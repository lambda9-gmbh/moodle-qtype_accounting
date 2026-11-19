// Run with node --test dev/tests/editform_test.cjs in the Nix development shell.
// Provide jsdom from the existing dev/tests/mobileapp Nix dependencies via NODE_PATH.
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
