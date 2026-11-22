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
 * @module     qtype_accounting/editform
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import * as Str from 'core/str';
import Log from 'core/log';
import {call as ajaxCall} from 'core/ajax';
import * as EntryUtils from 'qtype_accounting/entry_utils';
import {init as initChartOverlay} from 'qtype_accounting/chart_overlay';

// Module-level state.
let accountsByChart = {};
let lastChartId = null;
let nextEntryIndex = 0;
let allOrNothingEnabled = false;

// DOM element references.
let chartSelect = null;
let numberFormatSelect = null;
let initialChartId = '0';
let unavailableMessage = '';
let editForm = null;
let previousNumberFormat = 'de';
let maxEntries = 100;

// CSS selector for entry rows in the edit form.
let ROW_SELECTOR = '.qtype_accounting-entry-row';

/**
 * Initialize the edit form enhancements.
 * Data is read from a script tag to avoid js_call_amd size limits and HTML encoding issues.
 */
function init() {
    // Read configuration from script tag (preferred) or data attribute (fallback).
    const dataElement = document.getElementById('qtype_accounting-editform-data');
    editForm = dataElement ? dataElement.closest('form') : null;
    if (!editForm) {
        return;
    }
    setupAccountCountHandler();
    let config = {};
    if (dataElement) {
        try {
            // Script tags use textContent, div elements use data-config attribute.
            const jsonText = dataElement.textContent || dataElement.getAttribute('data-config');
            if (jsonText) {
                config = JSON.parse(jsonText);
            }
        } catch (e) {
            Log.error('Failed to parse editform config', e);
        }
    }

    accountsByChart = config.accounts || {};
    maxEntries = config.maxEntries || 100;
    editForm.elements.accounting_js.value = '1';
    editForm.querySelectorAll('input[type="hidden"]').forEach(input => {
        if (/^(?:debit|credit|weight_).*\[\d+\]$/.test(input.name)) {
            input.disabled = false;
        }
    });
    const serverControls = editForm.querySelector('#fgroup_id_accountingservercontrols');
    if (serverControls) {
        serverControls.hidden = true;
    }
    editForm.querySelector('.qtype_accounting-edit-table').dataset.enhanced = 'true';
    initialChartId = String(config.chartId || '0');

    // Find chart select by ID first, then fall back to name (for Moodle 4.x compatibility).
    chartSelect = document.getElementById('id_chartofaccountsid');
    if (!chartSelect) {
        chartSelect = editForm.querySelector('select[name="chartofaccountsid"]');
    }

    // Find number format select.
    numberFormatSelect = document.getElementById('id_numberformat');
    if (!numberFormatSelect) {
        numberFormatSelect = editForm.querySelector('select[name="numberformat"]');
    }

    // Initial setup - populate dropdowns based on selected chart.
    if (chartSelect && initialChartId && (!chartSelect.value || chartSelect.value === '0')) {
        chartSelect.value = initialChartId;
    }
    lastChartId = chartSelect ? chartSelect.value : '0';
    previousNumberFormat = getNumberFormat();

    // Calculate next entry index from existing rows.
    calculateNextEntryIndex();

    // Setup event handlers.
    setupChartChangeHandler();
    const refreshCharts = createChartRefreshCallback(config.contextId);
    initChartOverlay(refreshCharts);
    setupNumberFormatChangeHandler();
    setupDebitaccountChangeHandler();
    setupAddEntryHandler();
    setupDeleteEntryHandler();
    setupFormSubmitHandler();
    // Don't rebuild dropdowns on init - PHP already populated them correctly.
    // Only update debitamount states (disable if no debit account selected).
    updateAllDebitamountStates();

    // Setup all-or-nothing grading handler (must run before updateAllWeightStates).
    setupAllOrNothingHandler();

    // Update weight field states (disable if no account selected or all-or-nothing is active).
    updateAllWeightStates();

    // Update delete button states for each side.
    updateDeleteButtonStates();

    // Sync display fields to hidden fields for any existing entries.
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
    const input = editForm.elements.accountsindropdown;
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
    editForm.addEventListener('submit', normalise, true);
}

/**
 * Calculate the next entry index from existing rows.
 */
function calculateNextEntryIndex() {
    const existingRows = editForm.querySelectorAll(ROW_SELECTOR);
    let maxIndex = -1;
    existingRows.forEach(function(row) {
        const index = parseInt(row.getAttribute('data-entry-index'), 10);
        if (!isNaN(index) && index > maxIndex) {
            maxIndex = index;
        }
    });
    nextEntryIndex = maxIndex + 1;
}

/**
 * Setup chart selection change handler.
 */
