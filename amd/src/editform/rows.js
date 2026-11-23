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
 * Adding and deleting entries in the edit form.
 *
 * Each entry has a table row and a weight row. An entry may hold both sides or only one;
 * each side always keeps at least one entry.
 *
 * @module     qtype_accounting/editform/rows
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Log from 'core/log';
import * as EntryUtils from 'qtype_accounting/entry_utils';
import {state, ROW_SELECTOR} from 'qtype_accounting/editform/state';
import {initializeHiddenFieldsForIndex, clearHiddenFieldsForIndex, clearHiddenFieldsForSide}
    from 'qtype_accounting/editform/fields';
import {updateDebitamountState, updateWeightStates} from 'qtype_accounting/editform/field_states';
import {updateAccountDropdowns} from 'qtype_accounting/editform/accounts';

/**
 * Calculate the next entry index from existing rows.
 */
export function calculateNextEntryIndex() {
    let maxIndex = -1;
    state.form.querySelectorAll(ROW_SELECTOR).forEach(function(row) {
        const index = parseInt(row.getAttribute('data-entry-index'), 10);
        if (!isNaN(index) && index > maxIndex) {
            maxIndex = index;
        }
    });
    state.nextEntryIndex = maxIndex + 1;
}

/**
 * Setup the add entry buttons.
 */
export function setupAddEntryHandler() {
    ['debit', 'credit'].forEach(side => {
        const button = document.getElementById('qtype_accounting-add-' + side + '-entry');
        if (button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                addEntryRow(side);
            });
        }
    });
}

/**
 * Setup the delete buttons of both sides (event delegation).
 */
export function setupDeleteEntryHandler() {
    state.form.addEventListener('click', function(e) {
        for (const side of ['debit', 'credit']) {
            const button = e.target.closest('.qtype_accounting-delete-' + side);
            if (button) {
                e.preventDefault();
                deleteEntrySide(button.getAttribute('data-index'), side);
                return;
            }
        }
    });
}

/**
 * Get the table row and the weight row of an entry.
 *
 * @param {string|number} index The entry index.
 * @return {{entryRow: Element|null, weightRow: Element|null}} The rows.
 */
function getEntryRows(index) {
    return {
        entryRow: state.form.querySelector(ROW_SELECTOR + '[data-entry-index="' + index + '"]'),
        weightRow: state.form.querySelector('.qtype_accounting-weight-row[data-entry-index="' + index + '"]'),
    };
}

/**
 * Delete one side of an entry (debit or credit).
 *
 * @param {string|number} index The entry index.
 * @param {string} side The side to delete: 'debit' or 'credit'.
 */
export function deleteEntrySide(index, side) {
    const {entryRow, weightRow} = getEntryRows(index);
    if (!entryRow || getEntrySideRows(side).length <= 1) {
        return;
    }

    const currentType = entryRow.getAttribute('data-entry-type') || 'both';
    if (currentType === 'both') {
        // The entry keeps its other side.
        const newType = (side === 'debit') ? 'credit' : 'debit';
        entryRow.setAttribute('data-entry-type', newType);
        EntryUtils.applyEntryTypeVisibility(entryRow, newType);
        EntryUtils.applyWeightRowVisibility(weightRow, newType);
        EntryUtils.clearEntrySideFields(entryRow, side);
        clearHiddenFieldsForSide(index, side);
        EntryUtils.updatePerLabels(state.form, ROW_SELECTOR);
        updateDeleteButtonStates();
    } else if (currentType === side) {
        deleteEntryRow(index);
    }
}

/**
 * Add an entry, or complete an entry that only has the other side.
 *
 * @param {string} entryType The type of entry: 'debit', 'credit', or 'both'.
 */
