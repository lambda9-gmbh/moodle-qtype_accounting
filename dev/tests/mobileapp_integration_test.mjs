// Angular DOM tests for the Moodle App 5.2.1 template lifecycle and native quiz form.
// Ionic tags are left as custom elements: these tests cover their Angular bindings,
// not Ionic overlays, rendering, themes, or a connection to a running Moodle site.
import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import {createRequire} from 'node:module';
import path from 'node:path';
import {afterEach, test} from 'node:test';
import {fileURLToPath, pathToFileURL} from 'node:url';
import vm from 'node:vm';

const dependencies = process.env.ACCOUNTING_MOBILE_TEST_DEPS || path.join(path.dirname(fileURLToPath(import.meta.url)), 'mobileapp');
const require = createRequire(path.join(dependencies, 'package.json'));
const {JSDOM} = require('jsdom');
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {url: 'http://localhost/'});
for (const key of ['window', 'document', 'Node', 'HTMLElement', 'Element', 'Event', 'CustomEvent']) {
    globalThis[key] = dom.window[key];
}
const load = name => import(pathToFileURL(require.resolve(name)).href);
await load('@angular/compiler');
const {Component, CUSTOM_ELEMENTS_SCHEMA, Pipe, provideZonelessChangeDetection} = await load('@angular/core');
const {CommonModule} = await load('@angular/common');
const {FormsModule} = await load('@angular/forms');
const {TestBed} = await load('@angular/core/testing');
const {BrowserDynamicTestingModule, platformBrowserDynamicTesting} = await load('@angular/platform-browser-dynamic/testing');
TestBed.initTestEnvironment(BrowserDynamicTestingModule, platformBrowserDynamicTesting());

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
// Optional negative control: run the same integration tests against a committed revision.
const read = file => process.env.ACCOUNTING_MOBILE_TEST_REVISION
    ? execFileSync('git', ['show', process.env.ACCOUNTING_MOBILE_TEST_REVISION + ':' + file], {cwd: root, encoding: 'utf8'})
    : fs.readFileSync(path.join(root, file), 'utf8');
const controller = read('js/mobileapp/question.js');
const template = read('templates/mobileapp/question.mustache')
    .replace(/{{![\s\S]*?}}/g, '').replace('{{=<% %>=}}', '').replace(/<%![\s\S]*?%>/g, '');

class TranslatePipe {
    transform(value) {
        return value;
    }
}
Pipe({name: 'translate', standalone: true})(TranslatePipe);
class QuizHost {}
Component({
    selector: 'accounting-test-quiz',
    standalone: true,
    imports: [CommonModule, FormsModule, TranslatePipe],
    schemas: [CUSTOM_ELEMENTS_SCHEMA],
    template: '<form name="addon-mod_quiz-player-form">' + template + '</form>',
})(QuizHost);

function serverHtml({saved = {}, readonly = false, format = 'de', accounts = true, correctness = {}, feedback = ''} = {}) {
    const fields = ['debit', 'credit'].map(side => {
        const accountKey = side + 'account_0';
        const amountKey = side + 'amount_0';
        const account = accounts && !readonly
            ? `<select name="q42:7_${accountKey}"><option value="">Choose</option>` +
                `<option value="101" ${saved[accountKey] === '101' ? 'selected' : ''}>Bank</option>` +
                `<option value="201" ${saved[accountKey] === '201' ? 'selected' : ''}>Sales</option></select>`
            : `<input name="q42:7_${accountKey}" value="${saved[accountKey] || ''}">`;
        const feedbackClass = key => correctness[key] ? ' qtype_accounting-' + correctness[key] : '';
        return `<div><span class="qtype_accounting-readonly${feedbackClass(accountKey)}">Bank</span>${account}</div>` +
            `<div><span class="qtype_accounting-readonly${feedbackClass(amountKey)}">100,00</span>` +
            `<input name="q42:7_${amountKey}" value="${saved[amountKey] || ''}"></div>`;
    }).join('');
    return '<div class="qtext">Record the booking</div>' +
        `<div class="qtype_accounting-question-container" data-allowedit="${readonly ? '0' : '1'}"` +
        ` data-maxrows="3" data-numberformat="${format}">${fields}</div>` +
        (feedback ? '<div class="outcome">' + feedback + '</div>' : '');
}