function setupChartChangeHandler() {
    if (chartSelect) {
        chartSelect.addEventListener('change', function() {
            checkOptionAvailability(chartSelect);
            lastChartId = null; // Reset to force rebuild.
            updateAccountDropdowns(true);
        });
    }
}

/**
 * Create the refresh callback used when chart management closes, keeping the question form in place.
 *
 * @param {number} contextId Question bank context ID.
 * @returns {Function|null} Refresh callback, or null outside a course.
 */
function createChartRefreshCallback(contextId) {
    const status = document.getElementById('qtype_accounting-refresh-status');
    if (!status || !chartSelect || !contextId) {
        return null;
    }
    let pendingRefresh = Promise.resolve();
    const refresh = async function() {
        status.textContent = '';
        try {
            const messages = await Str.get_strings([
                {key: 'refreshchartsdone', component: 'qtype_accounting'},
                {key: 'refreshchartsmissing', component: 'qtype_accounting'},
                {key: 'err_accountunavailable', component: 'qtype_accounting'},
            ]);
            const charts = await ajaxCall([{
                methodname: 'qtype_accounting_get_charts',
                args: {contextid: contextId},
            }])[0];
            unavailableMessage = messages[2];
            // Read selections after the request: the teacher may edit while it is in flight.
            const chartId = chartSelect.value;
            const selectedOption = chartSelect.selectedOptions[0];
            const freshAccounts = {};
            while (chartSelect.options.length > 1) {
                chartSelect.remove(1);
            }
            charts.forEach(function(chart) {
                // The name is formatted for HTML output; the option needs its plain text.
                const label = new DOMParser().parseFromString(chart.name, 'text/html').body.textContent;
                chartSelect.add(new Option(label, chart.id));
                freshAccounts[chart.id] = {};
                chart.accounts.forEach(function(account) {
                    freshAccounts[chart.id][account.id] = account.name;
                });
            });
            if (chartId !== '0' && !Object.prototype.hasOwnProperty.call(freshAccounts, chartId)) {
                selectedOption.dataset.unavailable = 'true';
                chartSelect.add(selectedOption);
            }
            chartSelect.value = chartId;
            checkOptionAvailability(chartSelect);
            accountsByChart = freshAccounts;
            updateAccountDropdowns(true, true);
            syncAllDisplayToHidden();
            const missing = editForm.querySelector('option[data-unavailable="true"]:checked');
            status.textContent = ' ' + messages[missing ? 1 : 0];
        } catch (error) {
            Log.error('Failed to refresh accounting charts', error);
            status.textContent = ' ' + await Str.get_string('refreshchartsfailed', 'qtype_accounting');
        }
    };
    // Queue refreshes when overlays are closed before an earlier refresh completes.
    const refreshCharts = () => {
        pendingRefresh = pendingRefresh.then(refresh, refresh);
        return pendingRefresh;
    };
    return refreshCharts;
}

/**
 * Block saving a selection which disappeared during a refresh.
 *
 * @param {HTMLSelectElement} select Chart or account selector.
 */
function checkOptionAvailability(select) {
    const missing = select.selectedOptions[0] && select.selectedOptions[0].dataset.unavailable;
    select.setCustomValidity(missing ? unavailableMessage : '');
    select.classList.toggle('is-invalid', !!missing);
}

/**
 * Setup number format change handler.
 *
 * When the teacher changes the number format, reformat all amount fields
 * and update placeholders to match.
 */
function setupNumberFormatChangeHandler() {
    if (numberFormatSelect) {
        numberFormatSelect.addEventListener('change', function() {
            let fmt = numberFormatSelect.value || 'de';
            let placeholder = (fmt === 'us') ? '0.00' : '0,00';

            // Reformat all amount fields.
            let amountFields = editForm.querySelectorAll('.qtype_accounting-debitamount, .qtype_accounting-creditamount');
            amountFields.forEach(function(field) {
                // Update placeholder.
                field.placeholder = placeholder;
                // Reformat current value if present.
                if (field.value) {
                    const cents = EntryUtils.parseCents(field.value, previousNumberFormat);
                    field.value = cents === null ? '' : EntryUtils.formatCents(cents, fmt);
                }
            });

            previousNumberFormat = fmt;
            // Sync to hidden fields after reformatting.
            syncAllDisplayToHidden();
        });
    }
}

/**
 * Setup event delegation for debitaccount and creditaccount changes.
 */
