// Run with Node in a Nix environment: node dev/tests/mobileapp_test.cjs
// These controller tests supply the DOM interface used by the app; they do not run Angular or Moodle.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../js/mobileapp/question.js'), 'utf8');

function fixture({saved = {}, local = {}, readonly = false, limit = 3, format = 'de', missing = false} = {}) {
    const fields = new Map();
    const prefix = 'q42:7_';
    const keys = new Set(['debitaccount_0', 'debitamount_0', 'creditaccount_0', 'creditamount_0', ...Object.keys(saved)]);
    for (const key of keys) {
        fields.set(prefix + key, {
            name: prefix + key,
            value: saved[key] || '',
            parentElement: {querySelector: () => ({outerHTML: '<span>Saved review value</span>'})},
        });
    }
    const container = {
        getAttribute: name => ({'data-allowedit': readonly ? '0' : '1', 'data-maxrows': String(limit),
            'data-numberformat': format})[name],
        querySelector(selector) {
            if (selector === '[name$="debitaccount_0"]') {
                return fields.get(prefix + 'debitaccount_0');
            }
            if (selector === 'select') {
                return {options: [{value: '', textContent: 'Choose'}, {value: '101', textContent: 'Bank & Cash'},
                    {value: '201', textContent: 'Sales'}]};
            }
            return fields.get(selector.slice('[name="'.length, -2)) || null;
        },
        querySelectorAll: () => Array.from(fields.values()),
    };
    const feedback = {innerHTML: '<p>Feedback permitted by the quiz</p>'};
    const element = {
        querySelector(selector) {
            return {'.qtype_accounting-question-container': missing ? null : container,
                '.qtext': {innerHTML: 'Record the booking'}, '.feedback': feedback}[selector] || null;
        },
        querySelectorAll: () => [],
    };
    let aborted = false;
    const context = {
        document: {createElement: () => element},
        CoreQuestionHelperProvider: {
            showComponentError: () => { aborted = true; },
            replaceCorrectnessClasses() {},
            replaceFeedbackClasses() {},
            treatCorrectnessIcons() {},
        },
    };
    const component = vm.runInNewContext(source, context);
    component.question = {html: '<server HTML>', localAnswers: local};
    component.componentInit();
    return {component, question: component.question, aborted, prefix, feedback};
}

test('unanswered question has independent debit/credit entries and preserved options', () => {
    const {question, prefix} = fixture();
    assert.equal(question.accountingSides.length, 2);
    assert.equal(question.accountingSides[0].entries[0].accountName, prefix + 'debitaccount_0');
    assert.equal(question.accountingSides[1].entries[0].visible, true);
    assert.equal(question.accountingAccounts[1].label, 'Bank & Cash');
    assert.equal(question.accountingPlaceholder, '0,00');
    assert.equal(fixture({format: 'us'}).question.accountingPlaceholder, '0.00');
});

test('restores local rows absent from server HTML and preserves locale-formatted amounts', () => {
    const {question} = fixture({saved: {debitaccount_0: '101', debitamount_0: '100'},
        local: {debitamount_0: '1.234,56', debitaccount_2: '201', debitamount_2: '200,00'}});
    const entries = question.accountingSides[0].entries;
    assert.equal(entries.length, 3);
    assert.equal(entries[0].amount, '1.234,56');
    assert.equal(entries[1].visible, false);
    assert.equal(entries[2].visible, true);
    assert.equal(entries[2].account, '201');
});

test('deletion clears both saved fields without shifting the other side; adding reuses cleared row', () => {
    const {component, question} = fixture({saved: {debitaccount_0: '101', debitamount_0: '100',
        creditaccount_0: '201', creditamount_0: '100'}});
    const debit = question.accountingSides[0];
    const entry = debit.entries[0];
    component.removeEntry(entry);
    assert.equal(entry.account, '');
    assert.equal(entry.amount, '');
    assert.equal(entry.visible, false);
    assert.equal(question.accountingSides[1].entries[0].account, '201');
    component.addEntry(debit);
    assert.equal(debit.entries.length, 1);
    assert.equal(entry.visible, true);
});

test('new row field names remain valid and additions stop at the server expected-data limit', () => {
    const {component, question, prefix} = fixture();
    const debit = question.accountingSides[0];
    for (let index = 0; index < 8; index++) {
        component.addEntry(debit);
    }
    assert.equal(debit.entries.length, 3);
    assert.equal(debit.entries[2].amountName, prefix + 'debitamount_2');
});

test('review ignores local edits and cannot add or remove entries', () => {
    const {component, question, feedback} = fixture({readonly: true,
        saved: {debitaccount_0: '101', debitamount_0: '100'}, local: {debitaccount_0: '201'}});
    const debit = question.accountingSides[0];
    component.removeEntry(debit.entries[0]);
    component.addEntry(debit);
    assert.equal(debit.entries[0].account, '101');
    assert.equal(debit.entries.length, 1);
    assert.equal(debit.entries[0].accountReview, '<span>Saved review value</span>');
    assert.equal(question.feedback, feedback);
    assert.equal(question.feedbackHTML, true);
});

test('local cleared fields override saved values and invalid row indices are ignored', () => {
    const {question} = fixture({saved: {debitaccount_0: '101', debitamount_0: '100'},
        local: {debitaccount_0: '', debitamount_0: '', debitaccount_9999999: '101', debitaccount_invalid: '101'}});
    const entries = question.accountingSides[0].entries;
    assert.equal(entries.length, 1);
    assert.equal(entries[0].account, '');
    assert.equal(entries[0].amount, '');
});

test('missing renderer metadata aborts instead of displaying a question with no inputs', () => {
    assert.equal(fixture({missing: true}).aborted, true);
    assert.equal(fixture({limit: 0}).aborted, true);
});