async function render(options = {}) {
    TestBed.configureTestingModule({imports: [QuizHost], providers: [provideZonelessChangeDetection()]});
    await TestBed.compileComponents();
    const fixture = TestBed.createComponent(QuizHost);
    const instance = fixture.componentInstance;
    instance.question = {html: serverHtml(options), localAnswers: options.local || {}};
    // CoreQuestionHelper.extractQuestionFeedback runs before the plugin controller.
    const parsed = document.createElement('div');
    parsed.innerHTML = instance.question.html;
    const outcome = parsed.querySelector('.outcome');
    if (outcome) {
        instance.question.feedbackHtml = outcome.innerHTML;
        outcome.remove();
        instance.question.html = parsed.innerHTML;
    }
    const handler = vm.runInNewContext(controller, {
        document: dom.window.document,
        CoreQuestionHelperProvider: {
            showComponentError() { throw new Error('Question aborted'); },
            replaceCorrectnessClasses(element) {
                // Match the app helper, including its substring replacement of className.
                for (const state of ['correct', 'incorrect', 'partiallycorrect']) {
                    element.querySelectorAll('.' + state).forEach(field => {
                        field.className = field.className.replace(state, 'core-question-answer-' + state);
                    });
                }
            },
            replaceFeedbackClasses() {},
            treatCorrectnessIcons() {},
        },
    });
    // This is the app lifecycle, not a call on the handler object itself.
    handler.componentInit.apply(instance);
    fixture.detectChanges();
    await fixture.whenStable();
    return fixture;
}

const payload = fixture => Object.fromEntries(new dom.window.FormData(fixture.nativeElement.querySelector('form')));
const action = (fixture, text) => {
    const button = Array.from(fixture.nativeElement.querySelectorAll('ion-button')).find(item => item.textContent.includes(text));
    assert.ok(button, 'Expected rendered button: ' + text);
    button.click();
    fixture.detectChanges();
};
function input(element, value, event = 'ionInput') {
    assert.ok(element, 'Expected Ionic field');
    element.dispatchEvent(new dom.window.CustomEvent(event, {detail: {value}, bubbles: true}));
}
function enterBooking(card, account, amount) {
    // Support the earlier native fields for the negative control: this test must
    // detect the lost payload itself, not merely assert that Ionic tags exist.
    const select = card.querySelector('ion-select');
    if (select) {
        input(select, account, 'ionChange');
        input(card.querySelector('ion-input'), amount);
        return;
    }
    const nativeSelect = card.querySelector('select');
    nativeSelect.value = account;
    nativeSelect.dispatchEvent(new dom.window.Event('change', {bubbles: true}));
    const nativeAmount = card.querySelector('input[type="text"]');
    nativeAmount.value = amount;
    nativeAmount.dispatchEvent(new dom.window.Event('input', {bubbles: true}));
}
afterEach(() => TestBed.resetTestingModule());

test('review highlights each account and amount independently with app feedback backgrounds', async () => {
    const fixture = await render({readonly: true,
        saved: {debitaccount_0: '101', debitamount_0: '50,00', creditaccount_0: '201', creditamount_0: '100,00'},
        correctness: {debitaccount_0: 'correct', debitamount_0: 'incorrect',
            creditaccount_0: 'incorrect', creditamount_0: 'correct'}});
    const cards = fixture.nativeElement.querySelectorAll('ion-card');
    for (const [index, states] of [['correct', 'incorrect'], ['incorrect', 'correct']].entries()) {
        const fields = cards[index].querySelectorAll('core-format-text');
        for (const [fieldIndex, state] of states.entries()) {
            assert.ok(fields[fieldIndex].parentElement.classList.contains('core-question-' + state));
            assert.equal(fields[fieldIndex].parentElement.style.backgroundColor,
                'var(--core-question-' + state + '-color-bg)');
            assert.equal(fields[fieldIndex].parentElement.classList.contains(
                'core-question-' + (state === 'correct' ? 'incorrect' : 'correct')), false);
        }
    }
    assert.equal(payload(fixture)['q42:7_debitamount_0'], '50,00');
});