function setupDebitaccountChangeHandler() {
    editForm.addEventListener('change', function(e) {
        // Guard against events where target doesn't have classList.
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('qtype_accounting-debitaccount')) {
            checkOptionAvailability(e.target);
            const index = e.target.getAttribute('data-index');
            updateDebitamountState(index);
            updateWeightStates(index);
            syncDisplayToHidden(index);
        }
        // Handle creditaccount changes to update weight states.
        if (e.target.classList.contains('qtype_accounting-creditaccount')) {
            checkOptionAvailability(e.target);
            const index = e.target.getAttribute('data-index');
            updateDebitamountState(index);
            updateWeightStates(index);
            syncDisplayToHidden(index);
        }
        // Also sync weight fields on change.
        if (e.target.classList.contains('qtype_accounting-weight')) {
            const index = e.target.getAttribute('data-index');
            syncDisplayToHidden(index);
        }
    });

    // Sync amount fields on input (not just change).
    editForm.addEventListener('input', function(e) {
        // Guard against events where target doesn't have classList.
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('qtype_accounting-debitamount') ||
            e.target.classList.contains('qtype_accounting-creditamount') ||
            e.target.classList.contains('qtype_accounting-weight')) {
            const index = e.target.getAttribute('data-index');
            syncDisplayToHidden(index);
        }
    });

    // Format amount fields on blur (when user leaves the field).
    editForm.addEventListener('blur', function(e) {
        // Guard against events where target doesn't have classList.
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('qtype_accounting-debitamount') ||
            e.target.classList.contains('qtype_accounting-creditamount')) {
            // Format the display value using the selected number format.
            const fmt = getNumberFormat();
            const formatted = EntryUtils.formatNumber(e.target.value, fmt, 2);
            e.target.value = formatted;
            // Also sync to hidden field (with plain number).
            const index = e.target.getAttribute('data-index');
            syncDisplayToHidden(index);
        }
    }, true); // Use capture to ensure we get the event before it bubbles.
}

/**
 * Setup add entry button handlers.
 */
function setupAddEntryHandler() {
    const addDebitButton = document.getElementById('qtype_accounting-add-debit-entry');
    const addCreditButton = document.getElementById('qtype_accounting-add-credit-entry');

    if (addDebitButton) {
        addDebitButton.addEventListener('click', function(e) {
            e.preventDefault();
            addEntryRow('debit');
        });
    }

    if (addCreditButton) {
        addCreditButton.addEventListener('click', function(e) {
            e.preventDefault();
            addEntryRow('credit');
        });
    }
}

/**
 * Setup delete entry button handlers (event delegation).
 */
function setupDeleteEntryHandler() {
    editForm.addEventListener('click', function(e) {
        // Handle delete debit button.
        const deleteDebitBtn = e.target.closest('.qtype_accounting-delete-debit');
        if (deleteDebitBtn) {
            e.preventDefault();
            const index = deleteDebitBtn.getAttribute('data-index');
            deleteEntrySide(index, 'debit');
            return;
        }

        // Handle delete credit button.
        const deleteCreditBtn = e.target.closest('.qtype_accounting-delete-credit');
        if (deleteCreditBtn) {
            e.preventDefault();
            const index = deleteCreditBtn.getAttribute('data-index');
            deleteEntrySide(index, 'credit');
            return;
        }
    });
}

/**
 * Delete one side of an entry (debit or credit).
 *
 * @param {string|number} index The entry index.
 * @param {string} side The side to delete: 'debit' or 'credit'.
 */
function deleteEntrySide(index, side) {
    const entryRow = editForm.querySelector(ROW_SELECTOR + '[data-entry-index="' + index + '"]');
    const weightRow = editForm.querySelector('.qtype_accounting-weight-row[data-entry-index="' + index + '"]');

    if (!entryRow || getEntrySideRows(side).length <= 1) {
        return;
    }

    const currentType = entryRow.getAttribute('data-entry-type') || 'both';

    if (currentType === 'both') {
        // Row has both sides - just hide the deleted side.
        const newType = (side === 'debit') ? 'credit' : 'debit';
        entryRow.setAttribute('data-entry-type', newType);

        // Apply visibility for the new type.
        EntryUtils.applyEntryTypeVisibility(entryRow, newType);
        EntryUtils.applyWeightRowVisibility(weightRow, newType);

        // Clear the hidden side's visible fields.
        EntryUtils.clearEntrySideFields(entryRow, side);
        // Clear the hidden form fields for this side.
        clearHiddenFieldsForSide(index, side);

        EntryUtils.updatePerLabels(editForm, ROW_SELECTOR);
        updateDeleteButtonStates();
    } else if ((currentType === 'debit' && side === 'debit') ||
               (currentType === 'credit' && side === 'credit')) {
        // Row only has this side - delete the entire row.
        deleteEntryRow(index);
    }
    // If trying to delete a side that's already hidden, do nothing.
}

