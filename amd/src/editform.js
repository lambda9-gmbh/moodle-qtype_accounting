// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * JavaScript for the Accounting Entry question edit form.
 *
 * Sets up the form and its input handlers; the entry table logic lives in the editform/ modules.
 *
 * @module     qtype_accounting/editform
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Pending from 'core/pending';
import Log from 'core/log';
import * as EntryUtils from 'qtype_accounting/entry_utils';
import * as Validation from 'qtype_accounting/editform/validation';
import {state, ROW_SELECTOR, getNumberFormat} from 'qtype_accounting/editform/state';
import {syncDisplayToHidden, syncAllDisplayToHidden} from 'qtype_accounting/editform/fields';
import {updateDebitamountState, updateAllDebitamountStates, setupAllOrNothingHandler, updateWeightStates,
    updateAllWeightStates} from 'qtype_accounting/editform/field_states';
import {readAccounts, setupAccountListHandler, checkOptionAvailability} from 'qtype_accounting/editform/accounts';
import {calculateNextEntryIndex, setupAddEntryHandler, setupDeleteEntryHandler, updateDeleteButtonStates}
    from 'qtype_accounting/editform/rows';

/**
 * Initialize the edit form enhancements.
 * Data is read from a script tag to avoid js_call_amd size limits and HTML encoding issues.
 */
function init() {
    const dataElement = document.getElementById('qtype_accounting-editform-data');
    const form = dataElement ? dataElement.closest('form') : null;
    if (!form) {
        return;
    }
    state.form = form;
    const pending = new Pending('qtype_accounting/editform:strings');
    Validation.loadStrings().then(() => pending.resolve()).catch(Log.error);
    setupAccountCountHandler();
    let config = {};
    try {
        // Script tags use textContent, div elements use data-config attribute.
        const jsonText = dataElement.textContent || dataElement.getAttribute('data-config');
        if (jsonText) {
            config = JSON.parse(jsonText);
        }
    } catch (e) {
        Log.error('Failed to parse editform config', e);
    }

    state.knownKeys = config.accountKeys || {};
    state.maxEntries = config.maxEntries || 100;
    form.elements.accounting_js.value = '1';
    form.querySelectorAll('input[type="hidden"]').forEach(input => {
        if (/^(?:debit|credit|weight_).*\[\d+\]$/.test(input.name)) {
            input.disabled = false;
        }
    });
    const serverControls = form.querySelector('#fgroup_id_accountingservercontrols');
    if (serverControls) {
        serverControls.hidden = true;
    }
    form.querySelector('.qtype_accounting-edit-table').dataset.enhanced = 'true';

    // Find the controls by ID first, then fall back to name (for Moodle 4.x compatibility).
    state.accountsInput = document.getElementById('id_accounts') || form.querySelector('textarea[name="accounts"]');
    state.numberFormatSelect = document.getElementById('id_numberformat')
        || form.querySelector('select[name="numberformat"]');
    state.previousNumberFormat = getNumberFormat();
    // PHP already populated the account options from the same list.
    readAccounts();

    calculateNextEntryIndex();
    setupAccountListHandler();
    setupNumberFormatChangeHandler();
    setupFieldChangeHandlers();
    setupAddEntryHandler();
    setupDeleteEntryHandler();
    setupFormSubmitHandler();
    // PHP already populated the account options, so only the field states are updated.
    updateAllDebitamountStates();
    // Must run before updateAllWeightStates.
    setupAllOrNothingHandler();
    updateAllWeightStates();
    updateDeleteButtonStates();
    syncAllDisplayToHidden();

    // Initialize Bootstrap popovers (for weight tooltip).
    // Guard: the Bootstrap popover jQuery plugin is lazy-loaded and may not be
    // present on minimal pages (e.g., Behat). A missing popover is purely cosmetic.
    if (typeof $.fn.popover === 'function') {
        $('[data-toggle="popover"]').popover();
    }
}

/**
 * Display the meaning of blank or zero account limits when editing is finished.
 */