test('unmarked review fields and editable answers have no correctness background', async () => {
    const saved = {debitaccount_0: '101', debitamount_0: '100,00'};
    const review = await render({readonly: true, saved});
    assert.equal(review.nativeElement.querySelectorAll('.core-question-correct, .core-question-incorrect').length, 0);
    review.destroy();
    TestBed.resetTestingModule();
    const attempt = await render({saved});
    assert.equal(attempt.nativeElement.querySelectorAll('.core-question-correct, .core-question-incorrect').length, 0);
});

test('correct answer becomes stacked cards with uneven sides, formatted amounts and explanations', async () => {
    const fixture = await render({readonly: true, feedback:
        '<div class="feedback"><div class="specificfeedback"><div class="qtype_accounting-feedback-summary mt-3">' +
        'The debit side is incorrect. The credit side is incorrect.</div></div><div class="rightanswer">' +
        '<div class="qtype_accounting-correct-response"><p>The correct answer is:</p>' +
        '<table class="qtype_accounting-solution"><thead><tr><th>Debit</th><th>Credit</th></tr></thead><tbody>' +
        '<tr><td>&lt;Cash&gt; &amp; Bank</td><td>1.234,56</td><td>Sales</td><td>2.000,00</td></tr>' +
        '<tr class="qtype_accounting-explanation-row"><td colspan="4"><strong>Explanation:</strong> Split sale</td></tr>' +
        '<tr><td>Receivables</td><td>765,44</td><td></td><td></td></tr>' +
        '</tbody></table></div></div><div class="generalfeedback">General feedback</div></div>'});
    const solution = fixture.nativeElement.querySelector('.qtype_accounting-app-solution');
    assert.ok(solution);
    assert.equal(solution.querySelector('h3').textContent, 'The correct answer is:');
    const sides = solution.querySelectorAll('section');
    assert.equal(sides.length, 2);
    assert.equal(sides[0].querySelectorAll('ion-card').length, 2);
    assert.equal(sides[1].querySelectorAll('ion-card').length, 1);
    assert.ok(sides[0].textContent.includes('<Cash> & Bank'));
    assert.ok(sides[0].textContent.includes('1.234,56'));
    assert.ok(sides[0].textContent.includes('765,44'));
    assert.ok(sides[1].textContent.includes('2.000,00'));
    const summary = solution.querySelector('core-format-text');
    assert.ok(summary.text.includes('The debit side is incorrect. The credit side is incorrect.'));
    assert.equal(summary.parentElement.nextElementSibling, solution.querySelector('h3'));
    assert.equal(summary.parentElement.style.backgroundColor, 'var(--core-question-feedback-background-color)');
    const explanations = solution.querySelectorAll('ion-card core-format-text');
    assert.equal(explanations.length, 1);
    assert.equal(explanations[0].text, '<strong>Explanation:</strong> Split sale');
    const remainingFeedback = fixture.componentInstance.question.feedback;
    assert.equal(remainingFeedback.querySelector('table, .qtype_accounting-correct-response'), null);
    assert.equal(remainingFeedback.querySelector('.qtype_accounting-feedback-summary'), null);
    assert.ok(!fixture.componentInstance.question.feedbackHtml.includes('The debit side is incorrect'));
    assert.ok(remainingFeedback.textContent.includes('General feedback'));
    assert.ok(!fixture.componentInstance.question.feedbackHtml.includes('<table'));
    assert.ok(fixture.componentInstance.question.feedbackHtml.includes('General feedback'));
    assert.equal(solution.querySelector('input, ion-button, ion-input, ion-select, table'), null);
});

test('correct answer stays hidden when quiz feedback does not include it', async () => {
    const fixture = await render({readonly: true, feedback: '<div class="feedback">General feedback only</div>'});
    assert.equal(fixture.nativeElement.querySelector('.qtype_accounting-app-solution'), null);
    assert.equal(fixture.componentInstance.question.accountingSolution, null);
});

