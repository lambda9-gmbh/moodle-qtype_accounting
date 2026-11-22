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
 * Enabled state of the amount and weight controls of the edit form's entries.
 *
 * @module     qtype_accounting/editform/field_states
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';
import Log from 'core/log';
import {state, forEachEntryIndex} from 'qtype_accounting/editform/state';

/**
 * Disable the amount fields of an entry whose side has no account selected.
 *
 * @param {string|number} index The entry index.
 */
export function updateDebitamountState(index) {
    ['debit', 'credit'].forEach(side => {
        const select = state.form.querySelector('.qtype_accounting-' + side + 'account[data-index="' + index + '"]');
        const amount = state.form.querySelector('.qtype_accounting-' + side + 'amount[data-index="' + index + '"]');
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
 * Update the amount field states of all entries.
 */
export function updateAllDebitamountStates() {
    forEachEntryIndex(updateDebitamountState);
}

/**
 * Follow the all-or-nothing grading checkbox, which disables all weights.
 */
export function setupAllOrNothingHandler() {
    const checkbox = document.getElementById('id_allornothinggrading');
    if (!checkbox) {
        return;
    }
    state.allOrNothingEnabled = checkbox.checked;
    checkbox.addEventListener('change', function() {
        state.allOrNothingEnabled = checkbox.checked;
        updateAllWeightStates();
    });
}

/**
 * Disable the weights of a side without an account, or all weights under all-or-nothing grading.
 *
 * @param {string|number} index The entry index.
 */
export function updateWeightStates(index) {
    ['debit', 'credit'].forEach(side => {
        const select = state.form.querySelector('select.qtype_accounting-' + side + 'account[data-index="' + index + '"]');
        const enabled = select && select.value !== '' && select.value !== null && !state.allOrNothingEnabled;
        [side + 'account', side + 'amount'].forEach(field => {
            const weight = state.form.querySelector(
                '.qtype_accounting-weight[data-index="' + index + '"][data-field="' + field + '"]'
            );
            if (weight) {
                weight.disabled = !enabled;
            }
        });
    });
}

/**
 * Update the weight field states of all entries.
 */
export function updateAllWeightStates() {
    forEachEntryIndex(updateWeightStates);
}
