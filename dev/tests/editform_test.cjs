// Run with ./dev/scripts/js-tests.sh dom using the optional Docker test image.
const {readFileSync} = require('node:fs');
const {execFileSync} = require('node:child_process');
const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const vm = require('node:vm');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const read = file => process.env.ACCOUNTING_EDITFORM_TEST_REVISION
    ? execFileSync('git', ['show', process.env.ACCOUNTING_EDITFORM_TEST_REVISION + ':' + file],
        {cwd: root, encoding: 'utf8'})
    : readFileSync(path.join(root, file), 'utf8');

test('balance validation uses cents for split amounts and large totals in both formats', () => {
    for (const format of ['de', 'us']) {
        const dom = new JSDOM(`<form><table class="qtype_accounting-edit-table"><tbody>
            ${[0, 1].map(index => `<tr class="qtype_accounting-entry-row" data-entry-index="${index}">
                <td><input class="qtype_accounting-debitamount"></td>
                <td><input class="qtype_accounting-creditamount"></td></tr>`).join('')}
            </tbody></table></form>`);
        const document = dom.window.document;
        const context = vm.createContext({document, M: {util: {get_string: key => key}}});
        vm.runInContext(read('amd/src/entry_utils.js')
            .replace(/^import .*;$/gm, '').replace(/export \{[\s\S]*?\};/, '') +
            ';globalThis.EntryUtils = {parseCents};', context);
        vm.runInContext(read('amd/src/editform.js')
            .replace(/^import .*;$/gm, '').replace('export {init};', '') +
            `;editForm = document.querySelector('form'); numberFormatSelect = {value: '${format}'};`, context);
        const debits = document.querySelectorAll('.qtype_accounting-debitamount');
        const credits = document.querySelectorAll('.qtype_accounting-creditamount');
        const decimal = value => value.replace('.', format === 'de' ? ',' : '.');
        debits[0].value = decimal('0.10');
        debits[1].value = decimal('0.20');
        credits[0].value = decimal('0.30');
        assert.equal(vm.runInContext('validateBalance()', context), true);
        credits[0].value = decimal('0.31');
        assert.equal(vm.runInContext('validateBalance()', context), false);
        debits[0].value = decimal('9999999999999.99');
        debits[1].value = decimal('9999999999999.98');
        credits[0].value = decimal('9999999999999.99');
        credits[1].value = decimal('9999999999999.98');
        assert.equal(vm.runInContext('validateBalance()', context), true);
        credits[1].value = decimal('9999999999999.99');
        assert.equal(vm.runInContext('validateBalance()', context), false);
    }
});

function row(index, type = 'both') {
    return `<tr class="qtype_accounting-entry-row" data-entry-index="${index}" data-entry-type="${type}">
        <td></td><td><input value="bank"></td><td><input value="100"></td>
        <td><button type="button" class="qtype_accounting-delete-debit" data-index="${index}"></button></td>
        <td><input value="cash"></td><td><input value="100"></td>
        <td><button type="button" class="qtype_accounting-delete-credit" data-index="${index}"></button></td>
        </tr><tr class="qtype_accounting-weight-row" data-entry-index="${index}">
        ${'<td></td>'.repeat(7)}</tr>`;
}

function form() {
    const dom = new JSDOM(`<form><button type="button" id="qtype_accounting-add-debit-entry"
        class="qtype_accounting-add-debit-entry"></button>
        <button type="button" id="qtype_accounting-add-credit-entry"
        class="qtype_accounting-add-credit-entry"></button>
        <table><tbody id="qtype_accounting-entries-body">${row(0)}</tbody></table>
        <template id="qtype_accounting-entry-template">${row('__INDEX__')}</template></form>`);
    const document = dom.window.document;
    const context = vm.createContext({document, console, $: () => ({trigger() {}})});
    vm.runInContext(read('amd/src/entry_utils.js')
        .replace(/^import .*;$/gm, '').replace(/export \{[\s\S]*?\};/, '') +
        ';globalThis.EntryUtils = {applyEntryTypeVisibility, applyWeightRowVisibility, ' +
        'clearEntrySideFields, updatePerLabels, findIncompleteRow};', context);
    vm.runInContext(read('amd/src/editform.js')
        .replace(/^import .*;$/gm, '').replace('export {init};', '') +
        ';editForm = document.querySelector("form"); nextEntryIndex = 1;' +
        'setupDeleteEntryHandler(); setupAddEntryHandler(); updateDeleteButtonStates();', context);
    return {
        add(side) { document.getElementById(`qtype_accounting-add-${side}-entry`).click(); },
        remove(index, side) { vm.runInContext(`deleteEntrySide(${index}, '${side}')`, context); },
        button(index, side) {
            return document.querySelector(`.qtype_accounting-delete-${side}[data-index="${index}"]`);
        },
        type(index) {
            return document.querySelector(`[data-entry-index="${index}"]`)?.getAttribute('data-entry-type');
        },
    };
}

for (const side of ['debit', 'credit']) {
    const other = side === 'debit' ? 'credit' : 'debit';

    test(`${side}: adding entries leaves the opposite side protected`, () => {
        const ui = form();
        assert.equal(ui.button(0, side).disabled, true);
        ui.add(side);
        assert.equal(ui.button(0, side).disabled, false);
        assert.equal(ui.button(1, side).disabled, false);
        assert.equal(ui.button(0, other).disabled, true);
        // Exercise the deletion guard independently of disabled browser buttons.
        ui.remove(0, other);
        assert.equal(ui.type(0), 'both');
        ui.button(1, side).click();
        assert.equal(ui.type(1), undefined);
        assert.equal(ui.button(0, side).disabled, true);
        assert.equal(ui.button(0, other).disabled, true);
    });

    test(`${side}: deleting the original entry protects a remaining entry at a higher index`, () => {
        const ui = form();
        ui.add(side);
        ui.button(0, side).click();
        assert.equal(ui.type(0), other);
        assert.equal(ui.type(1), side);
        assert.equal(ui.button(1, side).disabled, true);
        assert.equal(ui.button(0, other).disabled, true);
        ui.remove(1, side);
        assert.equal(ui.type(1), side);
        // Reusing an incomplete row must enable deletion again for that side.
        ui.add(side);
        assert.equal(ui.type(0), 'both');
        assert.equal(ui.button(0, side).disabled, false);
        assert.equal(ui.button(1, side).disabled, false);
        assert.equal(ui.button(0, other).disabled, true);
        ui.button(1, side).click();
        assert.equal(ui.type(1), undefined);
        assert.equal(ui.button(0, side).disabled, true);
    });
}
