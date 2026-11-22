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
 * Shared state of the Accounting Entry question edit form.
 *
 * @module     qtype_accounting/editform/state
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** CSS selector for entry rows in the edit form. */
export const ROW_SELECTOR = '.qtype_accounting-entry-row';

/** The form and its controls, set by qtype_accounting/editform on init. */
export const state = {
    form: null,
    chartSelect: null,
    numberFormatSelect: null,
    accountsByChart: {},
    lastChartId: null,
    nextEntryIndex: 0,
    maxEntries: 100,
    allOrNothingEnabled: false,
    previousNumberFormat: 'de',
    unavailableMessage: '',
};

/**
 * Get a form field by name, handling array-style names properly.
 *
 * @param {string} name The field name (e.g., "debitamount[0]").
 * @return {Element|null} The form field element or null.
 */
export function getFieldByName(name) {
    return Array.from(state.form.elements).find(field => field.name === name) || null;
}

/**
 * Get the currently selected number format from the form.
 *
 * @return {string} The number format: 'de' or 'us'.
 */
export function getNumberFormat() {
    return state.numberFormatSelect ? state.numberFormatSelect.value || 'de' : 'de';
}

/**
 * Call a function for the index of every entry row, skipping the row template.
 *
 * @param {Function} callback Called with the entry index.
 */
export function forEachEntryIndex(callback) {
    state.form.querySelectorAll(ROW_SELECTOR).forEach(row => {
        const index = row.getAttribute('data-entry-index');
        if (index !== '__INDEX__') {
            callback(index);
        }
    });
}