function setupAccountCountHandler() {
    const input = state.form.elements.accountsindropdown;
    if (!input) {
        return;
    }
    const allLabel = input.placeholder;
    const normalise = () => {
        const value = input.value.trim();
        if (value === '' || /^-?0+$/.test(value) || value.toLowerCase() === allLabel.toLowerCase()) {
            input.value = allLabel;
        }
    };
    normalise();
    input.addEventListener('change', normalise);
    input.addEventListener('blur', normalise);
    state.form.addEventListener('submit', normalise, true);
}

/**
 * Reformat all amount fields and their placeholders when the number format changes.
 */
function setupNumberFormatChangeHandler() {
    const select = state.numberFormatSelect;
    if (!select) {
        return;
    }
    select.addEventListener('change', function() {
        const fmt = select.value || 'de';
        const placeholder = (fmt === 'us') ? '0.00' : '0,00';
        state.form.querySelectorAll('.qtype_accounting-debitamount, .qtype_accounting-creditamount').forEach(field => {
            field.placeholder = placeholder;
            if (field.value) {
                const cents = EntryUtils.parseCents(field.value, state.previousNumberFormat);
                field.value = cents === null ? '' : EntryUtils.formatCents(cents, fmt);
            }
        });
        state.previousNumberFormat = fmt;
        syncAllDisplayToHidden();
    });
}

/**
 * Keep field states and hidden fields in step with the account, amount and weight controls.
 */
function setupFieldChangeHandlers() {
    const form = state.form;
    form.addEventListener('change', function(e) {
        if (!e.target || !e.target.classList) {
            return;
        }
        const index = e.target.getAttribute('data-index');
        if (e.target.classList.contains('qtype_accounting-debitaccount') ||
            e.target.classList.contains('qtype_accounting-creditaccount')) {
            checkOptionAvailability(e.target);
            updateDebitamountState(index);
            updateWeightStates(index);
            syncDisplayToHidden(index);
        }
        if (e.target.classList.contains('qtype_accounting-weight')) {
            syncDisplayToHidden(index);
        }
    });

    // Sync amount and weight fields on input, not just on change.
    form.addEventListener('input', function(e) {
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('qtype_accounting-debitamount') ||
            e.target.classList.contains('qtype_accounting-creditamount') ||
            e.target.classList.contains('qtype_accounting-weight')) {
            syncDisplayToHidden(e.target.getAttribute('data-index'));
        }
    });

    // Format amount fields when the user leaves them; blur does not bubble, so use capture.
    form.addEventListener('blur', function(e) {
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('qtype_accounting-debitamount') ||
            e.target.classList.contains('qtype_accounting-creditamount')) {
            e.target.value = EntryUtils.formatNumber(e.target.value, getNumberFormat(), 2);
            syncDisplayToHidden(e.target.getAttribute('data-index'));
        }
    }, true);
}

/**
 * Sync all display fields to hidden fields and validate the entries before submission.
 */
function setupFormSubmitHandler() {
    const form = state.form;
    form.addEventListener('submit', function(e) {
        // Moodle cancel buttons submit the form without saving or validating it.
        if (e.submitter && (e.submitter.name === 'cancel' || e.submitter.dataset.cancel === '1' ||
            e.submitter.hasAttribute('data-skip-validation'))) {
            return;
        }
        syncAllDisplayToHidden();
        let valid = Validation.validateAccounts(form, ROW_SELECTOR);
        if (!Validation.validateBalance(form, ROW_SELECTOR, getNumberFormat())) {
            valid = false;
        }
        if (!valid) {
            e.preventDefault();
            return;
        }
        // Only canonical hidden fields are submitted. Avoid duplicating every value
        // in PHP's max_input_vars budget; restore names if another listener cancels.
        const controls = Array.from(form.querySelectorAll('[name*="_display["]'));
        const names = controls.map(control => control.name);
        controls.forEach(control => control.removeAttribute('name'));
        setTimeout(() => controls.forEach((control, i) => {
            control.name = names[i];
        }), 0);
    });
}

export {init};