/**
 * Clear hidden form fields for one side of an entry.
 *
 * @param {string|number} index The entry index.
 * @param {string} side The side to clear: 'debit' or 'credit'.
 */
function clearHiddenFieldsForSide(index, side) {
    if (side === 'debit') {
        let debitaccountHidden = getFieldByName('debitaccount[' + index + ']');
        let debitamountHidden = getFieldByName('debitamount[' + index + ']');
        if (debitaccountHidden) {
            debitaccountHidden.value = '';
        }
        if (debitamountHidden) {
            debitamountHidden.value = '';
        }
    } else if (side === 'credit') {
        let creditaccountHidden = getFieldByName('creditaccount[' + index + ']');
        let creditamountHidden = getFieldByName('creditamount[' + index + ']');
        if (creditaccountHidden) {
            creditaccountHidden.value = '';
        }
        if (creditamountHidden) {
            creditamountHidden.value = '';
        }
    }
}

/**
 * Setup form submit handler to sync all display fields to hidden fields
 * and validate balance before submission.
 */
function setupFormSubmitHandler() {
    const form = editForm;
    if (form) {
        form.addEventListener('submit', function(e) {
            // Moodle cancel buttons submit the form without saving or validating it.
            if (e.submitter && (e.submitter.name === 'cancel' || e.submitter.dataset.cancel === '1' ||
                e.submitter.hasAttribute('data-skip-validation'))) {
                return;
            }
            syncAllDisplayToHidden();
            let valid = validateAccounts();
            if (!validateBalance()) {
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
}

/**
 * Validate that every visible entry side has an account selected.
 *
 * @return {boolean} True if all visible sides have accounts, false if not.
 */
function validateAccounts() {
    let errors = [];
    clearAccountErrors();
    if (chartSelect && chartSelect.selectedOptions[0] && chartSelect.selectedOptions[0].dataset.unavailable) {
        showAccountError(chartSelect, 'err_accountunavailable');
        errors.push('chart');
    }

    let entryRows = editForm.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function(row) {
        if (row.style.display === 'none') {
            return;
        }
        let index = row.getAttribute('data-entry-index');
        if (index === '__INDEX__') {
            return;
        }

        let entryType = row.getAttribute('data-entry-type') || 'both';
        let debitAccount = row.querySelector('.qtype_accounting-debitaccount');
        let creditAccount = row.querySelector('.qtype_accounting-creditaccount');

        // Debit side is visible for 'debit' and 'both' entries.
        if (entryType === 'debit' || entryType === 'both') {
            let hasDebitAccount = debitAccount && debitAccount.value && debitAccount.value !== '';
            if (hasDebitAccount && debitAccount.selectedOptions[0].dataset.unavailable) {
                showAccountError(debitAccount, 'err_accountunavailable');
                errors.push(index);
            } else if (!hasDebitAccount) {
                showAccountError(debitAccount, 'err_debitaccountrequired');
                errors.push(index);
            }
        }

        // Credit side is visible for 'credit' and 'both' entries.
        if (entryType === 'credit' || entryType === 'both') {
            let hasCreditAccount = creditAccount && creditAccount.value && creditAccount.value !== '';
            if (hasCreditAccount && creditAccount.selectedOptions[0].dataset.unavailable) {
                showAccountError(creditAccount, 'err_accountunavailable');
                errors.push(index);
            } else if (!hasCreditAccount) {
                showAccountError(creditAccount, 'err_creditaccountrequired');
                errors.push(index);
            }
        }
        ['debit', 'credit'].forEach(side => {
            const account = row.querySelector('.qtype_accounting-' + side + 'account');
            const amount = row.querySelector('.qtype_accounting-' + side + 'amount');
            if ((entryType === 'both' || entryType === side) && account && account.value && amount &&
                    amount.value.trim() === '') {
                showAccountError(amount, 'err_' + side + 'amountrequired');
                errors.push(index);
            }
        });
    });

    return errors.length === 0;
}

/**
 * Show an inline validation error below a specific field.
 *
 * @param {HTMLElement} field The field element to show the error below.
 * @param {string} stringKey The language string key for the error message.
 */
function showAccountError(field, stringKey) {
    let errorSpan = document.createElement('span');
    errorSpan.className = 'qtype_accounting-account-error form-control-feedback invalid-feedback d-block';
    errorSpan.id = field.id ? field.id + '_error' : 'qtype_accounting-error-' + field.name.replace(/[^a-z0-9_-]/gi, '_');
    errorSpan.textContent = M.util.get_string(stringKey, 'qtype_accounting');
    field.classList.add('is-invalid');
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', errorSpan.id);
    field.parentNode.appendChild(errorSpan);
}

/**
 * Clear all inline account validation errors.
 */
function clearAccountErrors() {
    let existing = editForm.querySelectorAll('.qtype_accounting-account-error');
    existing.forEach(function(el) {
        const field = el.parentNode.querySelector('[aria-describedby="' + el.id + '"]');
        if (field) {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        }
        el.remove();
    });
}

/**
 * Validate that total debit amounts equal total credit amounts.
 *
 * @return {boolean} True if balanced, false if not.
 */
function validateBalance() {
    let totalDebit = 0n;
    let totalCredit = 0n;

    let entryRows = editForm.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function(row) {
        if (row.style.display === 'none') {
            return;
        }
        let index = row.getAttribute('data-entry-index');
        if (index === '__INDEX__') {
            return;
        }

        let debitField = row.querySelector('.qtype_accounting-debitamount');
        let creditField = row.querySelector('.qtype_accounting-creditamount');

        if (debitField && debitField.value) {
            let parsedDebit = EntryUtils.parseCents(debitField.value, getNumberFormat());
            if (parsedDebit !== null) {
                totalDebit += parsedDebit;
            }
        }
        if (creditField && creditField.value) {
            let parsedCredit = EntryUtils.parseCents(creditField.value, getNumberFormat());
            if (parsedCredit !== null) {
                totalCredit += parsedCredit;
            }
        }
    });

    if (totalDebit !== totalCredit) {
        showBalanceError();
        return false;
    }

    clearBalanceError();
    return true;
}

/**
 * Show balance validation error below the entries table.
 */
function showBalanceError() {
    clearBalanceError();
    let table = editForm.querySelector('.qtype_accounting-edit-table');
    if (!table) {
        return;
    }
    let errorDiv = document.createElement('div');
    errorDiv.id = 'qtype_accounting-balance-error';
    errorDiv.className = 'form-control-feedback invalid-feedback d-block mt-2';
    errorDiv.setAttribute('role', 'alert');
    errorDiv.textContent = M.util.get_string('err_balancemismatch', 'qtype_accounting');
    table.parentNode.insertBefore(errorDiv, table.nextSibling);
}

/**
 * Clear the balance validation error if present.
 */
function clearBalanceError() {
    let existing = document.getElementById('qtype_accounting-balance-error');
    if (existing) {
        existing.remove();
    }
}

/**
 * Add a new entry row to the table.
 *
 * @param {string} entryType The type of entry: 'debit', 'credit', or 'both'.
 */
function addEntryRow(entryType) {
    entryType = entryType || 'both';

    // First, check if we can complete an existing incomplete row.
    const incompleteRow = EntryUtils.findIncompleteRow(editForm, entryType, ROW_SELECTOR);
    if (incompleteRow) {
        // Complete the existing row by making it 'both'.
        incompleteRow.setAttribute('data-entry-type', 'both');

        // Find the associated weight row.
        const index = incompleteRow.getAttribute('data-entry-index');
        const weightRow = editForm.querySelector('.qtype_accounting-weight-row[data-entry-index="' + index + '"]');

        // Remove hidden-cell classes from both rows.
        EntryUtils.applyEntryTypeVisibility(incompleteRow, 'both');
        EntryUtils.applyWeightRowVisibility(weightRow, 'both');

        // Update states for the completed row.
        updateDebitamountState(index);
        updateWeightStates(index);
        EntryUtils.updatePerLabels(editForm, ROW_SELECTOR);
        updateDeleteButtonStates();
        return;
    }

    if (editForm.querySelectorAll(ROW_SELECTOR).length >= maxEntries) {
        return;
    }
    // No incomplete row to complete, so add a new row.
    const template = document.getElementById('qtype_accounting-entry-template');
    const tbody = document.getElementById('qtype_accounting-entries-body');

    if (!template || !tbody) {
        Log.error('Template or tbody not found', {template: template, tbody: tbody});
        return;
    }

    // Clone the template content (template.content is a DocumentFragment).
    const clone = template.content.cloneNode(true);

    // Update all __INDEX__ placeholders in the cloned content.
    // We need to update attributes and text content.
    clone.querySelectorAll('[data-index="__INDEX__"]').forEach(function(el) {
        el.setAttribute('data-index', nextEntryIndex);
    });
    clone.querySelectorAll('[data-entry-index="__INDEX__"]').forEach(function(el) {
        el.setAttribute('data-entry-index', nextEntryIndex);
    });
    clone.querySelectorAll('[name*="__INDEX__"]').forEach(function(el) {
        el.name = el.name.replace('__INDEX__', nextEntryIndex);
    });
    clone.querySelectorAll('[id*="__INDEX__"]').forEach(function(el) {
        el.id = el.id.replace('__INDEX__', nextEntryIndex);
    });

    // Set the entry type on the entry row.
    const entryRow = clone.querySelector(ROW_SELECTOR);
    const weightRow = clone.querySelector('.qtype_accounting-weight-row');
    if (entryRow) {
        entryRow.setAttribute('data-entry-type', entryType);
    }

    // Apply hidden-cell class based on entry type.
    EntryUtils.applyEntryTypeVisibility(entryRow, entryType);
    EntryUtils.applyWeightRowVisibility(weightRow, entryType);

    // Append the cloned rows to the tbody.
    tbody.appendChild(clone);

    // Update dropdowns for the new row (force rebuild to populate new row's selects).
    updateAccountDropdowns(true, true);
    updateDebitamountState(nextEntryIndex);
    updateWeightStates(nextEntryIndex);

    // Initialize hidden fields for the new index.
    initializeHiddenFieldsForIndex(nextEntryIndex);

    // Update delete button states for each side.
    updateDeleteButtonStates();

    // Update Per/an labels.
    EntryUtils.updatePerLabels(editForm, ROW_SELECTOR);

    nextEntryIndex++;
}

/**
 * Initialize hidden form fields for a given index.
 *
 * @param {number} index The entry index.
 */
function initializeHiddenFieldsForIndex(index) {
    const form = editForm;
    if (!form) {
        return;
    }

    const fields = ['debitaccount', 'debitamount', 'creditaccount', 'creditamount',
        'weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount'];
    fields.forEach(field => {
        const name = field + '[' + index + ']';
        if (!getFieldByName(name)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = field.startsWith('weight_') ? '1' : '';
            form.appendChild(input);
        }
    });
    syncDisplayToHidden(index);
}

/**
 * Delete an entry row from the table.
 *
 * @param {string|number} index The entry index to delete.
 */
function deleteEntryRow(index) {
    // Prevent deleting the last remaining entry.
    const allEntryRows = editForm.querySelectorAll(ROW_SELECTOR);
    if (allEntryRows.length <= 1) {
        return;
    }

    const entryRow = editForm.querySelector(ROW_SELECTOR + '[data-entry-index="' + index + '"]');
    const weightRow = editForm.querySelector('.qtype_accounting-weight-row[data-entry-index="' + index + '"]');

    if (entryRow) {
        entryRow.remove();
    }
    if (weightRow) {
        weightRow.remove();
    }

    // Clear the hidden fields for this index.
    clearHiddenFieldsForIndex(index);
    editForm.querySelectorAll('input[type="hidden"]').forEach(input => {
        if (input.name.endsWith('[' + index + ']')) {
            input.remove();
        }
    });

    // Update Per/an labels (first visible row should have them).
    EntryUtils.updatePerLabels(editForm, ROW_SELECTOR);

    // Update delete button states for each side.
    updateDeleteButtonStates();
}

/**
 * Get the entry rows containing the given side, independently of row indices.
 *
 * @param {string} side The side to count: 'debit' or 'credit'.
 * @return {Element[]} The rows containing that side.
 */
function getEntrySideRows(side) {
    return Array.from(editForm.querySelectorAll(ROW_SELECTOR)).filter(row => {
        const entryType = row.getAttribute('data-entry-type') || 'both';
        return entryType === 'both' || entryType === side;
    });
}

/**
 * Update delete buttons so each side keeps at least one entry.
 */
function updateDeleteButtonStates() {
    const allEntryRows = editForm.querySelectorAll(ROW_SELECTOR);
    editForm.querySelectorAll('.qtype_accounting-add-debit-entry, .qtype_accounting-add-credit-entry')
        .forEach(button => {
            button.disabled = allEntryRows.length >= maxEntries;
        });

    ['debit', 'credit'].forEach(side => {
        const isOnlyOne = getEntrySideRows(side).length <= 1;
        editForm.querySelectorAll('.qtype_accounting-delete-' + side).forEach(button => {
            button.disabled = isOnlyOne;
            button.classList.toggle('disabled', isOnlyOne);
        });
    });
}

/**
 * Clear hidden form fields for a given index.
 *
 * @param {string|number} index The entry index.
 */
function clearHiddenFieldsForIndex(index) {
    // Clear all Moodle hidden fields.
    const allFields = ['debitaccount', 'debitamount', 'creditaccount', 'creditamount',
                    'weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount'];
    allFields.forEach(function(field) {
        const hiddenField = getFieldByName(field + '[' + index + ']');
        if (hiddenField) {
            hiddenField.value = '';
        }
    });
}

/**
 * Get a form field by name, handling array-style names properly.
 *
 * @param {string} name The field name (e.g., "debitamount[0]").
 * @return {Element|null} The form field element or null.
 */
function getFieldByName(name) {
    return Array.from(editForm.elements).find(field => field.name === name) || null;
}

/**
 * Get the currently selected number format from the form.
 *
 * @return {string} The number format: 'de' or 'us'.
 */
function getNumberFormat() {
    if (numberFormatSelect) {
        return numberFormatSelect.value || 'de';
    }
    return 'de';
}

/**
 * Sync a single entry's display fields to hidden form fields.
 *
 * @param {string|number} index The entry index.
 */
function syncDisplayToHidden(index) {
    if (!getFieldByName('debitaccount[' + index + ']')) {
        initializeHiddenFieldsForIndex(index);
        return;
    }
    // Sync account selects.
    const debitaccountDisplay = editForm.querySelector('.qtype_accounting-debitaccount[data-index="' + index + '"]');
    const debitaccountHidden = getFieldByName('debitaccount[' + index + ']');
    if (debitaccountDisplay && debitaccountHidden) {
        debitaccountHidden.value = debitaccountDisplay.value;
    }

    const creditaccountDisplay = editForm.querySelector('.qtype_accounting-creditaccount[data-index="' + index + '"]');
    const creditaccountHidden = getFieldByName('creditaccount[' + index + ']');
    if (creditaccountDisplay && creditaccountHidden) {
        creditaccountHidden.value = creditaccountDisplay.value;
    }

    // Sync amount fields - parse German format to plain numbers for hidden fields.
    const debitamountDisplay = editForm.querySelector('.qtype_accounting-debitamount[data-index="' + index + '"]');
    const debitamountHidden = getFieldByName('debitamount[' + index + ']');
    if (debitamountDisplay && debitamountHidden) {
        debitamountHidden.value = EntryUtils.parseNumber(debitamountDisplay.value, getNumberFormat());
    }

    const creditamountDisplay = editForm.querySelector('.qtype_accounting-creditamount[data-index="' + index + '"]');
    const creditamountHidden = getFieldByName('creditamount[' + index + ']');
    if (creditamountDisplay && creditamountHidden) {
        creditamountHidden.value = EntryUtils.parseNumber(creditamountDisplay.value, getNumberFormat());
    }

    // Sync weight fields.
    const weightFields = ['debitaccount', 'debitamount', 'creditaccount', 'creditamount'];
    weightFields.forEach(function(field) {
        const displayField = editForm.querySelector(
            '.qtype_accounting-weight[data-index="' + index + '"][data-field="' + field + '"]'
        );
        const hiddenField = getFieldByName('weight_' + field + '[' + index + ']');
        if (displayField && hiddenField) {
            hiddenField.value = displayField.value;
        }
    });
}

/**
 * Sync all display fields to hidden form fields.
 */
function syncAllDisplayToHidden() {
    const entryRows = editForm.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function(row) {
        const index = row.getAttribute('data-entry-index');
        if (index !== '__INDEX__') {
            syncDisplayToHidden(index);
        }
    });
}

/**
 * Update account dropdowns based on selected chart.
 *
 * @param {boolean} forceRebuild Whether to force rebuild even if chart hasn't changed.
 * @param {boolean} preserveMissing Whether to retain unavailable selections and their amounts.
 */
function updateAccountDropdowns(forceRebuild, preserveMissing = false) {
    const chartId = chartSelect ? chartSelect.value : '0';
    const accounts = accountsByChart[chartId] || {};

    // Skip if chart hasn't changed (unless forced).
    if (!forceRebuild && lastChartId === chartId) {
        return;
    }
    lastChartId = chartId;

    // Find all debitaccount and creditaccount selects (display fields).
    const debitSelects = editForm.querySelectorAll('select.qtype_accounting-debitaccount');
    const creditSelects = editForm.querySelectorAll('select.qtype_accounting-creditaccount');

    debitSelects.forEach(function(select) {
        const currentValue = select.value;
        const currentOption = select.selectedOptions[0];
        // Keep first option (placeholder).
        while (select.options.length > 1) {
            select.remove(1);
        }
        for (const accountName in accounts) {
            const option = document.createElement('option');
            option.value = accountName;
            option.text = accounts[accountName];
            select.add(option);
        }
        if (currentValue) {
            if (preserveMissing && !Object.prototype.hasOwnProperty.call(accounts, currentValue)) {
                currentOption.dataset.unavailable = 'true';
                select.add(currentOption);
            }
            select.value = currentValue;
        }
        checkOptionAvailability(select);
    });

    creditSelects.forEach(function(select) {
        const currentValue = select.value;
        const currentOption = select.selectedOptions[0];
        while (select.options.length > 1) {
            select.remove(1);
        }
        for (const accountName in accounts) {
            const option = document.createElement('option');
            option.value = accountName;
            option.text = accounts[accountName];
            select.add(option);
        }
        if (currentValue) {
            if (preserveMissing && !Object.prototype.hasOwnProperty.call(accounts, currentValue)) {
                currentOption.dataset.unavailable = 'true';
                select.add(currentOption);
            }
            select.value = currentValue;
        }
        checkOptionAvailability(select);
    });

    updateAllDebitamountStates();
    updateAllWeightStates();
}

/**
 * Update the disabled state of a Debit (debit) amount field.
 *
 * @param {string|number} index The entry index.
 */
function updateDebitamountState(index) {
    ['debit', 'credit'].forEach(side => {
        const select = editForm.querySelector('.qtype_accounting-' + side + 'account[data-index="' + index + '"]');
        const amount = editForm.querySelector('.qtype_accounting-' + side + 'amount[data-index="' + index + '"]');
        if (select && amount) {
            amount.disabled = !select.value;
            if (select.value) {
                amount.title = '';
            } else {
                Str.get_string('select' + side + 'accountfirst', 'qtype_accounting').then(str => {
                    amount.title = str;
                    return str;
                }).catch(Log.error);
                amount.value = '';
            }
        }
    });
}

/**
 * Update all Debit (debit) amount field states.
 */
function updateAllDebitamountStates() {
    const entryRows = editForm.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function(row) {
        const index = row.getAttribute('data-entry-index');
        if (index !== '__INDEX__') {
            updateDebitamountState(index);
        }
    });
}

