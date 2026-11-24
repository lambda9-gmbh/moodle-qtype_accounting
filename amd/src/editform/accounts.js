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
 * The question's account list and the account options of the edit form's entries.
 *
 * @module     qtype_accounting/editform/accounts
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Selector of the account selects of the entries. */
const ACCOUNT_SELECTS = 'select[data-accounting-field="debitaccount"], select[data-accounting-field="creditaccount"]';

/**
 * Split the account list into names: one per line, trimmed, without blank lines and later duplicates.
 *
 * @param {string} text The account list.
 * @return {string[]} The account names in list order.
 */
export const parseNames = text => {
    const names = [];
    text.split(/\r\n|\r|\n/).forEach(line => {
        const name = line.trim();
        if (name !== '' && !names.includes(name)) {
            names.push(name);
        }
    });
    return names;
};

/**
 * Assign account keys like the server does: known names keep their key, new names get the next free keys.
 *
 * @param {string[]} names The account names in list order.
 * @param {Object} knownKeys Keys of the edited version, keyed by account name.
 * @return {Object} Account names keyed by account key.
 */
export const assignKeys = (names, knownKeys) => {
    const known = Object.values(knownKeys).map(Number);
    let next = known.length ? Math.max(...known) + 1 : 1;
    const accounts = {};
    names.forEach(name => {
        const key = Object.prototype.hasOwnProperty.call(knownKeys, name) ? Number(knownKeys[name]) : next++;
        accounts[key] = name;
    });
    return accounts;
};

/**
 * Mark a selection which is no longer in the account list; saving explains it.
 *
 * @param {HTMLSelectElement} select Account selector.
 */
const markAvailability = select => {
    const missing = select.selectedOptions[0] && select.selectedOptions[0].dataset.unavailable;
    select.classList.toggle('is-invalid', !!missing);
};

/**
 * Update the account options of all entries to the account list.
 *
 * Selections follow their account name, as new accounts may get other keys when the list changes.
 * A selected account that is not in the list stays selected and is marked as unavailable; it is selected
 * again when its name returns to the list, for example after a typing mistake has been corrected.
 *
 * @param {HTMLFormElement} form The question form.
 * @param {string} list The account list.
 * @param {Object} knownKeys Keys of the edited version, keyed by account name.
 */
export const updateAccountSelects = (form, list, knownKeys) => {
    const accounts = assignKeys(parseNames(list), knownKeys);
    const keysByName = {};
    Object.entries(accounts).forEach(([key, name]) => {
        keysByName[name] = key;
    });
    // Sort by name like the server, which compares bytes.
    const sorted = Object.entries(accounts).sort((a, b) => {
        if (a[1] === b[1]) {
            return 0;
        }
        return a[1] < b[1] ? -1 : 1;
    });

    form.querySelectorAll(ACCOUNT_SELECTS).forEach(select => {
        const currentValue = select.value;
        const currentOption = select.selectedOptions[0];
        const name = currentValue && currentOption ? currentOption.text : null;
        // Keep the first option (placeholder).
        while (select.options.length > 1) {
            select.remove(1);
        }
        sorted.forEach(([key, accountName]) => select.add(new Option(accountName, key)));
        if (currentValue) {
            if (name !== null && Object.prototype.hasOwnProperty.call(keysByName, name)) {
                select.value = keysByName[name];
                delete currentOption.dataset.unavailable;
            } else {
                // Keep showing the selection, but with a value no account has: a new account may have got its key.
                // The server reports it as no longer in the list.
                currentOption.dataset.unavailable = 'true';
                currentOption.value = String(-Math.abs(Number(currentValue)));
                select.add(currentOption);
                currentOption.selected = true;
            }
        }
        markAvailability(select);
        if (select.value !== currentValue) {
            // Lets the form dependencies enable or disable the amount and weights.
            select.dispatchEvent(new Event('change', {bubbles: true}));
        }
    });
};

/**
 * Follow the account list and the account selects.
 *
 * @param {HTMLFormElement} form The question form.
 */
export const init = form => {
    const input = form.elements.accounts;
    const keysField = form.elements.accountkeys;
    let knownKeys = {};
    try {
        knownKeys = JSON.parse(keysField ? keysField.value : '{}') || {};
    } catch (e) {
        knownKeys = {};
    }
    if (input) {
        input.addEventListener('input', () => updateAccountSelects(form, input.value, knownKeys));
    }
    form.addEventListener('change', e => {
        if (e.target.matches && e.target.matches(ACCOUNT_SELECTS)) {
            markAvailability(e.target);
        }
    });
};
