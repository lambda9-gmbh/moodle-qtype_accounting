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

define(['jquery', 'core/str', 'core/config'], function($, Str, Config) {

    // Module-level state
    let accountsByChart = {};
    let lastChartId = null;
    let chartManagementOpened = false;
    let nextEntryIndex = 0;

    // DOM element references
    let chartSelect = null;
    let initialChartId = '0';

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

        // Debug logging to help identify issues.
        console.log('Buchungssatz: Init started');
        console.log('Buchungssatz: dataElement found =', !!dataElement);
        console.log('Buchungssatz: chartSelect found =', !!chartSelect);
        console.log('Buchungssatz: accountsByChart keys =', Object.keys(accountsByChart));
        console.log('Buchungssatz: initialChartId =', initialChartId);

        if (!chartSelect) {
            console.warn('Buchungssatz: Chart select element not found');
        }
        if (!dataElement) {
            console.warn('Buchungssatz: Data element not found');
        }
        if (Object.keys(accountsByChart).length === 0) {
            console.warn('Buchungssatz: No accounts data loaded');
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
        setupSollkontoChangeHandler();
        setupAddEntryHandler();
        setupDeleteEntryHandler();
        setupFormSubmitHandler();
        setupChartManagementRefresh();

        // Don't rebuild dropdowns on init - PHP already populated them correctly.
        // Only update sollbetrag states (disable if no debit account selected).
        updateAllSollbetragStates();

        // Update weight field states (disable if no account selected).
        updateAllWeightStates();

        // Update delete button states (disable if only one entry).
        updateDeleteButtonStates();

        // Sync display fields to hidden fields for any existing entries.
        syncAllDisplayToHidden();
    }

    /**
     * Calculate the next entry index from existing rows.
     */
    function calculateNextEntryIndex() {
        const existingRows = document.querySelectorAll('.buchungssatz-entry-row');
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
                console.log('Buchungssatz: Chart changed to', chartSelect.value);
                console.log('Buchungssatz: Available chart IDs', Object.keys(accountsByChart));
                lastChartId = null; // Reset to force rebuild
                updateAccountDropdowns(true);
            });
        } else {
            console.warn('Buchungssatz: Cannot setup chart change handler - chartSelect is null');
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
                // Format the display value in German format.
                const formatted = formatGermanNumber(e.target.value);
                e.target.value = formatted;
                // Also sync to hidden field (with plain number).
                const index = e.target.getAttribute('data-index');
                syncDisplayToHidden(index);
            }
        }, true); // Use capture to ensure we get the event before it bubbles.
    }

    /**
     * Setup add entry button handler.
     */
    function setupAddEntryHandler() {
        const addButton = document.getElementById('buchungssatz-add-entry');
        if (addButton) {
            addButton.addEventListener('click', function(e) {
                e.preventDefault();
                addEntryRow();
            });
        }
    }

    /**
     * Setup delete entry button handler (event delegation).
     */
    function setupDeleteEntryHandler() {
        document.addEventListener('click', function(e) {
            const deleteBtn = e.target.closest('.buchungssatz-delete-entry');
            if (deleteBtn) {
                e.preventDefault();
                const index = deleteBtn.getAttribute('data-index');
                deleteEntryRow(index);
            }
        });
    }

    /**
     * Setup form submit handler to sync all display fields to hidden fields.
     */
    function setupFormSubmitHandler() {
        const form = document.querySelector('form.mform') || document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                syncAllDisplayToHidden();
            });
        }
    }

    /**
     * Add a new entry row to the table.
     */
    function addEntryRow() {
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

        // Append the cloned rows to the tbody.
        tbody.appendChild(clone);

        // Update dropdowns for the new row.
        updateAccountDropdowns(false);
        updateSollbetragState(nextEntryIndex);
        updateWeightStates(nextEntryIndex);

        // Initialize hidden fields for the new index.
        initializeHiddenFieldsForIndex(nextEntryIndex);

        // Update delete button states (enable all since we now have more than one).
        updateDeleteButtonStates();

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
        const allEntryRows = document.querySelectorAll('.buchungssatz-entry-row');
        if (allEntryRows.length <= 1) {
            return;
        }

        const entryRow = document.querySelector('.buchungssatz-entry-row[data-entry-index="' + index + '"]');
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
        updatePerAnLabels();

        // Update delete button states (disable if only one entry remains).
        updateDeleteButtonStates();
    }

    /**
     * Update the enabled/disabled state of all delete buttons.
     * Disable delete buttons when only one entry remains.
     */
    function updateDeleteButtonStates() {
        const allEntryRows = document.querySelectorAll('.buchungssatz-entry-row');
        const deleteButtons = document.querySelectorAll('.buchungssatz-delete-entry');
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
     * Update Per/an labels so they only appear on the first visible row.
     */
    function updatePerAnLabels() {
        const entryRows = document.querySelectorAll('.buchungssatz-entry-row');
        let isFirst = true;

        Str.get_strings([
            {key: 'per', component: 'qtype_buchungssatz'},
            {key: 'an', component: 'qtype_buchungssatz'}
        ]).then(function(strings) {
            const perStr = strings[0];
            const anStr = strings[1];

            entryRows.forEach(function(row) {
                const perCell = row.querySelector('td:first-child');
                const anCell = row.querySelector('td:nth-child(4)');

                if (perCell) {
                    perCell.textContent = isFirst ? perStr : '';
                }
                if (anCell) {
                    anCell.textContent = isFirst ? anStr : '';
                }

                isFirst = false;
            });
        });
    }

    /**
     * Parse a German formatted number (1.234,56) to a plain number.
     *
     * @param {string} value The German formatted number string.
     * @return {string} The plain number string (e.g., "1234.56").
     */
    function parseGermanNumber(value) {
        if (!value || value.trim() === '') {
            return '';
        }
        // Remove thousand separators (dots) and replace decimal comma with dot.
        let cleaned = value.trim();
        cleaned = cleaned.replace(/\./g, ''); // Remove thousand separators.
        cleaned = cleaned.replace(',', '.'); // Replace decimal comma with dot.
        return cleaned;
    }

    /**
     * Format a number in German format (1.234,56) with 2 decimal places.
     *
     * @param {string|number} value The number to format.
     * @return {string} The formatted number string.
     */
    function formatGermanNumber(value) {
        if (value === '' || value === null || value === undefined) {
            return '';
        }

        // Parse to float first (handle both German and plain formats).
        let num;
        if (typeof value === 'string') {
            // Try to parse as German format first.
            let cleaned = value.trim();
            if (cleaned.includes(',')) {
                // Likely German format.
                cleaned = cleaned.replace(/\./g, '');
                cleaned = cleaned.replace(',', '.');
            }
            num = parseFloat(cleaned);
        } else {
            num = parseFloat(value);
        }

        if (isNaN(num)) {
            return '';
        }

        // Format with 2 decimal places in German format.
        // toLocaleString with de-DE gives us the correct format.
        return num.toLocaleString('de-DE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
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
            sollbetragHidden.value = parseGermanNumber(sollbetragDisplay.value);
        }

        const habenbetragDisplay = document.querySelector('.buchungssatz-habenbetrag[data-index="' + index + '"]');
        const habenbetragHidden = getFieldByName('habenbetrag[' + index + ']');
        if (habenbetragDisplay && habenbetragHidden) {
            habenbetragHidden.value = parseGermanNumber(habenbetragDisplay.value);
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
        const entryRows = document.querySelectorAll('.buchungssatz-entry-row');
        entryRows.forEach(function(row) {
            const index = row.getAttribute('data-entry-index');
            if (index !== '__INDEX__') {
                syncDisplayToHidden(index);
            }
        });
    }

    /**
     * Setup auto-refresh when returning from chart management.
     */
    function setupChartManagementRefresh() {
        const manageChartsLink = document.getElementById('buchungssatz-manage-charts-link');

        if (manageChartsLink) {
            manageChartsLink.addEventListener('click', function() {
                chartManagementOpened = true;
            });
        }

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible' && chartManagementOpened) {
                chartManagementOpened = false;
                refreshAccountsFromServer();
            }
        });

        window.addEventListener('focus', function() {
            if (chartManagementOpened) {
                chartManagementOpened = false;
                refreshAccountsFromServer();
            }
        });
    }

    /**
     * Refresh accounts from server via AJAX.
     */
    function refreshAccountsFromServer() {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', Config.wwwroot + '/question/type/buchungssatz/ajax/get_accounts.php?sesskey=' + Config.sesskey, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    accountsByChart = JSON.parse(xhr.responseText);
                    updateAccountDropdowns(true);
                } catch (e) {
                    console.error('Failed to parse accounts response', e);
                }
            }
        };
        xhr.send();
    }

    /**
     * Update account dropdowns based on selected chart.
     *
     * @param {boolean} forceRebuild Whether to force rebuild even if chart hasn't changed.
     */
    function updateAccountDropdowns(forceRebuild) {
        const chartId = chartSelect ? chartSelect.value : '0';
        const accounts = accountsByChart[chartId] || {};

        console.log('Buchungssatz: updateAccountDropdowns called');
        console.log('Buchungssatz: chartId =', chartId, 'type:', typeof chartId);
        console.log('Buchungssatz: accounts for this chart =', accounts);
        console.log('Buchungssatz: number of accounts =', Object.keys(accounts).length);

        // Skip if chart hasn't changed (unless forced).
        if (!forceRebuild && lastChartId === chartId) {
            console.log('Buchungssatz: Skipping update - chart unchanged');
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
            for (const accountNumber in accounts) {
                const option = document.createElement('option');
                option.value = accountNumber;
                option.text = accounts[accountNumber];
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
            for (const accountNumber in accounts) {
                const option = document.createElement('option');
                option.value = accountNumber;
                option.text = accounts[accountNumber];
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
        const entryRows = document.querySelectorAll('.buchungssatz-entry-row');
        entryRows.forEach(function(row) {
            const index = row.getAttribute('data-entry-index');
            if (index !== '__INDEX__') {
                updateSollbetragState(index);
            }
        });
    }

    /**
     * Update the disabled state of weight selectors based on account selection.
     * Disables sollkonto/sollbetrag weights when no debit account is selected.
     * Disables habenkonto/habenbetrag weights when no credit account is selected.
     *
     * @param {string|number} index The entry index.
     */
    function updateWeightStates(index) {
        const sollSelect = document.querySelector('select.buchungssatz-sollkonto[data-index="' + index + '"]');
        const habenSelect = document.querySelector('select.buchungssatz-habenkonto[data-index="' + index + '"]');

        const hasSollAccount = sollSelect && sollSelect.value !== '' && sollSelect.value !== null;
        const hasHabenAccount = habenSelect && habenSelect.value !== '' && habenSelect.value !== null;

        // Update soll (debit) weight fields.
        const weightSollkonto = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="sollkonto"]');
        const weightSollbetrag = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="sollbetrag"]');

        if (weightSollkonto) {
            weightSollkonto.disabled = !hasSollAccount;
            weightSollkonto.style.opacity = hasSollAccount ? '' : '0.5';
            weightSollkonto.style.cursor = hasSollAccount ? '' : 'not-allowed';
        }
        if (weightSollbetrag) {
            weightSollbetrag.disabled = !hasSollAccount;
            weightSollbetrag.style.opacity = hasSollAccount ? '' : '0.5';
            weightSollbetrag.style.cursor = hasSollAccount ? '' : 'not-allowed';
        }

        // Update haben (credit) weight fields.
        const weightHabenkonto = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="habenkonto"]');
        const weightHabenbetrag = document.querySelector('.buchungssatz-weight[data-index="' + index + '"][data-field="habenbetrag"]');

        if (weightHabenkonto) {
            weightHabenkonto.disabled = !hasHabenAccount;
            weightHabenkonto.style.opacity = hasHabenAccount ? '' : '0.5';
            weightHabenkonto.style.cursor = hasHabenAccount ? '' : 'not-allowed';
        }
        if (weightHabenbetrag) {
            weightHabenbetrag.disabled = !hasHabenAccount;
            weightHabenbetrag.style.opacity = hasHabenAccount ? '' : '0.5';
            weightHabenbetrag.style.cursor = hasHabenAccount ? '' : 'not-allowed';
        }
    }

    /**
     * Update all weight field states.
     */
    function updateAllWeightStates() {
        const entryRows = document.querySelectorAll('.buchungssatz-entry-row');
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
