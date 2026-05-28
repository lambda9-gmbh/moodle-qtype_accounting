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
import * as EntryUtils from 'qtype_accounting/entry_utils';

// Module-level state.
let accountsByChart = {};
let lastChartId = null;
let nextEntryIndex = 0;
let allOrNothingEnabled = false;

// DOM element references.
let chartSelect = null;
let numberFormatSelect = null;
let initialChartId = '0';

// CSS selector for entry rows in the edit form.
var ROW_SELECTOR = '.accounting-entry-row';

/**
 * Initialize the edit form enhancements.
 * Data is read from a script tag to avoid js_call_amd size limits and HTML encoding issues.
 */
function init() {
    // Read configuration from script tag (preferred) or data attribute (fallback).
    const dataElement = document.getElementById('accounting-editform-data');
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
    initialChartId = String(config.chartId || '0');

    // Find chart select by ID first, then fall back to name (for Moodle 4.x compatibility).
    chartSelect = document.getElementById('id_chartofaccountsid');
    if (!chartSelect) {
        chartSelect = document.querySelector('select[name="chartofaccountsid"]');
    }

    // Find number format select.
    numberFormatSelect = document.getElementById('id_numberformat');
    if (!numberFormatSelect) {
        numberFormatSelect = document.querySelector('select[name="numberformat"]');
    }

    // Initial setup - populate dropdowns based on selected chart.
    if (chartSelect && initialChartId && (!chartSelect.value || chartSelect.value === '0')) {
        chartSelect.value = initialChartId;
    }
    lastChartId = chartSelect ? chartSelect.value : '0';

    // Calculate next entry index from existing rows.
    calculateNextEntryIndex();

    // Setup event handlers.
    setupChartChangeHandler();
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

    // Update delete button states (disable if only one entry).
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
 * Calculate the next entry index from existing rows.
 */
function calculateNextEntryIndex() {
    const existingRows = document.querySelectorAll(ROW_SELECTOR);
    let maxIndex = -1;
    existingRows.forEach(function (row) {
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
        chartSelect.addEventListener('change', function () {
            lastChartId = null; // Reset to force rebuild.
            updateAccountDropdowns(true);
        });
    }
}

/**
 * Setup number format change handler.
 *
 * When the teacher changes the number format, reformat all amount fields
 * and update placeholders to match.
 */
function setupNumberFormatChangeHandler() {
    if (numberFormatSelect) {
        numberFormatSelect.addEventListener('change', function () {
            var fmt = numberFormatSelect.value || 'de';
            var placeholder = (fmt === 'us') ? '0.00' : '0,00';

            // Reformat all amount fields.
            var amountFields = document.querySelectorAll('.accounting-debitamount, .accounting-creditamount');
            amountFields.forEach(function (field) {
                // Update placeholder.
                field.placeholder = placeholder;
                // Reformat current value if present.
                if (field.value) {
                    var formatted = EntryUtils.formatNumber(field.value, fmt, 2);
                    field.value = formatted;
                }
            });

            // Sync to hidden fields after reformatting.
            syncAllDisplayToHidden();
        });
    }
}

/**
 * Setup event delegation for debitaccount and creditaccount changes.
 */
function setupDebitaccountChangeHandler() {
    document.addEventListener('change', function (e) {
        // Guard against events where target doesn't have classList.
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('accounting-debitaccount')) {
            const index = e.target.getAttribute('data-index');
            updateDebitamountState(index);
            updateWeightStates(index);
            syncDisplayToHidden(index);
        }
        // Handle creditaccount changes to update weight states.
        if (e.target.classList.contains('accounting-creditaccount')) {
            const index = e.target.getAttribute('data-index');
            updateWeightStates(index);
            syncDisplayToHidden(index);
        }
        // Also sync weight fields on change.
        if (e.target.classList.contains('accounting-weight')) {
            const index = e.target.getAttribute('data-index');
            syncDisplayToHidden(index);
        }
    });

    // Sync amount fields on input (not just change).
    document.addEventListener('input', function (e) {
        // Guard against events where target doesn't have classList.
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('accounting-debitamount') ||
            e.target.classList.contains('accounting-creditamount') ||
            e.target.classList.contains('accounting-weight')) {
            const index = e.target.getAttribute('data-index');
            syncDisplayToHidden(index);
        }
    });

    // Format amount fields on blur (when user leaves the field).
    document.addEventListener('blur', function (e) {
        // Guard against events where target doesn't have classList.
        if (!e.target || !e.target.classList) {
            return;
        }
        if (e.target.classList.contains('accounting-debitamount') ||
            e.target.classList.contains('accounting-creditamount')) {
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
    const addDebitButton = document.getElementById('accounting-add-debit-entry');
    const addCreditButton = document.getElementById('accounting-add-credit-entry');

    if (addDebitButton) {
        addDebitButton.addEventListener('click', function (e) {
            e.preventDefault();
            addEntryRow('debit');
        });
    }

    if (addCreditButton) {
        addCreditButton.addEventListener('click', function (e) {
            e.preventDefault();
            addEntryRow('credit');
        });
    }
}

/**
 * Setup delete entry button handlers (event delegation).
 */
function setupDeleteEntryHandler() {
    document.addEventListener('click', function (e) {
        // Handle delete debit button.
        const deleteDebitBtn = e.target.closest('.accounting-delete-debit');
        if (deleteDebitBtn) {
            e.preventDefault();
            const index = deleteDebitBtn.getAttribute('data-index');
            deleteEntrySide(index, 'debit');
            return;
        }

        // Handle delete credit button.
        const deleteCreditBtn = e.target.closest('.accounting-delete-credit');
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
    const entryRow = document.querySelector(ROW_SELECTOR + '[data-entry-index="' + index + '"]');
    const weightRow = document.querySelector('.accounting-weight-row[data-entry-index="' + index + '"]');

    if (!entryRow) {
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

        EntryUtils.updatePerLabels(document, ROW_SELECTOR);
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
        var debitaccountHidden = getFieldByName('debitaccount[' + index + ']');
        var debitamountHidden = getFieldByName('debitamount[' + index + ']');
        if (debitaccountHidden) {
            debitaccountHidden.value = '';
        }
        if (debitamountHidden) {
            debitamountHidden.value = '';
        }
    } else if (side === 'credit') {
        var creditaccountHidden = getFieldByName('creditaccount[' + index + ']');
        var creditamountHidden = getFieldByName('creditamount[' + index + ']');
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
    const form = document.querySelector('form.mform') || document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            syncAllDisplayToHidden();
            var valid = validateAccounts();
            if (!validateBalance()) {
                valid = false;
            }
            if (!valid) {
                e.preventDefault();
            }
        });
    }
}

/**
 * Validate that every visible entry side has an account selected.
 *
 * @return {boolean} True if all visible sides have accounts, false if not.
 */
function validateAccounts() {
    var errors = [];
    clearAccountErrors();

    var entryRows = document.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function (row) {
        if (row.style.display === 'none') {
            return;
        }
        var index = row.getAttribute('data-entry-index');
        if (index === '__INDEX__') {
            return;
        }

        var entryType = row.getAttribute('data-entry-type') || 'both';
        var debitAccount = row.querySelector('.accounting-debitaccount');
        var creditAccount = row.querySelector('.accounting-creditaccount');

        // Debit side is visible for 'debit' and 'both' entries.
        if (entryType === 'debit' || entryType === 'both') {
            var hasDebitAccount = debitAccount && debitAccount.value && debitAccount.value !== '';
            if (!hasDebitAccount) {
                showAccountError(debitAccount, 'err_debitaccountrequired');
                errors.push(index);
            }
        }

        // Credit side is visible for 'credit' and 'both' entries.
        if (entryType === 'credit' || entryType === 'both') {
            var hasCreditAccount = creditAccount && creditAccount.value && creditAccount.value !== '';
            if (!hasCreditAccount) {
                showAccountError(creditAccount, 'err_creditaccountrequired');
                errors.push(index);
            }
        }
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
    var errorSpan = document.createElement('span');
    errorSpan.className = 'accounting-account-error text-danger d-block mt-1';
    errorSpan.style.fontSize = '0.875rem';
    errorSpan.textContent = M.util.get_string(stringKey, 'qtype_accounting');
    field.parentNode.appendChild(errorSpan);
}

/**
 * Clear all inline account validation errors.
 */
function clearAccountErrors() {
    var existing = document.querySelectorAll('.accounting-account-error');
    existing.forEach(function (el) {
        el.remove();
    });
}

/**
 * Validate that total debit amounts equal total credit amounts.
 *
 * @return {boolean} True if balanced, false if not.
 */
function validateBalance() {
    var totalDebit = 0;
    var totalCredit = 0;

    var entryRows = document.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function (row) {
        if (row.style.display === 'none') {
            return;
        }
        var index = row.getAttribute('data-entry-index');
        if (index === '__INDEX__') {
            return;
        }

        var debitField = row.querySelector('.accounting-debitamount');
        var creditField = row.querySelector('.accounting-creditamount');

        if (debitField && debitField.value) {
            var parsedDebit = parseFloat(EntryUtils.parseNumber(debitField.value));
            if (!isNaN(parsedDebit)) {
                totalDebit += parsedDebit;
            }
        }
        if (creditField && creditField.value) {
            var parsedCredit = parseFloat(EntryUtils.parseNumber(creditField.value));
            if (!isNaN(parsedCredit)) {
                totalCredit += parsedCredit;
            }
        }
    });

    if (Math.abs(totalDebit - totalCredit) > 0.001) {
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
    var table = document.querySelector('.accounting-edit-table');
    if (!table) {
        return;
    }
    var errorDiv = document.createElement('div');
    errorDiv.id = 'accounting-balance-error';
    errorDiv.className = 'alert alert-danger mt-2';
    errorDiv.setAttribute('role', 'alert');
    errorDiv.textContent = M.util.get_string('err_balancemismatch', 'qtype_accounting');
    table.parentNode.insertBefore(errorDiv, table.nextSibling);
}

/**
 * Clear the balance validation error if present.
 */
function clearBalanceError() {
    var existing = document.getElementById('accounting-balance-error');
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
    const incompleteRow = EntryUtils.findIncompleteRow(document, entryType, ROW_SELECTOR);
    if (incompleteRow) {
        // Complete the existing row by making it 'both'.
        incompleteRow.setAttribute('data-entry-type', 'both');

        // Find the associated weight row.
        const index = incompleteRow.getAttribute('data-entry-index');
        const weightRow = document.querySelector('.accounting-weight-row[data-entry-index="' + index + '"]');

        // Remove hidden-cell classes from both rows.
        EntryUtils.applyEntryTypeVisibility(incompleteRow, 'both');
        EntryUtils.applyWeightRowVisibility(weightRow, 'both');

        // Update states for the completed row.
        updateDebitamountState(index);
        updateWeightStates(index);
        EntryUtils.updatePerLabels(document, ROW_SELECTOR);
        return;
    }

    // No incomplete row to complete, so add a new row.
    const template = document.getElementById('accounting-entry-template');
    const tbody = document.getElementById('accounting-entries-body');

    if (!template || !tbody) {
        Log.error('Template or tbody not found', {template: template, tbody: tbody});
        return;
    }

    // Clone the template content (template.content is a DocumentFragment).
    const clone = template.content.cloneNode(true);

    // Update all __INDEX__ placeholders in the cloned content.
    // We need to update attributes and text content.
    clone.querySelectorAll('[data-index="__INDEX__"]').forEach(function (el) {
        el.setAttribute('data-index', nextEntryIndex);
    });
    clone.querySelectorAll('[data-entry-index="__INDEX__"]').forEach(function (el) {
        el.setAttribute('data-entry-index', nextEntryIndex);
    });
    clone.querySelectorAll('[name*="__INDEX__"]').forEach(function (el) {
        el.name = el.name.replace('__INDEX__', nextEntryIndex);
    });

    // Set the entry type on the entry row.
    const entryRow = clone.querySelector(ROW_SELECTOR);
    const weightRow = clone.querySelector('.accounting-weight-row');
    if (entryRow) {
        entryRow.setAttribute('data-entry-type', entryType);
    }

    // Apply hidden-cell class based on entry type.
    EntryUtils.applyEntryTypeVisibility(entryRow, entryType);
    EntryUtils.applyWeightRowVisibility(weightRow, entryType);

    // Append the cloned rows to the tbody.
    tbody.appendChild(clone);

    // Update dropdowns for the new row (force rebuild to populate new row's selects).
    updateAccountDropdowns(true);
    updateDebitamountState(nextEntryIndex);
    updateWeightStates(nextEntryIndex);

    // Initialize hidden fields for the new index.
    initializeHiddenFieldsForIndex(nextEntryIndex);

    // Update delete button states (enable all since we now have more than one).
    updateDeleteButtonStates();

    // Update Per/an labels.
    EntryUtils.updatePerLabels(document, ROW_SELECTOR);

    nextEntryIndex++;
}

/**
 * Initialize hidden form fields for a given index.
 *
 * @param {number} index The entry index.
 */
function initializeHiddenFieldsForIndex(index) {
    const form = document.querySelector('form.mform') || document.querySelector('form');
    if (!form) {
        return;
    }

    // The hidden fields should already exist (they're pre-created up to index 19).
    // Just sync the display values to them.
    syncDisplayToHidden(index);
}

/**
 * Delete an entry row from the table.
 *
 * @param {string|number} index The entry index to delete.
 */
function deleteEntryRow(index) {
    // Prevent deleting the last remaining entry.
    const allEntryRows = document.querySelectorAll(ROW_SELECTOR);
    if (allEntryRows.length <= 1) {
        return;
    }

    const entryRow = document.querySelector(ROW_SELECTOR + '[data-entry-index="' + index + '"]');
    const weightRow = document.querySelector('.accounting-weight-row[data-entry-index="' + index + '"]');

    if (entryRow) {
        entryRow.remove();
    }
    if (weightRow) {
        weightRow.remove();
    }

    // Clear the hidden fields for this index.
    clearHiddenFieldsForIndex(index);

    // Update Per/an labels (first visible row should have them).
    EntryUtils.updatePerLabels(document, ROW_SELECTOR);

    // Update delete button states (disable if only one entry remains).
    updateDeleteButtonStates();
}

/**
 * Update the enabled/disabled state of all delete buttons.
 * Disable delete buttons when only one entry remains.
 */
function updateDeleteButtonStates() {
    const allEntryRows = document.querySelectorAll(ROW_SELECTOR);
    const deleteButtons = document.querySelectorAll('.accounting-delete-debit, .accounting-delete-credit');
    const isOnlyOne = allEntryRows.length <= 1;

    deleteButtons.forEach(function (button) {
        button.disabled = isOnlyOne;
        if (isOnlyOne) {
            button.classList.add('disabled');
            button.style.opacity = '0.5';
            button.style.cursor = 'not-allowed';
        } else {
            button.classList.remove('disabled');
            button.style.opacity = '';
            button.style.cursor = '';
        }
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
    allFields.forEach(function (field) {
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
    // Use getElementsByName which handles brackets properly.
    const fields = document.getElementsByName(name);
    return fields.length > 0 ? fields[0] : null;
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
    // Sync account selects.
    const debitaccountDisplay = document.querySelector('.accounting-debitaccount[data-index="' + index + '"]');
    const debitaccountHidden = getFieldByName('debitaccount[' + index + ']');
    if (debitaccountDisplay && debitaccountHidden) {
        debitaccountHidden.value = debitaccountDisplay.value;
    }

    const creditaccountDisplay = document.querySelector('.accounting-creditaccount[data-index="' + index + '"]');
    const creditaccountHidden = getFieldByName('creditaccount[' + index + ']');
    if (creditaccountDisplay && creditaccountHidden) {
        creditaccountHidden.value = creditaccountDisplay.value;
    }

    // Sync amount fields - parse German format to plain numbers for hidden fields.
    const debitamountDisplay = document.querySelector('.accounting-debitamount[data-index="' + index + '"]');
    const debitamountHidden = getFieldByName('debitamount[' + index + ']');
    if (debitamountDisplay && debitamountHidden) {
        debitamountHidden.value = EntryUtils.parseNumber(debitamountDisplay.value);
    }

    const creditamountDisplay = document.querySelector('.accounting-creditamount[data-index="' + index + '"]');
    const creditamountHidden = getFieldByName('creditamount[' + index + ']');
    if (creditamountDisplay && creditamountHidden) {
        creditamountHidden.value = EntryUtils.parseNumber(creditamountDisplay.value);
    }

    // Sync weight fields.
    const weightFields = ['debitaccount', 'debitamount', 'creditaccount', 'creditamount'];
    weightFields.forEach(function (field) {
        const displayField = document.querySelector('.accounting-weight[data-index="' + index + '"][data-field="' + field + '"]');
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
    const entryRows = document.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function (row) {
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
 */
function updateAccountDropdowns(forceRebuild) {
    const chartId = chartSelect ? chartSelect.value : '0';
    const accounts = accountsByChart[chartId] || {};

    // Skip if chart hasn't changed (unless forced).
    if (!forceRebuild && lastChartId === chartId) {
        return;
    }
    lastChartId = chartId;

    // Find all debitaccount and creditaccount selects (display fields).
    const debitSelects = document.querySelectorAll('select.accounting-debitaccount');
    const creditSelects = document.querySelectorAll('select.accounting-creditaccount');

    debitSelects.forEach(function (select) {
        const currentValue = select.value;
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
            select.value = currentValue;
        }
    });

    creditSelects.forEach(function (select) {
        const currentValue = select.value;
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
            select.value = currentValue;
        }
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
    const debitSelect = document.querySelector('select.accounting-debitaccount[data-index="' + index + '"]');
    const debitAmount = document.querySelector('input.accounting-debitamount[data-index="' + index + '"]');

    if (debitSelect && debitAmount) {
        const hasAccount = debitSelect.value !== '' && debitSelect.value !== null;
        debitAmount.disabled = !hasAccount;
        debitAmount.style.backgroundColor = hasAccount ? '' : '#e9ecef';

        if (hasAccount) {
            debitAmount.title = '';
        } else {
            Str.get_string('selectDebitAccountFirst', 'qtype_accounting').then(function (str) {
                debitAmount.title = str;
            });
            debitAmount.value = '';
        }
    }
}

/**
 * Update all Debit (debit) amount field states.
 */
function updateAllDebitamountStates() {
    const entryRows = document.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function (row) {
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
    var checkbox = document.getElementById('id_allornothinggrading');
    if (!checkbox) {
        return;
    }

    allOrNothingEnabled = checkbox.checked;

    checkbox.addEventListener('change', function () {
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
    const debitSelect = document.querySelector('select.accounting-debitaccount[data-index="' + index + '"]');
    const creditSelect = document.querySelector('select.accounting-creditaccount[data-index="' + index + '"]');

    const hasDebitAccount = debitSelect && debitSelect.value !== '' && debitSelect.value !== null;
    const hasCreditAccount = creditSelect && creditSelect.value !== '' && creditSelect.value !== null;

    // When all-or-nothing is enabled, all weights are disabled regardless of account state.
    var debitEnabled = hasDebitAccount && !allOrNothingEnabled;
    var creditEnabled = hasCreditAccount && !allOrNothingEnabled;

    // Update debit (debit) weight fields.
    const weightDebitaccount = document.querySelector('.accounting-weight[data-index="' + index + '"][data-field="debitaccount"]');
    const weightDebitamount = document.querySelector('.accounting-weight[data-index="' + index + '"][data-field="debitamount"]');

    if (weightDebitaccount) {
        weightDebitaccount.disabled = !debitEnabled;
        weightDebitaccount.style.opacity = debitEnabled ? '' : '0.5';
        weightDebitaccount.style.cursor = debitEnabled ? '' : 'not-allowed';
    }
    if (weightDebitamount) {
        weightDebitamount.disabled = !debitEnabled;
        weightDebitamount.style.opacity = debitEnabled ? '' : '0.5';
        weightDebitamount.style.cursor = debitEnabled ? '' : 'not-allowed';
    }

    // Update credit (credit) weight fields.
    const weightCreditaccount = document.querySelector('.accounting-weight[data-index="' + index + '"][data-field="creditaccount"]');
    const weightCreditamount = document.querySelector('.accounting-weight[data-index="' + index + '"][data-field="creditamount"]');

    if (weightCreditaccount) {
        weightCreditaccount.disabled = !creditEnabled;
        weightCreditaccount.style.opacity = creditEnabled ? '' : '0.5';
        weightCreditaccount.style.cursor = creditEnabled ? '' : 'not-allowed';
    }
    if (weightCreditamount) {
        weightCreditamount.disabled = !creditEnabled;
        weightCreditamount.style.opacity = creditEnabled ? '' : '0.5';
        weightCreditamount.style.cursor = creditEnabled ? '' : 'not-allowed';
    }
}

/**
 * Update all weight field states.
 */
function updateAllWeightStates() {
    const entryRows = document.querySelectorAll(ROW_SELECTOR);
    entryRows.forEach(function (row) {
        const index = row.getAttribute('data-entry-index');
        if (index !== '__INDEX__') {
            updateWeightStates(index);
        }
    });
}

export {init};
