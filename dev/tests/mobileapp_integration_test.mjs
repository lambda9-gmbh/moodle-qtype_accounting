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

const dependencies = process.env.ACCOUNTING_MOBILE_TEST_DEPS;
if (!dependencies) {
    throw new Error('Set ACCOUNTING_MOBILE_TEST_DEPS to the Nix test dependency output (see README.md).');
}
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

function serverHtml({saved = {}, readonly = false, format = 'de', accounts = true} = {}) {
    const fields = ['debit', 'credit'].map(side => {
        const accountKey = side + 'account_0';
        const amountKey = side + 'amount_0';
        const account = accounts && !readonly
            ? `<select name="q42:7_${accountKey}"><option value="">Choose</option>` +
                `<option value="101" ${saved[accountKey] === '101' ? 'selected' : ''}>Bank</option>` +
                `<option value="201" ${saved[accountKey] === '201' ? 'selected' : ''}>Sales</option></select>`
            : `<input name="q42:7_${accountKey}" value="${saved[accountKey] || ''}">`;
        return `<div><span class="qtype_accounting-readonly">Bank</span>${account}</div>` +
            `<div><span class="qtype_accounting-readonly">100,00</span>` +
            `<input name="q42:7_${amountKey}" value="${saved[amountKey] || ''}"></div>`;
    }).join('');
    return '<div class="qtext">Record the booking</div>' +
        `<div class="qtype_accounting-question-container" data-allowedit="${readonly ? '0' : '1'}"` +
        ` data-maxrows="3" data-numberformat="${format}">${fields}</div>`;
}

async function render(options = {}) {
    TestBed.configureTestingModule({imports: [QuizHost], providers: [provideZonelessChangeDetection()]});
    await TestBed.compileComponents();
    const fixture = TestBed.createComponent(QuizHost);
    const instance = fixture.componentInstance;
    instance.question = {html: serverHtml(options), localAnswers: options.local || {}};
    const handler = vm.runInNewContext(controller, {
        document: dom.window.document,
        CoreQuestionHelperProvider: {
            showComponentError() { throw new Error('Question aborted'); },
            replaceCorrectnessClasses() {},
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
