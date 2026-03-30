// This file is part of MoFT BuSa - Moodle Question Type Buchungssatz.
//
// MoFT BuSa is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// MoFT BuSa is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with MoFT BuSa.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for the Buchungssatz question edit form.
 *
 * @module     qtype_buchungssatz/editform
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'qtype_buchungssatz/entry_utils'], function($, Str, EntryUtils) {

    // Module-level state
    let accountsByChart = {};
    let lastChartId = null;
    let nextEntryIndex = 0;
    let allOrNothingEnabled = false;

    // DOM element references
    let chartSelect = null;
    let numberFormatSelect = null;
    let initialChartId = '0';

    /** CSS selector for entry rows in the edit form. */
    var ROW_SELECTOR = '.buchungssatz-entry-row';

    /**
     * Initialize the edit form enhancements.
     * Data is read from a script tag to avoid js_call_amd size limits and HTML encoding issues.
     */
    function init() {
        // Read configuration from script tag (preferred) or data attribute (fallback).
        const dataElement = document.getElementById('buchungssatz-editform-data');
        let config = {};
        if (dataElement) {
            try {
                // Script tags use textContent, div elements use data-config attribute.
                const jsonText = dataElement.textContent || dataElement.getAttribute('data-config');
                if (jsonText) {
                    config = JSON.parse(jsonText);
                }
            } catch (e) {
                console.error('Failed to parse editform config', e);
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

        // Initial setup - populate dropdowns based on selected chart
        if (chartSelect && initialChartId && (!chartSelect.value || chartSelect.value === '0')) {
            chartSelect.value = initialChartId;
        }
        lastChartId = chartSelect ? chartSelect.value : '0';

        // Calculate next entry index from existing rows.
        calculateNextEntryIndex();

        // Setup event handlers.
        setupChartChangeHandler();
        setupNumberFormatChangeHandler();
        setupSollkontoChangeHandler();
        setupAddEntryHandler();
        setupDeleteEntryHandler();
        setupFormSubmitHandler();
        // Don't rebuild dropdowns on init - PHP already populated them correctly.
        // Only update sollbetrag states (disable if no debit account selected).
        updateAllSollbetragStates();

        // Setup all-or-nothing grading handler (must run before updateAllWeightStates).
        setupAllOrNothingHandler();

        // Update weight field states (disable if no account selected or all-or-nothing is active).
        updateAllWeightStates();

        // Update delete button states (disable if only one entry).
        updateDeleteButtonStates();

        // Sync display fields to hidden fields for any existing entries.
        syncAllDisplayToHidden();

        // Initialize Bootstrap popovers (for weight tooltip).
        $('[data-toggle="popover"]').popover();
    }

    /**
     * Calculate the next entry index from existing rows.
     */
    function calculateNextEntryIndex() {
        const existingRows = document.querySelectorAll(ROW_SELECTOR);
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
                lastChartId = null; // Reset to force rebuild
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
            numberFormatSelect.addEventListener('change', function() {
                var fmt = numberFormatSelect.value || 'de';
                var placeholder = (fmt === 'us') ? '0.00' : '0,00';

                // Reformat all amount fields.
                var amountFields = document.querySelectorAll('.buchungssatz-sollbetrag, .buchungssatz-habenbetrag');
                amountFields.forEach(function(field) {
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
     * Setup event delegation for sollkonto and habenkonto changes.
     */
    function setupSollkontoChangeHandler() {
        document.addEventListener('change', function(e) {
            // Guard against events where target doesn't have classList.
            if (!e.target || !e.target.classList) {
                return;
            }
            if (e.target.classList.contains('buchungssatz-sollkonto')) {
                const index = e.target.getAttribute('data-index');
                updateSollbetragState(index);
                updateWeightStates(index);
                syncDisplayToHidden(index);
            }
            // Handle habenkonto changes to update weight states.
            if (e.target.classList.contains('buchungssatz-habenkonto')) {
                const index = e.target.getAttribute('data-index');
                updateWeightStates(index);
                syncDisplayToHidden(index);
            }
            // Also sync weight fields on change.
            if (e.target.classList.contains('buchungssatz-weight')) {
                const index = e.target.getAttribute('data-index');
                syncDisplayToHidden(index);
            }
        });

        // Sync amount fields on input (not just change).
        document.addEventListener('input', function(e) {
            // Guard against events where target doesn't have classList.
            if (!e.target || !e.target.classList) {
                return;
            }
            if (e.target.classList.contains('buchungssatz-sollbetrag') ||
                e.target.classList.contains('buchungssatz-habenbetrag') ||
                e.target.classList.contains('buchungssatz-weight')) {
                const index = e.target.getAttribute('data-index');
                syncDisplayToHidden(index);
            }
        });

        // Format amount fields on blur (when user leaves the field).
        document.addEventListener('blur', function(e) {
            // Guard against events where target doesn't have classList.
            if (!e.target || !e.target.classList) {
                return;
            }
            if (e.target.classList.contains('buchungssatz-sollbetrag') ||
                e.target.classList.contains('buchungssatz-habenbetrag')) {
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
        const addDebitButton = document.getElementById('buchungssatz-add-debit-entry');
        const addCreditButton = document.getElementById('buchungssatz-add-credit-entry');

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
        document.addEventListener('click', function(e) {
            // Handle delete debit button.
            const deleteDebitBtn = e.target.closest('.buchungssatz-delete-debit');
            if (deleteDebitBtn) {
                e.preventDefault();
                const index = deleteDebitBtn.getAttribute('data-index');
                deleteEntrySide(index, 'debit');
                return;
            }

            // Handle delete credit button.
            const deleteCreditBtn = e.target.closest('.buchungssatz-delete-credit');
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
        const weightRow = document.querySelector('.buchungssatz-weight-row[data-entry-index="' + index + '"]');

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
            var sollkontoHidden = getFieldByName('sollkonto[' + index + ']');
            var sollbetragHidden = getFieldByName('sollbetrag[' + index + ']');
            if (sollkontoHidden) {
                sollkontoHidden.value = '';
            }
            if (sollbetragHidden) {
                sollbetragHidden.value = '';
            }
        } else if (side === 'credit') {
            var habenkontoHidden = getFieldByName('habenkonto[' + index + ']');
            var habenbetragHidden = getFieldByName('habenbetrag[' + index + ']');
            if (habenkontoHidden) {
                habenkontoHidden.value = '';
            }
            if (habenbetragHidden) {
                habenbetragHidden.value = '';
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
            form.addEventListener('submit', function(e) {
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
        entryRows.forEach(function(row) {
            if (row.style.display === 'none') {
                return;
            }
            var index = row.getAttribute('data-entry-index');
            if (index === '__INDEX__') {
                return;
            }

            var entryType = row.getAttribute('data-entry-type') || 'both';
            var sollKonto = row.querySelector('.buchungssatz-sollkonto');
            var habenKonto = row.querySelector('.buchungssatz-habenkonto');

            // Debit side is visible for 'debit' and 'both' entries.
            if (entryType === 'debit' || entryType === 'both') {
                var hasSollKonto = sollKonto && sollKonto.value && sollKonto.value !== '';
                if (!hasSollKonto) {
                    showAccountError(sollKonto, 'err_sollkontorequired');
                    errors.push(index);
                }
            }

            // Credit side is visible for 'credit' and 'both' entries.
            if (entryType === 'credit' || entryType === 'both') {
                var hasHabenKonto = habenKonto && habenKonto.value && habenKonto.value !== '';
                if (!hasHabenKonto) {
                    showAccountError(habenKonto, 'err_habenkontorequired');
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
        errorSpan.className = 'buchungssatz-account-error text-danger d-block mt-1';
        errorSpan.style.fontSize = '0.875rem';
        errorSpan.textContent = M.util.get_string(stringKey, 'qtype_buchungssatz');
        field.parentNode.appendChild(errorSpan);
    }

    /**
     * Clear all inline account validation errors.
     */
    function clearAccountErrors() {
        var existing = document.querySelectorAll('.buchungssatz-account-error');
        existing.forEach(function(el) {
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
        entryRows.forEach(function(row) {
            if (row.style.display === 'none') {
                return;
            }
            var index = row.getAttribute('data-entry-index');
            if (index === '__INDEX__') {
                return;
            }

            var sollField = row.querySelector('.buchungssatz-sollbetrag');
            var habenField = row.querySelector('.buchungssatz-habenbetrag');

            if (sollField && sollField.value) {
                var parsedSoll = parseFloat(EntryUtils.parseNumber(sollField.value));
                if (!isNaN(parsedSoll)) {
                    totalDebit += parsedSoll;
                }
            }
            if (habenField && habenField.value) {
                var parsedHaben = parseFloat(EntryUtils.parseNumber(habenField.value));
                if (!isNaN(parsedHaben)) {
                    totalCredit += parsedHaben;
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
        var table = document.querySelector('.buchungssatz-edit-table');
        if (!table) {
            return;
        }
        var errorDiv = document.createElement('div');
        errorDiv.id = 'buchungssatz-balance-error';
        errorDiv.className = 'alert alert-danger mt-2';
        errorDiv.setAttribute('role', 'alert');
        errorDiv.textContent = M.util.get_string('err_balancemismatch', 'qtype_buchungssatz');
        table.parentNode.insertBefore(errorDiv, table.nextSibling);
    }

    /**
     * Clear the balance validation error if present.
     */
    function clearBalanceError() {
        var existing = document.getElementById('buchungssatz-balance-error');
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
            const weightRow = document.querySelector('.buchungssatz-weight-row[data-entry-index="' + index + '"]');

            // Remove hidden-cell classes from both rows.
            EntryUtils.applyEntryTypeVisibility(incompleteRow, 'both');
            EntryUtils.applyWeightRowVisibility(weightRow, 'both');

            // Update states for the completed row.
            updateSollbetragState(index);
            updateWeightStates(index);
            EntryUtils.updatePerLabels(document, ROW_SELECTOR);
            return;
        }

        // No incomplete row to complete, so add a new row.
        const template = document.getElementById('buchungssatz-entry-template');
        const tbody = document.getElementById('buchungssatz-entries-body');

        if (!template || !tbody) {
            console.error('Template or tbody not found', {template: template, tbody: tbody});
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

        // Set the entry type on the entry row.
        const entryRow = clone.querySelector(ROW_SELECTOR);
        const weightRow = clone.querySelector('.buchungssatz-weight-row');
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
        updateSollbetragState(nextEntryIndex);
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
        const weightRow = document.querySelector('.buchungssatz-weight-row[data-entry-index="' + index + '"]');

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
        const deleteButtons = document.querySelectorAll('.buchungssatz-delete-debit, .buchungssatz-delete-credit');
        const isOnlyOne = allEntryRows.length <= 1;

        deleteButtons.forEach(function(button) {
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
        const allFields = ['sollkonto', 'sollbetrag', 'habenkonto', 'habenbetrag',
                        'weight_sollkonto', 'weight_sollbetrag', 'weight_habenkonto', 'weight_habenbetrag'];
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
     * @param {string} name The field name (e.g., "sollbetrag[0]").
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
        const sollkontoDisplay = document.querySelector('.buchungssatz-sollkonto[data-index="' + index + '"]');
        const sollkontoHidden = getFieldByName('sollkonto[' + index + ']');
        if (sollkontoDisplay && sollkontoHidden) {
            sollkontoHidden.value = sollkontoDisplay.value;
        }

        const habenkontoDisplay = document.querySelector('.buchungssatz-habenkonto[data-index="' + index + '"]');
        const habenkontoHidden = getFieldByName('habenkonto[' + index + ']');
        if (habenkontoDisplay && habenkontoHidden) {
            habenkontoHidden.value = habenkontoDisplay.value;
        }

        // Sync amount fields - parse German format to plain numbers for hidden fields.
        const sollbetragDisplay = document.querySelector('.buchungssatz-sollbetrag[data-index="' + index + '"]');
        const sollbetragHidden = getFieldByName('sollbetrag[' + index + ']');
        if (sollbetragDisplay && sollbetragHidden) {
            sollbetragHidden.value = EntryUtils.parseNumber(sollbetragDisplay.value);
        }

        const habenbetragDisplay = document.querySelector('.buchungssatz-habenbetrag[data-index="' + index + '"]');
        const habenbetragHidden = getFieldByName('habenbetrag[' + index + ']');
        if (habenbetragDisplay && habenbetragHidden) {
            habenbetragHidden.value = EntryUtils.parseNumber(habenbetragDisplay.value);
        }

        // Sync weight fields.
        const weightFields = ['sollkonto', 'sollbetrag', 'habenkonto', 'habenbetrag'];
        weightFields.forEach(function(field) {
            const displayField = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="' + field + '"]');
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
     */
    function updateAccountDropdowns(forceRebuild) {
        const chartId = chartSelect ? chartSelect.value : '0';
        const accounts = accountsByChart[chartId] || {};

        // Skip if chart hasn't changed (unless forced).
        if (!forceRebuild && lastChartId === chartId) {
            return;
        }
        lastChartId = chartId;

        // Find all sollkonto and habenkonto selects (display fields).
        const sollSelects = document.querySelectorAll('select.buchungssatz-sollkonto');
        const habenSelects = document.querySelectorAll('select.buchungssatz-habenkonto');

        sollSelects.forEach(function(select) {
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

        habenSelects.forEach(function(select) {
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

        updateAllSollbetragStates();
        updateAllWeightStates();
    }

    /**
     * Update the disabled state of a Soll (debit) amount field.
     *
     * @param {string|number} index The entry index.
     */
    function updateSollbetragState(index) {
        const sollSelect = document.querySelector('select.buchungssatz-sollkonto[data-index="' + index + '"]');
        const sollBetrag = document.querySelector('input.buchungssatz-sollbetrag[data-index="' + index + '"]');

        if (sollSelect && sollBetrag) {
            const hasAccount = sollSelect.value !== '' && sollSelect.value !== null;
            sollBetrag.disabled = !hasAccount;
            sollBetrag.style.backgroundColor = hasAccount ? '' : '#e9ecef';

            if (hasAccount) {
                sollBetrag.title = '';
            } else {
                Str.get_string('selectDebitAccountFirst', 'qtype_buchungssatz').then(function(str) {
                    sollBetrag.title = str;
                });
                sollBetrag.value = '';
            }
        }
    }

    /**
     * Update all Soll (debit) amount field states.
     */
    function updateAllSollbetragStates() {
        const entryRows = document.querySelectorAll(ROW_SELECTOR);
        entryRows.forEach(function(row) {
            const index = row.getAttribute('data-entry-index');
            if (index !== '__INDEX__') {
                updateSollbetragState(index);
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

        checkbox.addEventListener('change', function() {
            allOrNothingEnabled = checkbox.checked;
            updateAllWeightStates();
        });
    }

    /**
     * Update the disabled state of weight selectors based on account selection.
     * Disables sollkonto/sollbetrag weights when no debit account is selected.
     * Disables habenkonto/habenbetrag weights when no credit account is selected.
     * Also disables all weights when all-or-nothing grading is enabled.
     *
     * @param {string|number} index The entry index.
     */
    function updateWeightStates(index) {
        const sollSelect = document.querySelector('select.buchungssatz-sollkonto[data-index="' + index + '"]');
        const habenSelect = document.querySelector('select.buchungssatz-habenkonto[data-index="' + index + '"]');

        const hasSollAccount = sollSelect && sollSelect.value !== '' && sollSelect.value !== null;
        const hasHabenAccount = habenSelect && habenSelect.value !== '' && habenSelect.value !== null;

        // When all-or-nothing is enabled, all weights are disabled regardless of account state.
        var sollEnabled = hasSollAccount && !allOrNothingEnabled;
        var habenEnabled = hasHabenAccount && !allOrNothingEnabled;

        // Update soll (debit) weight fields.
        const weightSollkonto = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="sollkonto"]');
        const weightSollbetrag = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="sollbetrag"]');

        if (weightSollkonto) {
            weightSollkonto.disabled = !sollEnabled;
            weightSollkonto.style.opacity = sollEnabled ? '' : '0.5';
            weightSollkonto.style.cursor = sollEnabled ? '' : 'not-allowed';
        }
        if (weightSollbetrag) {
            weightSollbetrag.disabled = !sollEnabled;
            weightSollbetrag.style.opacity = sollEnabled ? '' : '0.5';
            weightSollbetrag.style.cursor = sollEnabled ? '' : 'not-allowed';
        }

        // Update haben (credit) weight fields.
        const weightHabenkonto = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="habenkonto"]');
        const weightHabenbetrag = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="habenbetrag"]');

        if (weightHabenkonto) {
            weightHabenkonto.disabled = !habenEnabled;
            weightHabenkonto.style.opacity = habenEnabled ? '' : '0.5';
            weightHabenkonto.style.cursor = habenEnabled ? '' : 'not-allowed';
        }
        if (weightHabenbetrag) {
            weightHabenbetrag.disabled = !habenEnabled;
            weightHabenbetrag.style.opacity = habenEnabled ? '' : '0.5';
            weightHabenbetrag.style.cursor = habenEnabled ? '' : 'not-allowed';
        }
    }

    /**
     * Update all weight field states.
     */
    function updateAllWeightStates() {
        const entryRows = document.querySelectorAll(ROW_SELECTOR);
        entryRows.forEach(function(row) {
            const index = row.getAttribute('data-entry-index');
            if (index !== '__INDEX__') {
                updateWeightStates(index);
            }
        });
    }

    return {
        init: init
    };
});
