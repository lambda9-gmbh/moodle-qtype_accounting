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
 * Confirm chart deletion in a Moodle modal.
 *
 * @module     qtype_accounting/manage_charts
 * @copyright  2026 lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {deleteCancelPromise} from 'core/notification';

/**
 * Attach confirmation modals to chart deletion links.
 */
export const init = () => {
    document.querySelectorAll('[data-action="qtype_accounting-delete-chart"]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            deleteCancelPromise(
                link.dataset.confirmationTitle,
                link.dataset.confirmationMessage,
                link.dataset.confirmationTitle
            ).then(() => {
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
                return;
            }).catch(() => {
                // Cancelling or dismissing the modal leaves the chart unchanged.
                return;
            });
        });
    });
};
