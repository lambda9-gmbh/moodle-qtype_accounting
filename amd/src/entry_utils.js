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
 * Shared utility functions for accounting entry table rendering.
 *
 * Used by both question.js (student view) and editform.js (teacher view).
 *
 * @module     qtype_accounting/entry_utils
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

// CSS class used to hide cells based on entry type.
let HIDDEN_CLASS = 'qtype_accounting-hidden-cell';

/**
 * Column indices for the 7-column entry table.
 * 0=Per label, 1=Debit Account, 2=Debit Amount, 3=Debit Delete/an,
 * 4=Credit Account, 5=Credit Amount, 6=Credit Delete.
 */
let COL = {
    PER: 0,
    DEBIT_ACCOUNT: 1,
    DEBIT_AMOUNT: 2,
    DEBIT_DELETE: 3,
    CREDIT_ACCOUNT: 4,
    CREDIT_AMOUNT: 5,
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
    let el = row.jquery ? row[0] : row;
    if (!el) {
        return;
    }
    let cells = el.querySelectorAll('td');

    // Reset all hidden classes.
    for (let i = 0; i < cells.length; i++) {
        cells[i].classList.remove(HIDDEN_CLASS);
    }

    if (entryType === 'debit') {
        // Hide credit (credit) side.
        if (cells[COL.CREDIT_ACCOUNT]) {
            cells[COL.CREDIT_ACCOUNT].classList.add(HIDDEN_CLASS);
        }
        if (cells[COL.CREDIT_AMOUNT]) {
            cells[COL.CREDIT_AMOUNT].classList.add(HIDDEN_CLASS);
        }
        if (cells[COL.CREDIT_DELETE]) {
            cells[COL.CREDIT_DELETE].classList.add(HIDDEN_CLASS);
        }
    } else if (entryType === 'credit') {
        // Hide debit (debit) side.
        if (cells[COL.PER]) {
            cells[COL.PER].classList.add(HIDDEN_CLASS);
        }
        if (cells[COL.DEBIT_ACCOUNT]) {
            cells[COL.DEBIT_ACCOUNT].classList.add(HIDDEN_CLASS);
        }
        if (cells[COL.DEBIT_AMOUNT]) {
            cells[COL.DEBIT_AMOUNT].classList.add(HIDDEN_CLASS);
        }
        if (cells[COL.DEBIT_DELETE]) {
            cells[COL.DEBIT_DELETE].classList.add(HIDDEN_CLASS);
        }
    }
    // The 'both' type keeps everything visible (already reset above).
}

/**
 * Apply visibility classes to a weight row based on entry type.
 *
 * Weight rows have 7 cells: 0=empty, 1=weight_debitaccount, 2=weight_debitamount,
 * 3=empty, 4=weight_creditaccount, 5=weight_creditamount, 6=empty.
 *
 * @param {Element|jQuery|null} weightRow The weight row (tr element), or null.
 * @param {string} entryType The entry type: 'debit', 'credit', or 'both'.
 */
