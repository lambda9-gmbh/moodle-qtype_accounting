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
 * Shared utility functions for Buchungssatz entry table rendering.
 *
 * Used by both question.js (student view) and editform.js (teacher view).
 *
 * @module     qtype_buchungssatz/entry_utils
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str'], function(Str) {

    /** CSS class used to hide cells based on entry type. */
    var HIDDEN_CLASS = 'buchungssatz-hidden-cell';

    /**
     * Column indices for the 7-column entry table.
     * 0=Per label, 1=Soll Account, 2=Soll Amount, 3=Debit Delete/an,
     * 4=Haben Account, 5=Haben Amount, 6=Credit Delete.
     */
    var COL = {
        PER: 0,
        SOLL_ACCOUNT: 1,
        SOLL_AMOUNT: 2,
        DEBIT_DELETE: 3,
        HABEN_ACCOUNT: 4,
        HABEN_AMOUNT: 5,
        CREDIT_DELETE: 6
    };

    /**
     * Apply visibility classes to an entry row based on entry type.
     *
     * Hides the debit or credit side cells by adding/removing the hidden class.
     * Works with both DOM Elements and jQuery objects (unwraps jQuery automatically).
     *
     * @param {Element|jQuery} row The entry row (tr element).
     * @param {string} entryType The entry type: 'debit', 'credit', or 'both'.
     */
    function applyEntryTypeVisibility(row, entryType) {
        // Unwrap jQuery if needed.
        var el = row.jquery ? row[0] : row;
        if (!el) {
            return;
        }
        var cells = el.querySelectorAll('td');

        // Reset all hidden classes.
        for (var i = 0; i < cells.length; i++) {
            cells[i].classList.remove(HIDDEN_CLASS);
        }

        if (entryType === 'debit') {
            // Hide credit (haben) side.
            if (cells[COL.HABEN_ACCOUNT]) {
                cells[COL.HABEN_ACCOUNT].classList.add(HIDDEN_CLASS);
            }
            if (cells[COL.HABEN_AMOUNT]) {
                cells[COL.HABEN_AMOUNT].classList.add(HIDDEN_CLASS);
            }
            if (cells[COL.CREDIT_DELETE]) {
                cells[COL.CREDIT_DELETE].classList.add(HIDDEN_CLASS);
            }
        } else if (entryType === 'credit') {
            // Hide debit (soll) side.
            if (cells[COL.PER]) {
                cells[COL.PER].classList.add(HIDDEN_CLASS);
            }
            if (cells[COL.SOLL_ACCOUNT]) {
                cells[COL.SOLL_ACCOUNT].classList.add(HIDDEN_CLASS);
            }
            if (cells[COL.SOLL_AMOUNT]) {
                cells[COL.SOLL_AMOUNT].classList.add(HIDDEN_CLASS);
            }
            if (cells[COL.DEBIT_DELETE]) {
                cells[COL.DEBIT_DELETE].classList.add(HIDDEN_CLASS);
            }
        }
        // 'both' keeps everything visible (already reset above).
    }

    /**
     * Apply visibility classes to a weight row based on entry type.
     *
     * Weight rows have 7 cells: 0=empty, 1=weight_sollkonto, 2=weight_sollbetrag,
     * 3=empty, 4=weight_habenkonto, 5=weight_habenbetrag, 6=empty.
     *
     * @param {Element|jQuery|null} weightRow The weight row (tr element), or null.
     * @param {string} entryType The entry type: 'debit', 'credit', or 'both'.
     */
    function applyWeightRowVisibility(weightRow, entryType) {
        if (!weightRow) {
            return;
        }
        var el = weightRow.jquery ? weightRow[0] : weightRow;
        if (!el) {
            return;
        }
        var cells = el.querySelectorAll('td');

        // Reset all hidden classes.
        for (var i = 0; i < cells.length; i++) {
            cells[i].classList.remove(HIDDEN_CLASS);
        }

        if (entryType === 'debit') {
            // Hide credit weight cells (4, 5, 6).
            if (cells.length > 5) {
                cells[4].classList.add(HIDDEN_CLASS);
                cells[5].classList.add(HIDDEN_CLASS);
                cells[6].classList.add(HIDDEN_CLASS);
            }
        } else if (entryType === 'credit') {
            // Hide debit weight cells (0, 1, 2, 3).
            if (cells.length > 3) {
                cells[0].classList.add(HIDDEN_CLASS);
                cells[1].classList.add(HIDDEN_CLASS);
                cells[2].classList.add(HIDDEN_CLASS);
                cells[3].classList.add(HIDDEN_CLASS);
            }
        }
    }

    /**
     * Find an incomplete row that can be completed with the given entry type.
     *
     * Searches for visible rows that only have one side filled. If adding 'debit',
     * looks for a 'credit'-only row, and vice versa.
     *
     * @param {Element|jQuery} container The container element to search within.
     * @param {string} entryType The type of entry being added: 'debit' or 'credit'.
     * @param {string} rowSelector The CSS selector for entry rows.
     * @return {Element|null} The incomplete row element, or null if none found.
     */
    function findIncompleteRow(container, entryType, rowSelector) {
        var el = container.jquery ? container[0] : container;
        if (!el) {
            return null;
        }
        var rows = el.querySelectorAll(rowSelector);

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            // Skip hidden rows (student view hides unused rows with display:none).
            if (row.style.display === 'none') {
                continue;
            }
            var rowType = row.getAttribute('data-entry-type');
            if (entryType === 'debit' && rowType === 'credit') {
                return row;
            } else if (entryType === 'credit' && rowType === 'debit') {
                return row;
            }
        }
        return null;
    }

    /**
     * Update "Per" labels so it only appears on the first visible debit entry.
     *
     * @param {Element|jQuery} container The container element.
     * @param {string} rowSelector The CSS selector for entry rows.
     */
    function updatePerLabels(container, rowSelector) {
        var el = container.jquery ? container[0] : container;
        if (!el) {
            return;
        }
        var rows = el.querySelectorAll(rowSelector);

        Str.get_string('per', 'qtype_buchungssatz').then(function(perStr) {
            var firstDebitFound = false;

            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                // Skip hidden rows.
                if (row.style.display === 'none') {
                    continue;
                }
                var entryType = row.getAttribute('data-entry-type') || 'both';
                var perCell = row.querySelector('td:first-child');

                if ((entryType === 'debit' || entryType === 'both') && perCell) {
                    if (!firstDebitFound) {
                        perCell.textContent = perStr;
                        firstDebitFound = true;
                    } else {
                        perCell.textContent = '';
                    }
                }
            }
        });
    }

    /**
     * Clear the visible fields for one side of an entry row.
     *
     * Clears select and input values within the specified side's cells.
     * Does NOT clear hidden form fields (editform handles that separately).
     *
     * @param {Element|jQuery} entryRow The entry row element.
     * @param {string} side The side to clear: 'debit' or 'credit'.
     */
    function clearEntrySideFields(entryRow, side) {
        var el = entryRow.jquery ? entryRow[0] : entryRow;
        if (!el) {
            return;
        }
        var cells = el.querySelectorAll('td');

        if (side === 'debit') {
            // Clear soll fields (cells 1 and 2).
            clearFieldsInCell(cells[COL.SOLL_ACCOUNT]);
            clearFieldsInCell(cells[COL.SOLL_AMOUNT]);
        } else if (side === 'credit') {
            // Clear haben fields (cells 4 and 5).
            clearFieldsInCell(cells[COL.HABEN_ACCOUNT]);
            clearFieldsInCell(cells[COL.HABEN_AMOUNT]);
        }
    }

    /**
     * Clear all select and input values within a table cell.
     *
     * @param {Element|undefined} cell The td element.
     */
    function clearFieldsInCell(cell) {
        if (!cell) {
            return;
        }
        var selects = cell.querySelectorAll('select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].value = '';
        }
        var inputs = cell.querySelectorAll('input');
        for (var j = 0; j < inputs.length; j++) {
            inputs[j].value = '';
        }
    }

    /**
     * Detect the entry type from field values in a row.
     *
     * Checks account selects/inputs in the soll and haben columns to determine
     * whether the row is 'debit', 'credit', or 'both'.
     *
     * @param {Element|jQuery} row The entry row element.
     * @return {string} The detected entry type: 'both', 'debit', or 'credit'.
     */
    function detectEntryType(row) {
        var el = row.jquery ? row[0] : row;
        if (!el) {
            return 'both';
        }
        var cells = el.querySelectorAll('td');
        var sollCell = cells[COL.SOLL_ACCOUNT];
        var habenCell = cells[COL.HABEN_ACCOUNT];

        var sollVal = getFieldValue(sollCell);
        var habenVal = getFieldValue(habenCell);

        if (sollVal && habenVal) {
            return 'both';
        } else if (sollVal) {
            return 'debit';
        } else if (habenVal) {
            return 'credit';
        }
        return 'both';
    }

    /**
     * Get the value of the first select or input within a cell.
     *
     * @param {Element|undefined} cell The td element.
     * @return {string} The field value, or empty string.
     */
    function getFieldValue(cell) {
        if (!cell) {
            return '';
        }
        var field = cell.querySelector('select, input');
        return field ? (field.value || '') : '';
    }

    /**
     * Parse a formatted number string to a plain number string.
     *
     * Handles both German (1.234,56) and US (1,234.56) formats by detecting
     * which separator comes last.
     *
     * @param {string} value The formatted number string.
     * @return {string} The plain number string (e.g., "1234.56"), or empty string.
     */
    function parseNumber(value) {
        if (!value || value.trim() === '') {
            return '';
        }

        var cleaned = value.trim();

        var lastComma = cleaned.lastIndexOf(',');
        var lastDot = cleaned.lastIndexOf('.');

        if (lastComma > lastDot) {
            // German format: comma is decimal separator.
            cleaned = cleaned.replace(/\./g, '');
            cleaned = cleaned.replace(',', '.');
        } else if (lastDot > lastComma) {
            // US format: dot is decimal separator.
            cleaned = cleaned.replace(/,/g, '');
        }

        return cleaned;
    }

    /**
     * Format a number according to locale and decimal settings.
     *
     * @param {string|number} value The number to format.
     * @param {string} locale The locale string: 'de' for German, 'us' for US.
     * @param {number} decimals The number of decimal places.
     * @return {string} The formatted number string, or empty string if invalid.
     */
    function formatNumber(value, locale, decimals) {
        if (value === '' || value === null || value === undefined) {
            return '';
        }

        var plainValue = parseNumber(String(value));
        var num = parseFloat(plainValue);

        if (isNaN(num)) {
            return '';
        }

        var localeStr = (locale === 'us') ? 'en-US' : 'de-DE';
        return num.toLocaleString(localeStr, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    return {
        HIDDEN_CLASS: HIDDEN_CLASS,
        COL: COL,
        applyEntryTypeVisibility: applyEntryTypeVisibility,
        applyWeightRowVisibility: applyWeightRowVisibility,
        findIncompleteRow: findIncompleteRow,
        updatePerLabels: updatePerLabels,
        clearEntrySideFields: clearEntrySideFields,
        detectEntryType: detectEntryType,
        parseNumber: parseNumber,
        formatNumber: formatNumber
    };
});
