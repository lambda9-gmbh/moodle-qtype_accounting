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
 * The form works without JavaScript; this module updates the account options while the account list
 * is edited, formats the amounts and shows the meaning of an empty account limit.
 *
 * @module     qtype_accounting/editform
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as EntryUtils from 'qtype_accounting/entry_utils';
import * as Accounts from 'qtype_accounting/editform/accounts';

/** Selector of the amount fields of the entries. */
const AMOUNTS = 'input[data-accounting-field="debitamount"], input[data-accounting-field="creditamount"]';

/**
 * Display the meaning of blank or zero account limits when editing is finished.
 *
 * @param {HTMLFormElement} form The question form.
 */
const setupAccountCount = form => {
    const input = form.elements.accountsindropdown;
    if (!input || input.type === 'hidden') {
        return;
    }
    const allLabel = input.placeholder;
    const normalise = () => {
        const value = input.value.trim();
        if (value === '' || /^-?0+$/.test(value) || value.toLowerCase() === allLabel.toLowerCase()) {
            input.value = allLabel;
        }
    };
    normalise();
    input.addEventListener('change', normalise);
};

/**
 * Format the amounts when they are left, and reformat them when the number format changes.
 *
 * @param {HTMLFormElement} form The question form.
 */
const setupAmounts = form => {
    const formatSelect = form.elements.numberformat;
    const getFormat = () => (formatSelect && formatSelect.value) || 'de';
    let previousFormat = getFormat();

    // Blur does not bubble, so listen in the capture phase.
    form.addEventListener('blur', e => {
        if (e.target.matches && e.target.matches(AMOUNTS) && e.target.value.trim() !== '') {
            e.target.value = EntryUtils.formatNumber(e.target.value, getFormat());
        }
    }, true);

    if (formatSelect) {
        formatSelect.addEventListener('change', () => {
            const format = getFormat();
            form.querySelectorAll(AMOUNTS).forEach(field => {
                field.placeholder = format === 'us' ? '0.00' : '0,00';
                if (field.value) {
                    const cents = EntryUtils.parseCents(field.value, previousFormat);
                    field.value = cents === null ? '' : EntryUtils.formatCents(cents, format);
                }
            });
            previousFormat = format;
        });
    }
};

/**
 * Initialise the edit form enhancements.
 */
export const init = () => {
    const select = document.querySelector('select[data-accounting-field="debitaccount"]');
    const form = select ? select.closest('form') : null;
    if (!form) {
        return;
    }
    setupAccountCount(form);
    setupAmounts(form);
    Accounts.init(form);
};