function applyWeightRowVisibility(weightRow, entryType) {
    if (!weightRow) {
        return;
    }
    let el = weightRow.jquery ? weightRow[0] : weightRow;
    if (!el) {
        return;
    }
    let cells = el.querySelectorAll('td');

    // Reset all hidden classes.
    for (let i = 0; i < cells.length; i++) {
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
    let el = container.jquery ? container[0] : container;
    if (!el) {
        return null;
    }
    let rows = el.querySelectorAll(rowSelector);

    for (let i = 0; i < rows.length; i++) {
        let row = rows[i];
        // Skip hidden rows (student view hides unused rows with display:none).
        if (row.classList.contains('qtype_accounting-deleted-row') || row.style.display === 'none') {
            continue;
        }
        let rowType = row.getAttribute('data-entry-type');
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
    let el = container.jquery ? container[0] : container;
    if (!el) {
        return;
    }
    let rows = el.querySelectorAll(rowSelector);

    // The "Per" column is only shown in the header row, not in data rows.
    for (let i = 0; i < rows.length; i++) {
        let row = rows[i];
        let perCell = row.querySelector('td:first-child');
        if (perCell) {
            perCell.textContent = '';
        }
    }
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
    let el = entryRow.jquery ? entryRow[0] : entryRow;
    if (!el) {
        return;
    }
    let cells = el.querySelectorAll('td');

    if (side === 'debit') {
        // Clear debit fields (cells 1 and 2).
        clearFieldsInCell(cells[COL.DEBIT_ACCOUNT]);
        clearFieldsInCell(cells[COL.DEBIT_AMOUNT]);
    } else if (side === 'credit') {
        // Clear credit fields (cells 4 and 5).
        clearFieldsInCell(cells[COL.CREDIT_ACCOUNT]);
        clearFieldsInCell(cells[COL.CREDIT_AMOUNT]);
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
    let selects = cell.querySelectorAll('select');
    for (let i = 0; i < selects.length; i++) {
        selects[i].value = '';
        // Notify listeners so dependent fields stay in sync.
        $(selects[i]).trigger('change');
    }
    let inputs = cell.querySelectorAll('input');
    for (let j = 0; j < inputs.length; j++) {
        inputs[j].value = '';
    }
}

/**
 * Detect the entry type from field values in a row.
 *
 * Checks account selects/inputs in the debit and credit columns to determine
 * whether the row is 'debit', 'credit', or 'both'.
 *
 * @param {Element|jQuery} row The entry row element.
 * @return {string} The detected entry type: 'both', 'debit', or 'credit'.
 */
function detectEntryType(row) {
    let el = row.jquery ? row[0] : row;
    if (!el) {
        return 'both';
    }
    let cells = el.querySelectorAll('td');
    let debitCell = cells[COL.DEBIT_ACCOUNT];
    let creditCell = cells[COL.CREDIT_ACCOUNT];

    let debitVal = getFieldValue(debitCell);
    let creditVal = getFieldValue(creditCell);
    // Treat "0" as empty (account ID 0 = no account selected).
    if (debitVal === '0') {
        debitVal = '';
    }
    if (creditVal === '0') {
        creditVal = '';
    }

    if (debitVal && creditVal) {
        return 'both';
    } else if (debitVal) {
        return 'debit';
    } else if (creditVal) {
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
    let field = cell.querySelector('select, input');
    return field ? (field.value || '') : '';
}

/**
 * Check whether an entry row is empty (both account fields have no value).
 *
 * @param {Element|jQuery} row The entry row (tr element).
 * @return {boolean} True if both debit and credit account fields are empty.
 */
function isRowEmpty(row) {
    let el = row.jquery ? row[0] : row;
    if (!el) {
        return false;
    }
    let cells = el.querySelectorAll('td');
    let debitVal = getFieldValue(cells[COL.DEBIT_ACCOUNT]);
    let creditVal = getFieldValue(cells[COL.CREDIT_ACCOUNT]);
    // Treat "0" as empty (account ID 0 = no account selected).
    if (debitVal === '0') {
        debitVal = '';
    }
    if (creditVal === '0') {
        creditVal = '';
    }
    return !debitVal && !creditVal;
}

/**
 * Parse a formatted number string to a plain number string.
 *
 * Uses the selected format, including ambiguous inputs such as German 1.000.
 *
 * @param {string} value The formatted number string.
 * @param {string} format The input format, or us for canonical decimal values.
 * @return {string} The plain number string (e.g., "1234.56"), or empty string.
 */
function parseNumber(value, format = 'us') {
    const cleaned = String(value ?? '').trim();
    if (cleaned === '') {
        return '';
    }
    return format === 'de' ? cleaned.replace(/\./g, '').replace(',', '.') : cleaned.replace(/,/g, '');
}

/**
 * Parse decimal text into integer cents, rounding half cents away from zero.
 *
 * BigInt keeps totals exact even when many large database amounts are combined.
 *
 * @param {string} value Decimal input.
 * @param {string} format German or US number format.
 * @return {bigint|null} Integer cents, or null for invalid input.
 */
function parseCents(value, format = 'us') {
    const plain = parseNumber(value, format);
    const match = plain.match(/^([+-]?)(\d*)(?:\.(\d*))?$/);
    if (!match || !/\d/.test(plain)) {
        return null;
    }
    const fraction = (match[3] || '').padEnd(3, '0');
    const cents = BigInt((match[2] || '0') + fraction.slice(0, 2)) + (fraction[2] >= '5' ? 1n : 0n);
    return match[1] === '-' ? -cents : cents;
}

/**
 * Format a monetary amount with grouping and two decimal places.
 *
 * @param {string|number} value The number to format.
 * @param {string} locale The locale string: 'de' for German, 'us' for US.
 * @return {string} The formatted number string, or empty string if invalid.
 */
function formatNumber(value, locale) {
    if (value === '' || value === null || value === undefined) {
        return '';
    }

    const cents = parseCents(value, typeof value === 'number' ? 'us' : locale);
    if (cents === null) {
        return '';
    }
    return formatCents(cents, locale);
}

/**
 * Format integer cents without passing through floating-point amounts.
 *
 * @param {bigint} cents Amount in cents.
 * @param {string} locale German or US number format.
 * @return {string} Localised amount with grouping and two decimal places.
 */
function formatCents(cents, locale) {
    const digits = String(cents < 0n ? -cents : cents).padStart(3, '0');
    const whole = digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, locale === 'us' ? ',' : '.');
    return (cents < 0n ? '-' : '') + whole + (locale === 'us' ? '.' : ',') + digits.slice(-2);
}

export {
    HIDDEN_CLASS, COL, applyEntryTypeVisibility, applyWeightRowVisibility,
    findIncompleteRow, updatePerLabels, clearEntrySideFields, detectEntryType,
    isRowEmpty, parseNumber, parseCents, formatCents, formatNumber
};
