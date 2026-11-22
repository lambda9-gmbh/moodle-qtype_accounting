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
 * Client-side validation of the correct entries in the question editor.
 *
 * Runs synchronously on submit, so the messages are loaded beforehand with {@see loadStrings}.
 *
 * @module     qtype_accounting/editform_validation
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';
import * as EntryUtils from 'qtype_accounting/entry_utils';

const STRING_KEYS = [
    'err_accountunavailable', 'err_debitaccountrequired', 'err_creditaccountrequired',
    'err_debitamountrequired', 'err_creditamountrequired', 'err_balancemismatch',
];

let messages = {};

/**
 * Load the validation messages.
 *
 * @return {Promise} Resolved when the messages are available.
 */
export const loadStrings = async() => {
    const strings = await getStrings(STRING_KEYS.map(key => ({key, component: 'qtype_accounting'})));
    messages = Object.fromEntries(STRING_KEYS.map((key, i) => [key, strings[i]]));
};

/**
 * Get the entry rows that are shown, excluding deleted rows and the row template.
 *
 * @param {HTMLFormElement} form The question form.
 * @param {string} rowSelector CSS selector of entry rows.
 * @return {HTMLElement[]} The rows.
 */
const visibleRows = (form, rowSelector) => Array.from(form.querySelectorAll(rowSelector))
    .filter(row => row.style.display !== 'none' && row.getAttribute('data-entry-index') !== '__INDEX__');

/**
 * Validate that every visible entry side has an available account and, with an account, an amount.
 *
 * @param {HTMLFormElement} form The question form.
 * @param {HTMLSelectElement|null} chartSelect The chart select.
 * @param {string} rowSelector CSS selector of entry rows.
 * @return {boolean} True if valid.
 */
export const validateAccounts = (form, chartSelect, rowSelector) => {
    let valid = true;
    const fail = (field, key) => {
        showFieldError(field, key);
        valid = false;
    };
    clearFieldErrors(form);
    if (chartSelect && chartSelect.selectedOptions[0] && chartSelect.selectedOptions[0].dataset.unavailable) {
        fail(chartSelect, 'err_accountunavailable');
    }
    visibleRows(form, rowSelector).forEach(row => {
        const entryType = row.getAttribute('data-entry-type') || 'both';
        ['debit', 'credit'].forEach(side => {
            if (entryType !== 'both' && entryType !== side) {
                return;
            }
            const account = row.querySelector('.qtype_accounting-' + side + 'account');
            const amount = row.querySelector('.qtype_accounting-' + side + 'amount');
            const hasAccount = account && account.value;
            if (hasAccount && account.selectedOptions[0].dataset.unavailable) {
                fail(account, 'err_accountunavailable');
            } else if (!hasAccount) {
                fail(account, 'err_' + side + 'accountrequired');
            }
            if (hasAccount && amount && amount.value.trim() === '') {
                fail(amount, 'err_' + side + 'amountrequired');
            }
        });
    });
    return valid;
};

/**
 * Validate that the debit amounts sum to the credit amounts, in exact cents.
 *
 * @param {HTMLFormElement} form The question form.
 * @param {string} rowSelector CSS selector of entry rows.
 * @param {string} numberFormat 'de' or 'us'.
 * @return {boolean} True if balanced.
 */
export const validateBalance = (form, rowSelector, numberFormat) => {
    const totals = {debit: 0n, credit: 0n};
    visibleRows(form, rowSelector).forEach(row => {
        ['debit', 'credit'].forEach(side => {
            const field = row.querySelector('.qtype_accounting-' + side + 'amount');
            const cents = field && field.value ? EntryUtils.parseCents(field.value, numberFormat) : null;
            if (cents !== null) {
                totals[side] += cents;
            }
        });
    });
    clearBalanceError();
    if (totals.debit !== totals.credit) {
        showBalanceError(form);
        return false;
    }
    return true;
};

/**
 * Show an inline validation error below a field.
 *
 * @param {HTMLElement} field The field.
 * @param {string} key The language string key of the message.
 */
const showFieldError = (field, key) => {
    const error = document.createElement('span');
    error.className = 'qtype_accounting-account-error form-control-feedback invalid-feedback d-block';
    error.id = field.id ? field.id + '_error' : 'qtype_accounting-error-' + field.name.replace(/[^a-z0-9_-]/gi, '_');
    error.textContent = messages[key] || key;
    field.classList.add('is-invalid');
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', error.id);
    field.parentNode.appendChild(error);
};

/**
 * Remove all inline validation errors of fields.
 *
 * @param {HTMLFormElement} form The question form.
 */
const clearFieldErrors = form => {
    form.querySelectorAll('.qtype_accounting-account-error').forEach(error => {
        const field = error.parentNode.querySelector('[aria-describedby="' + error.id + '"]');
        if (field) {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        }
        error.remove();
    });
};

/**
 * Show the balance error below the entries table.
 *
 * @param {HTMLFormElement} form The question form.
 */
const showBalanceError = form => {
    const table = form.querySelector('.qtype_accounting-edit-table');
    if (!table) {
        return;
    }
    const error = document.createElement('div');
    error.id = 'qtype_accounting-balance-error';
    error.className = 'form-control-feedback invalid-feedback d-block mt-2';
    error.setAttribute('role', 'alert');
    error.textContent = messages.err_balancemismatch || 'err_balancemismatch';
    table.parentNode.insertBefore(error, table.nextSibling);
};

/**
 * Remove the balance error.
 */
const clearBalanceError = () => {
    document.getElementById('qtype_accounting-balance-error')?.remove();
};