export function addEntryRow(entryType) {
    entryType = entryType || 'both';

    const incompleteRow = EntryUtils.findIncompleteRow(state.form, entryType, ROW_SELECTOR);
    if (incompleteRow) {
        const index = incompleteRow.getAttribute('data-entry-index');
        const {weightRow} = getEntryRows(index);
        incompleteRow.setAttribute('data-entry-type', 'both');
        EntryUtils.applyEntryTypeVisibility(incompleteRow, 'both');
        EntryUtils.applyWeightRowVisibility(weightRow, 'both');
        updateDebitamountState(index);
        updateWeightStates(index);
        EntryUtils.updatePerLabels(state.form, ROW_SELECTOR);
        updateDeleteButtonStates();
        return;
    }

    if (state.form.querySelectorAll(ROW_SELECTOR).length >= state.maxEntries) {
        return;
    }
    const template = document.getElementById('qtype_accounting-entry-template');
    const tbody = document.getElementById('qtype_accounting-entries-body');
    if (!template || !tbody) {
        Log.error('Template or tbody not found', {template: template, tbody: tbody});
        return;
    }

    // Replace the __INDEX__ placeholders of the template rows.
    const index = state.nextEntryIndex;
    const clone = template.content.cloneNode(true);
    clone.querySelectorAll('[data-index="__INDEX__"]').forEach(el => el.setAttribute('data-index', index));
    clone.querySelectorAll('[data-entry-index="__INDEX__"]').forEach(el => el.setAttribute('data-entry-index', index));
    clone.querySelectorAll('[name*="__INDEX__"]').forEach(el => {
        el.name = el.name.replace('__INDEX__', index);
    });
    clone.querySelectorAll('[id*="__INDEX__"]').forEach(el => {
        el.id = el.id.replace('__INDEX__', index);
    });

    const entryRow = clone.querySelector(ROW_SELECTOR);
    const weightRow = clone.querySelector('.qtype_accounting-weight-row');
    if (entryRow) {
        entryRow.setAttribute('data-entry-type', entryType);
    }
    EntryUtils.applyEntryTypeVisibility(entryRow, entryType);
    EntryUtils.applyWeightRowVisibility(weightRow, entryType);
    tbody.appendChild(clone);

    // Populate the new row's account selects.
    updateAccountDropdowns();
    updateDebitamountState(index);
    updateWeightStates(index);
    initializeHiddenFieldsForIndex(index);
    updateDeleteButtonStates();
    EntryUtils.updatePerLabels(state.form, ROW_SELECTOR);
    state.nextEntryIndex++;
}

/**
 * Delete an entry with its hidden fields, unless it is the last one.
 *
 * @param {string|number} index The entry index to delete.
 */
export function deleteEntryRow(index) {
    if (state.form.querySelectorAll(ROW_SELECTOR).length <= 1) {
        return;
    }

    const {entryRow, weightRow} = getEntryRows(index);
    if (entryRow) {
        entryRow.remove();
    }
    if (weightRow) {
        weightRow.remove();
    }

    clearHiddenFieldsForIndex(index);
    state.form.querySelectorAll('input[type="hidden"]').forEach(input => {
        if (input.name.endsWith('[' + index + ']')) {
            input.remove();
        }
    });

    EntryUtils.updatePerLabels(state.form, ROW_SELECTOR);
    updateDeleteButtonStates();
}

/**
 * Get the entry rows containing the given side, independently of row indices.
 *
 * @param {string} side The side to count: 'debit' or 'credit'.
 * @return {Element[]} The rows containing that side.
 */
export function getEntrySideRows(side) {
    return Array.from(state.form.querySelectorAll(ROW_SELECTOR)).filter(row => {
        const entryType = row.getAttribute('data-entry-type') || 'both';
        return entryType === 'both' || entryType === side;
    });
}

/**
 * Update the add buttons for the entry limit and the delete buttons so each side keeps at least one entry.
 */
export function updateDeleteButtonStates() {
    const entryCount = state.form.querySelectorAll(ROW_SELECTOR).length;
    state.form.querySelectorAll('.qtype_accounting-add-debit-entry, .qtype_accounting-add-credit-entry')
        .forEach(button => {
            button.disabled = entryCount >= state.maxEntries;
        });

    ['debit', 'credit'].forEach(side => {
        const isOnlyOne = getEntrySideRows(side).length <= 1;
        state.form.querySelectorAll('.qtype_accounting-delete-' + side).forEach(button => {
            button.disabled = isOnlyOne;
            button.classList.toggle('disabled', isOnlyOne);
        });
    });
}