/**
 * Setup the all-or-nothing grading checkbox handler.
 *
 * Reads the initial checkbox state and listens for changes to toggle
 * weight field disabled state.
 */
function setupAllOrNothingHandler() {
    let checkbox = document.getElementById('id_allornothinggrading');
    if (!checkbox) {
        return;
    }

    allOrNothingEnabled = checkbox.checked;

    checkbox.addEventListener('change', function() {
        allOrNothingEnabled = checkbox.checked;
        updateAllWeightStates();
    });
}

/**
 * Update the disabled state of weight selectors based on account selection.
 * Disables debitaccount/debitamount weights when no debit account is selected.
 * Disables creditaccount/creditamount weights when no credit account is selected.
 * Also disables all weights when all-or-nothing grading is enabled.
 *
 * @param {string|number} index The entry index.
 */
function updateWeightStates(index) {
    const debitSelect = editForm.querySelector('select.qtype_accounting-debitaccount[data-index="' + index + '"]');
    const creditSelect = editForm.querySelector('select.qtype_accounting-creditaccount[data-index="' + index + '"]');

    const hasDebitAccount = debitSelect && debitSelect.value !== '' && debitSelect.value !== null;
    const hasCreditAccount = creditSelect && creditSelect.value !== '' && creditSelect.value !== null;

    // When all-or-nothing is enabled, all weights are disabled regardless of account state.
    let debitEnabled = hasDebitAccount && !allOrNothingEnabled;
    let creditEnabled = hasCreditAccount && !allOrNothingEnabled;

    // Update debit weight fields.
    const weightDebitaccount = editForm.querySelector(
        '.qtype_accounting-weight[data-index="' + index + '"][data-field="debitaccount"]'
    );
    const weightDebitamount = editForm.querySelector(
        '.qtype_accounting-weight[data-index="' + index + '"][data-field="debitamount"]'
    );

    if (weightDebitaccount) {
        weightDebitaccount.disabled = !debitEnabled;


    }
    if (weightDebitamount) {
        weightDebitamount.disabled = !debitEnabled;


    }

    // Update credit weight fields.
    const creditWeightBase = '.qtype_accounting-weight[data-index="' + index + '"]';
    const weightCreditaccount = editForm.querySelector(creditWeightBase + '[data-field="creditaccount"]');
    const weightCreditamount = editForm.querySelector(creditWeightBase + '[data-field="creditamount"]');

    if (weightCreditaccount) {
        weightCreditaccount.disabled = !creditEnabled;


    }
    if (weightCreditamount) {
        weightCreditamount.disabled = !creditEnabled;


    }
}

/**
 * Update all weight field states.
 */
function updateAllWeightStates() {
    const entryRows = editForm.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function(row) {
        const index = row.getAttribute('data-entry-index');
        if (index !== '__INDEX__') {
            updateWeightStates(index);
        }
    });
}

export {init};
