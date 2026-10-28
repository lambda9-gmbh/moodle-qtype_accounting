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
        // Map the browser's field feedback to the app's standard correctness styling.
        element.querySelectorAll('.qtype_accounting-correct').forEach(field => field.classList.add('correct'));
        element.querySelectorAll('.qtype_accounting-incorrect').forEach(field => field.classList.add('incorrect'));
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
        question.accountingPlaceholder = container.getAttribute('data-numberformat') === 'us' ? '0.00' : '0,00';
        question.accountingSides = [];

        // Local answers can contain rows not present in the server's last saved HTML.
        const local = readonly ? {} : question.localAnswers || {};
        const value = (field, name) => Object.prototype.hasOwnProperty.call(local, name)
            ? String(local[name] ?? '') : field?.value || '';
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
                entries.push({
                    index,
                    accountName: prefix + accountKey,
                    amountName: prefix + amountKey,
                    account: accountValue,
                    amount: amountValue,
                    visible: (accountValue !== '' && accountValue !== '0') || (amountValue !== '' && amountValue !== '0'),
                    accountReview: account?.parentElement.querySelector('.qtype_accounting-readonly')?.outerHTML || '',
                    amountReview: amount?.parentElement.querySelector('.qtype_accounting-readonly')?.outerHTML || '',
                });
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

        // The surrounding quiz component displays feedback according to the quiz review options.
        const feedback = element.querySelector('.feedback');
        if (feedback) {
            accountingProviders.CoreQuestionHelperProvider.replaceFeedbackClasses(element);
            accountingProviders.CoreQuestionHelperProvider.treatCorrectnessIcons(element);
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
