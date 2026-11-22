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
 * Hidden form fields of the edit form's entries.
 *
 * The visible controls are display fields; only the hidden fields are submitted.
 *
 * @module     qtype_accounting/editform/fields
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as EntryUtils from 'qtype_accounting/entry_utils';
import {state, getFieldByName, getNumberFormat, forEachEntryIndex} from 'qtype_accounting/editform/state';

/** Names of the hidden fields of an entry, without the index. */
const HIDDEN_FIELDS = ['debitaccount', 'debitamount', 'creditaccount', 'creditamount',
    'weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount'];

/**
 * Sync a single entry's display fields to hidden form fields.
 *
 * @param {string|number} index The entry index.
 */
export function syncDisplayToHidden(index) {
    if (!getFieldByName('debitaccount[' + index + ']')) {
        initializeHiddenFieldsForIndex(index);
        return;
    }
    ['debit', 'credit'].forEach(side => {
        const accountDisplay = state.form.querySelector('.qtype_accounting-' + side + 'account[data-index="' + index + '"]');
        const accountHidden = getFieldByName(side + 'account[' + index + ']');
        if (accountDisplay && accountHidden) {
            accountHidden.value = accountDisplay.value;
        }
        // Hidden amount fields hold plain numbers.
        const amountDisplay = state.form.querySelector('.qtype_accounting-' + side + 'amount[data-index="' + index + '"]');
        const amountHidden = getFieldByName(side + 'amount[' + index + ']');
        if (amountDisplay && amountHidden) {
            amountHidden.value = EntryUtils.parseNumber(amountDisplay.value, getNumberFormat());
        }
    });
    ['debitaccount', 'debitamount', 'creditaccount', 'creditamount'].forEach(field => {
        const displayField = state.form.querySelector(
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
export function syncAllDisplayToHidden() {
    forEachEntryIndex(syncDisplayToHidden);
}

/**
 * Create the hidden form fields for a given index.
 *
 * @param {string|number} index The entry index.
 */
export function initializeHiddenFieldsForIndex(index) {
    HIDDEN_FIELDS.forEach(field => {
        const name = field + '[' + index + ']';
        if (!getFieldByName(name)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = field.startsWith('weight_') ? '1' : '';
            state.form.appendChild(input);
        }
    });
    syncDisplayToHidden(index);
}

/**
 * Clear the hidden form fields for a given index.
 *
 * @param {string|number} index The entry index.
 */
export function clearHiddenFieldsForIndex(index) {
    HIDDEN_FIELDS.forEach(field => {
        const hiddenField = getFieldByName(field + '[' + index + ']');
        if (hiddenField) {
            hiddenField.value = '';
        }
    });
}

/**
 * Clear the hidden account and amount fields for one side of an entry.
 *
 * @param {string|number} index The entry index.
 * @param {string} side The side to clear: 'debit' or 'credit'.
 */
export function clearHiddenFieldsForSide(index, side) {
    [side + 'account', side + 'amount'].forEach(field => {
        const hiddenField = getFieldByName(field + '[' + index + ']');
        if (hiddenField) {
            hiddenField.value = '';
        }
    });
}
