// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Chart management overlay for the question editor.
 *
 * @module     qtype_accounting/chart_overlay
 * @copyright  2026 lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {get_strings as getStrings} from 'core/str';
import {exception} from 'core/notification';

/**
 * Read all successful form controls, including inputs associated using the form attribute.
 *
 * @param {HTMLFormElement} form Chart management form.
 * @returns {string} Snapshot of the form values.
 */
const formValues = form => JSON.stringify(Array.from(new FormData(form), ([name, value]) =>
    [name, typeof value === 'string' ? value : [value.name, value.size, value.name ? value.lastModified : 0]]
));

/**
 * Keep every dismissal path behind the same save/unsaved-edit checks.
 */
class ChartOverlay extends Modal {
    frame = null;
    forms = new Map();
    saving = false;
    confirming = false;
    closing = false;
    messages = [];

    /**
     * Track the current embedded page after each navigation or form submission.
     */
    trackPage() {
        this.saving = false;
        this.getFooter().find('[role="status"]').text('');
        const page = this.frame.contentDocument;
        if (!page) {
            return;
        }
        this.forms = new Map(Array.from(page.forms, form => [form, formValues(form)]));
        // Run after the page's form-validation handlers; rejected submissions remain editable.
        this.frame.contentWindow.addEventListener('submit', event => {
            if (!event.defaultPrevented) {
                this.saving = true;
            }
        });
        page.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (link) {
                const url = new URL(link.href);
                // Account deletion is confirmed by a navigation rather than a form submission.
                if (url.searchParams.get('action') === 'deleteaccount' && url.searchParams.get('confirm') === '1') {
                    this.saving = true;
                }
            }
        });
        page.addEventListener('keydown', event => {
            // Escape in a filepicker or deletion dialogue belongs to that dialogue.
            if (event.key === 'Escape' && !page.querySelector('[data-region="modal-container"].show')) {
                event.preventDefault();
                this.destroy();
            }
        });
    }

    /**
     * Close only after a save finishes, and confirm discarding unfinished edits.
     */
    async destroy() {
        if (this.closing || this.confirming) {
            return;
        }
        if (this.saving) {
            this.getFooter().find('[role="status"]').text(this.messages[5]);
            return;
        }
        const dirty = Array.from(this.forms).some(([form, values]) => formValues(form) !== values);
        if (!dirty) {
            this.closing = true;
            super.destroy();
            return;
        }
        this.confirming = true;
        try {
            const confirmation = await ModalSaveCancel.create({
                title: this.messages[2],
                body: this.messages[3],
                buttons: {save: this.messages[4]},
                removeOnClose: true,
                returnElement: this.frame,
            });
            let discard = false;
            confirmation.getRoot().on(ModalEvents.save, () => {
                discard = true;
            });
            confirmation.getRoot().one(ModalEvents.hidden, () => {
                this.confirming = false;
                if (discard) {
                    this.closing = true;
                    super.destroy();
                }
            });
            confirmation.show();
        } catch (error) {
            this.confirming = false;
            exception(error);
        }
    }
}

/**
 * Enhance the management link, keeping its new-tab behavior as a no-JavaScript fallback.
 *
 * @param {Function|null} refreshCharts Refresh the question's chart and account selectors.
 */
export const init = refreshCharts => {
    const link = document.getElementById('qtype_accounting-manage-charts');
    if (!link || !refreshCharts) {
        return;
    }
    let opening = false;
    let activeModal = null;
    link.addEventListener('click', async event => {
        // Preserve intentional browser actions such as Ctrl/Cmd-click to open a separate tab.
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        if (opening || activeModal) {
            return;
        }
        opening = true;
        try {
            const messages = await getStrings([
                'chartoverlaydone', 'chartoverlayhint', 'chartoverlayunsavedtitle',
                'chartoverlayunsaved', 'chartoverlaydiscard', 'chartoverlaysaving',
            ].map(key => ({key, component: 'qtype_accounting'})));
            const frame = document.createElement('iframe');
            frame.id = 'qtype_accounting-chart-frame';
            frame.className = 'qtype_accounting-chart-frame';
            frame.tabIndex = 0;
            frame.title = link.textContent;
            const url = new URL(link.href);
            url.searchParams.set('embedded', '1');

            const footer = document.createElement('div');
            footer.className = 'qtype_accounting-chart-footer';
            const hint = document.createElement('span');
            hint.textContent = messages[1];
            const status = document.createElement('span');
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            const done = document.createElement('button');
            done.type = 'button';
            done.className = 'btn btn-primary';
            done.textContent = messages[0];
            footer.append(hint, status, done);

            const modal = await ChartOverlay.create({
                title: link.textContent,
                body: frame.outerHTML,
                footer: footer.outerHTML,
                large: true,
                removeOnClose: true,
                returnElement: link,
            });
            activeModal = modal;
            modal.messages = messages;
            modal.getRoot().addClass('qtype_accounting-chart-overlay');
            modal.frame = modal.getBody().find('iframe')[0];
            modal.frame.addEventListener('load', () => modal.trackPage());
            modal.getFooter().find('button').on('click', () => modal.destroy());
            // An accidental click outside must not interrupt chart editing.
            modal.getRoot().on(ModalEvents.outsideClick, event => event.preventDefault());
            modal.getRoot().one(ModalEvents.hidden, () => {
                activeModal = null;
                refreshCharts();
            });
            await modal.show();
            modal.frame.src = url.href;
        } catch (error) {
            if (activeModal) {
                activeModal.closing = false;
                activeModal.forms.clear();
                activeModal.saving = false;
                activeModal.destroy();
                activeModal = null;
            }
            exception(error);
        } finally {
            opening = false;
        }
    });
};
