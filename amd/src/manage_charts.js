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
 * Actions on the chart of accounts template pages: delete confirmations and adding accounts in a modal form.
 *
 * @module     qtype_accounting/manage_charts
 * @copyright  2026 lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {deleteCancelPromise} from 'core/notification';
import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';

/**
 * Post a confirmed deletion link with the session key.
 *
 * @param {HTMLAnchorElement} link The deletion link.
 */
const postDeletion = link => {
    const url = new URL(link.href);
    const form = document.createElement('form');
    form.method = 'post';
    form.action = url.origin + url.pathname;
    url.searchParams.set('confirm', '1');
    url.searchParams.set('sesskey', M.cfg.sesskey);
    url.searchParams.forEach((value, name) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.requestSubmit();
};

/**
 * Attach confirmation modals to deletion links and the modal form to the add account button.
 */
export const init = () => {
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-action="qtype_accounting-delete"]');
        if (link) {
            event.preventDefault();
            deleteCancelPromise(
                link.dataset.confirmationTitle,
                link.dataset.confirmationMessage,
                link.dataset.confirmationTitle
            ).then(() => postDeletion(link)).catch(() => {
                // Cancelling or dismissing the modal leaves the template unchanged.
                return;
            });
            return;
        }
        const button = event.target.closest('[data-action="qtype_accounting-add-account"]');
        if (button) {
            event.preventDefault();
            const modal = new ModalForm({
                formClass: 'qtype_accounting\\form\\account_form',
                args: {chartid: button.dataset.chartid},
                modalConfig: {title: getString('addaccount', 'qtype_accounting')},
                saveButtonText: getString('addaccount', 'qtype_accounting'),
                returnFocus: button,
            });
            modal.addEventListener(modal.events.FORM_SUBMITTED, () => window.location.reload());
            modal.show();
        }
    });
};
