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
 * Moodle App controller. This is evaluated by the app, rather than loaded as AMD.
 *
 * @copyright 2026 Hochschule Flensburg / lambda9
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const accountingProviders = this;

const accountingComponent = {
    componentInit() {
        const question = this.question;
        const abort = () => accountingProviders.CoreQuestionHelperProvider.showComponentError(this.onAbort);
        if (!question) {
            return abort();
        }

        const element = document.createElement('div');
        element.innerHTML = question.html;
        // Add app classes directly: its helper replaces substrings of className,
        // which would corrupt our namespaced markers if we added "correct"/"incorrect".
        element.querySelectorAll('.qtype_accounting-correct').forEach(field =>
            field.classList.add('core-question-answer-correct'));
        element.querySelectorAll('.qtype_accounting-incorrect').forEach(field =>
            field.classList.add('core-question-answer-incorrect'));
        accountingProviders.CoreQuestionHelperProvider.replaceCorrectnessClasses(element);
        const container = element.querySelector('.qtype_accounting-question-container');
        const text = element.querySelector('.qtext');
        const firstField = container && container.querySelector('[name$="debitaccount_0"]');
        if (!container || !text || !firstField) {
            return abort();
        }

        const prefix = firstField.name.slice(0, -'debitaccount_0'.length);
        const readonly = container.getAttribute('data-allowedit') !== '1';
        const limit = Number(container.getAttribute('data-maxrows'));
        if (!Number.isInteger(limit) || limit < 1) {
            return abort();
        }

        question.readonly = readonly;
        question.text = text.innerHTML;
        question.accountingNotice = element.querySelector('.alert')?.innerHTML || '';
        const numberFormat = container.getAttribute('data-numberformat') === 'us' ? 'us' : 'de';
        question.accountingPlaceholder = numberFormat === 'us' ? '0.00' : '0,00';
        question.accountingSides = [];

        // Match the browser's formatNumber(value, numberFormat, 2), including
        // German grouping such as 1.000 and formatting when the field loses focus.
        this.formatAmount = entry => {
            if (question.readonly) {
                return;
            }
            const value = String(entry.amount ?? '').trim();
            const plain = numberFormat === 'de' ? value.replace(/\./g, '').replace(',', '.') : value.replace(/,/g, '');
            const match = plain.match(/^([+-]?)(\d*)(?:\.(\d*))?$/);
            if (!match || !/\d/.test(plain)) {
                entry.amount = '';
                return;
            }
            const fraction = (match[3] || '').padEnd(3, '0');
            const cents = BigInt((match[2] || '0') + fraction.slice(0, 2)) + (fraction[2] >= '5' ? 1n : 0n);
            const digits = String(cents).padStart(3, '0');
            const whole = digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, numberFormat === 'us' ? ',' : '.');
            entry.amount = (match[1] === '-' && cents !== 0n ? '-' : '') + whole +
                (numberFormat === 'us' ? '.' : ',') + digits.slice(-2);
        };

        // Local answers can contain rows not present in the server's last saved HTML.
        const local = readonly ? {} : question.localAnswers || {};
        const value = (field, name) => Object.prototype.hasOwnProperty.call(local, name)
            ? String(local[name] ?? '') : field?.value || '';
        const correctness = field => {
            if (field?.classList.contains('qtype_accounting-correct')) {
                return 'correct';
            }
            return field?.classList.contains('qtype_accounting-incorrect') ? 'incorrect' : '';
        };
        const accountSelect = container.querySelector('select');
        question.accountingAccounts = accountSelect ? Array.from(accountSelect.options).map(option => ({
            value: option.value,
            label: option.textContent,
        })) : [];

        ['debit', 'credit'].forEach(side => {
            const entries = [];
            let lastIndex = 0;
            const fields = container.querySelectorAll('[name]');
            const names = Array.from(fields).map(field => field.name.slice(prefix.length)).concat(Object.keys(local));
            names.forEach(name => {
                const match = name.match(new RegExp('^' + side + '(?:account|amount)_(\\d+)$'));
                if (match && Number(match[1]) < limit) {
                    lastIndex = Math.max(lastIndex, Number(match[1]));
                }
            });

            for (let index = 0; index <= lastIndex; index++) {
                const accountKey = side + 'account_' + index;
                const amountKey = side + 'amount_' + index;
                const account = container.querySelector('[name="' + prefix + accountKey + '"]');
                const amount = container.querySelector('[name="' + prefix + amountKey + '"]');
                const accountValue = value(account, accountKey);
                const amountValue = value(amount, amountKey);
                const accountReview = account?.parentElement.querySelector('.qtype_accounting-readonly');
                const amountReview = amount?.parentElement.querySelector('.qtype_accounting-readonly');
                entries.push({
                    index,
                    accountName: prefix + accountKey,
                    amountName: prefix + amountKey,
                    account: accountValue,
                    amount: amountValue,
                    visible: (accountValue !== '' && accountValue !== '0') || (amountValue !== '' && amountValue !== '0'),
                    accountReview: accountReview?.outerHTML || '',
                    amountReview: amountReview?.outerHTML || '',
                    accountCorrectness: correctness(accountReview),
                    amountCorrectness: correctness(amountReview),
                });
                this.formatAmount(entries[entries.length - 1]);
            }

            // An unanswered question needs one editable entry on each side.
            if (!readonly && !entries.some(entry => entry.visible)) {
                entries[0].visible = true;
            }
            question.accountingSides.push({
                name: side,
                title: 'plugin.qtype_accounting.' + side + 'entries',
                addLabel: 'plugin.qtype_accounting.add' + side + 'entry',
                entries,
                limit,
            });
        });

        // The app extracts .outcome into feedbackHtml before initializing this
        // controller. Handle that source as well as unprocessed question HTML.
        const feedbackElement = typeof question.feedbackHtml === 'string' ? document.createElement('div') : element;
        if (feedbackElement !== element) {
            feedbackElement.innerHTML = question.feedbackHtml;
        }
        const feedback = feedbackElement.querySelector('.feedback');
        question.accountingSolution = null;
        if (feedback) {
            const solution = feedback.querySelector('.qtype_accounting-correct-response');
            const table = solution?.querySelector('.qtype_accounting-solution');
            if (table) {
                const sides = ['debit', 'credit'].map(name => ({
                    title: 'plugin.qtype_accounting.' + name + 'entries',
                    entries: [],
                }));
                table.querySelectorAll('tbody > tr').forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length !== 4) {
                        return;
                    }
                    const explanation = row.nextElementSibling?.classList.contains('qtype_accounting-explanation-row')
                        ? row.nextElementSibling.querySelector('td')?.innerHTML || '' : '';
                    let explanationAssigned = false;
                    sides.forEach((side, index) => {
                        const account = cells[index * 2].textContent.trim();
                        const amount = cells[index * 2 + 1].textContent.trim();
                        if (account || amount) {
                            side.entries.push({account, amount, explanation: explanationAssigned ? '' : explanation});
                            explanationAssigned = true;
                        }
                    });
                });
                question.accountingSolution = {
                    title: solution.querySelector('p')?.textContent || '',
                    sides: sides.filter(side => side.entries.length),
                };
                // The app template renders these entries as Ionic cards instead.
                solution.remove();
            }
            accountingProviders.CoreQuestionHelperProvider.replaceFeedbackClasses(feedbackElement);
            accountingProviders.CoreQuestionHelperProvider.treatCorrectnessIcons(feedbackElement);
            if (question.accountingSolution) {
                const summary = feedback.querySelector('.qtype_accounting-feedback-summary');
                // The browser's mt-3 spacing sits inside the app warning and
                // pushes the message down. Let the enclosing panel own spacing.
                if (summary) {
                    summary.classList.remove('mt-3');
                    summary.style.margin = '0';
                }
                // The enclosing app warning supplies the feedback background.
                // Do not retain the browser alert's red/green background inside it.
                summary?.querySelectorAll('.alert').forEach(alert =>
                    alert.classList.remove('alert', 'alert-danger', 'alert-warning', 'alert-success'));
                question.accountingSolution.summaryHtml = summary?.outerHTML || '';
                summary?.remove();
            }
            // This is the property rendered by CoreQuestionComponent, rather than
            // the legacy feedback/feedbackHTML fields used by older handlers.
            // Empty .specificfeedback/.rightanswer wrappers must not cause the
            // app to render an empty orange feedback panel below the cards.
            const hasFeedback = feedback.textContent?.trim() ||
                feedback.querySelector('img, video, audio, iframe, object, embed, svg, canvas, hr');
            question.feedbackHtml = hasFeedback ? feedback.outerHTML : '';
            question.feedback = feedback;
            question.feedbackHTML = true;
        }

        // The app only calls componentInit on the compiled template instance. Other
        // methods in the returned object override the handler, not the component.
        this.addEntry = side => {
            if (question.readonly) {
                return;
            }
            const unused = side.entries.find(entry => !entry.visible);
            if (unused) {
                unused.visible = true;
                return;
            }
            const index = side.entries.length;
            if (index >= side.limit) {
                return;
            }
            side.entries.push({
                index,
                accountName: prefix + side.name + 'account_' + index,
                amountName: prefix + side.name + 'amount_' + index,
                account: '',
                amount: '',
                visible: true,
            });
        };

        this.canRemoveEntry = side => side.entries.filter(entry => entry.visible).length > 1;

        this.removeEntry = (side, entry) => {
            if (question.readonly || !entry.visible || !side.entries.includes(entry) || !this.canRemoveEntry(side)) {
                return;
            }
            // Named hidden inputs remain in the form to clear previously saved values.
            entry.account = '';
            entry.amount = '';
            entry.visible = false;
        };
        return true;
    },
};

accountingComponent;