test('moved warning uses the orange feedback background and leaves no empty bottom panel', async () => {
    const fixture = await render({readonly: true, feedback:
        '<div class="feedback"><div class="specificfeedback"><div class="qtype_accounting-feedback-summary mt-3">' +
        '<div class="alert alert-danger"><i class="fa fa-times-circle"></i>Both sides are incorrect.</div>' +
        '</div></div><div class="rightanswer"><div class="qtype_accounting-correct-response">' +
        '<p>The correct answer is:</p><table class="qtype_accounting-solution"><tbody>' +
        '<tr><td>Bank</td><td>100.00</td><td>Sales</td><td>100.00</td></tr>' +
        '</tbody></table></div></div><div class="generalfeedback"> </div></div>'});
    const warning = fixture.nativeElement.querySelector('.qtype_accounting-app-summary');
    assert.ok(warning);
    assert.equal(warning.style.backgroundColor, 'var(--core-question-feedback-background-color)');
    assert.equal(warning.style.color, 'var(--core-question-feedback-color)');
    assert.equal(warning.style.display, 'flex');
    assert.equal(warning.style.alignItems, 'center');
    const html = warning.querySelector('core-format-text').text;
    const summary = document.createElement('div');
    summary.innerHTML = html;
    assert.equal(summary.firstElementChild.classList.contains('mt-3'), false);
    assert.equal(summary.firstElementChild.style.margin, '0px');
    assert.ok(html.includes('Both sides are incorrect.'));
    assert.ok(!html.includes('alert-danger'));
    assert.equal(fixture.componentInstance.question.feedbackHtml, '');
});

test('image-only feedback is retained while empty wrappers do not produce a panel', async () => {
    const fixture = await render({readonly: true, feedback:
        '<div class="feedback"><div class="generalfeedback"><img src="feedback.png" alt="Diagram"></div></div>'});
    assert.ok(fixture.componentInstance.question.feedbackHtml.includes('feedback.png'));
});

for (const [format, savedAmount, grouped, decimal] of [
    ['de', '1234,5', '1.234,50', '12,30'],
    ['us', '1234.5', '1,234.50', '12.30'],
]) {
    test(format + ' amounts format on restore and Ionic blur, including added rows', async () => {
        const fixture = await render({format, saved: {debitaccount_0: '101', debitamount_0: savedAmount}});
        assert.equal(payload(fixture)['q42:7_debitamount_0'], grouped);
        const amount = fixture.nativeElement.querySelector('ion-card ion-input');
        input(amount, format === 'de' ? '12,3' : '12.3');
        fixture.detectChanges();
        amount.dispatchEvent(new dom.window.CustomEvent('ionBlur'));
        fixture.detectChanges();
        assert.equal(amount.value, decimal);
        assert.equal(payload(fixture)['q42:7_debitamount_0'], decimal);
        action(fixture, 'adddebitentry');
        const added = fixture.nativeElement.querySelectorAll('ion-card ion-input')[1];
        input(added, format === 'de' ? '1.000' : '1,000');
        added.dispatchEvent(new dom.window.CustomEvent('ionBlur'));
        fixture.detectChanges();
        assert.equal(payload(fixture)['q42:7_debitamount_1'], format === 'de' ? '1.000,00' : '1,000.00');
        input(added, '');
        added.dispatchEvent(new dom.window.CustomEvent('ionBlur'));
        fixture.detectChanges();
        assert.equal(payload(fixture)['q42:7_debitamount_1'], '');
    });
}

test('rendered add/delete actions work on the separate compiled component', async () => {
    const fixture = await render();
    action(fixture, 'adddebitentry');
    action(fixture, 'addcreditentry');
    assert.equal(fixture.nativeElement.querySelectorAll('ion-card').length, 4);
    assert.ok(Object.hasOwn(payload(fixture), 'q42:7_debitaccount_1'));
    assert.ok(Object.hasOwn(payload(fixture), 'q42:7_creditamount_1'));
    action(fixture, 'deleteentry');
    assert.equal(fixture.nativeElement.querySelectorAll('ion-card').length, 3);
    assert.equal(payload(fixture)['q42:7_debitaccount_0'], '');
});

test('Check/Submit payload includes entered accounts and raw amounts without a blur', async () => {
    const fixture = await render();
    const cards = fixture.nativeElement.querySelectorAll('ion-card');
    enterBooking(cards[0], '101', '1.234,56');
    enterBooking(cards[1], '201', '1.234,56');
    fixture.detectChanges();
    assert.deepEqual(payload(fixture), {
        'q42:7_debitaccount_0': '101', 'q42:7_debitamount_0': '1.234,56',
        'q42:7_creditaccount_0': '201', 'q42:7_creditamount_0': '1.234,56',
    });
    // Model a server response after Check, then render it as a fresh app component.
    const saved = Object.fromEntries(Object.entries(payload(fixture)).map(([key, value]) => [key.slice('q42:7_'.length), value]));
    fixture.destroy();
    TestBed.resetTestingModule();
    const resumed = await render({saved});
    assert.equal(payload(resumed)['q42:7_debitamount_0'], '1.234,56');
    assert.equal(payload(resumed)['q42:7_creditaccount_0'], '201');
});

test('added entries submit once, and deleting a saved entry submits explicit empty values', async () => {
    const fixture = await render({saved: {debitaccount_0: '101', debitamount_0: '100,00'}});
    action(fixture, 'adddebitentry');
    let cards = fixture.nativeElement.querySelectorAll('ion-card');
    input(cards[1].querySelector('ion-select'), '201', 'ionChange');
    input(cards[1].querySelector('ion-input'), '20,00');
    fixture.detectChanges();
    action(fixture, 'deleteentry');
    const answers = payload(fixture);
    assert.equal(answers['q42:7_debitaccount_0'], '');
    assert.equal(answers['q42:7_debitamount_0'], '');
    assert.equal(answers['q42:7_debitaccount_1'], '201');
    assert.equal(answers['q42:7_debitamount_1'], '20,00');
    const names = Array.from(fixture.nativeElement.querySelector('form').elements).map(field => field.name);
    assert.equal(new Set(names).size, names.length, 'Each answer must have exactly one native form field');
});

test('account fallback and US amounts use Ionic controls and serialize before blur', async () => {
    const fixture = await render({accounts: false, format: 'us'});
    const card = fixture.nativeElement.querySelector('ion-card');
    const inputs = card.querySelectorAll('ion-input');
    input(inputs[0], '101');
    input(inputs[1], '1,234.56');
    fixture.detectChanges();
    assert.equal(payload(fixture)['q42:7_debitaccount_0'], '101');
    assert.equal(payload(fixture)['q42:7_debitamount_0'], '1,234.56');
    assert.equal(fixture.nativeElement.querySelectorAll('select, input:not([type="hidden"])').length, 0);
});

test('local added rows are restored in the actual form; review exposes no editing actions', async () => {
    const local = {debitaccount_2: '201', debitamount_2: '50,00'};
    const fixture = await render({local});
    assert.equal(payload(fixture)['q42:7_debitamount_2'], '50,00');
    fixture.destroy();
    TestBed.resetTestingModule();
    const review = await render({readonly: true, saved: {debitaccount_0: '101', debitamount_0: '100,00'}, local});
    assert.equal(review.nativeElement.querySelectorAll('ion-button, ion-input, ion-select').length, 0);
    assert.equal(payload(review)['q42:7_debitamount_0'], '100,00');
    assert.ok(!Object.hasOwn(payload(review), 'q42:7_debitaccount_2'));
});

test('delete buttons exist only when that side has more than one visible entry', async () => {
    const fixture = await render({saved: {debitaccount_0: '101', debitamount_0: '100,00',
        creditaccount_0: '201', creditamount_0: '100,00'}});
    const before = payload(fixture);
    const deleteButtons = () => Array.from(fixture.nativeElement.querySelectorAll('ion-card ion-button'));
    const sideButtons = side => fixture.nativeElement.querySelectorAll('.qtype_accounting-app > section')[side]
        .querySelectorAll('ion-card ion-button');
    assert.equal(deleteButtons().length, 0);
    assert.equal(fixture.nativeElement.querySelectorAll('ion-card').length, 2);
    assert.deepEqual(payload(fixture), before);

    action(fixture, 'adddebitentry');
    assert.equal(sideButtons(0).length, 2);
    assert.equal(sideButtons(1).length, 0);
    action(fixture, 'deleteentry');
    assert.equal(fixture.nativeElement.querySelectorAll('ion-card').length, 2);
    assert.equal(deleteButtons().length, 0);
    assert.equal(payload(fixture)['q42:7_debitaccount_0'], '');
    assert.equal(payload(fixture)['q42:7_creditaccount_0'], '201');

    action(fixture, 'addcreditentry');
    assert.equal(sideButtons(0).length, 0);
    assert.equal(sideButtons(1).length, 2);
    action(fixture, 'deleteentry');
    assert.equal(deleteButtons().length, 0);
    assert.equal(fixture.nativeElement.querySelectorAll('ion-card').length, 2);
});
