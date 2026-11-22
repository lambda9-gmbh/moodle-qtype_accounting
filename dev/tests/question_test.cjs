// Run with ./dev/scripts/js-tests.sh dom using the optional Docker test image.
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const read = file => readFileSync(path.join(root, file), 'utf8');

function row(index, type = 'both') {
    const cells = ['<td></td>'];
    for (const side of ['debit', 'credit']) {
        cells.push(`<td><select class="qtype_accounting-account-select" name="${side}account_${index}">
            <option value=""></option><option value="bank" selected>Bank</option></select></td>
            <td><input class="qtype_accounting-amount-input" name="${side}amount_${index}" value="100"></td>
            <td><button type="button" class="qtype_accounting-delete-${side}" data-entry="${index}"></button></td>`);
    }
    return `<tr class="qtype_accounting-entry-row" data-entry="${index}" data-entry-type="${type}">
        ${cells.join('')}</tr><tr class="qtype_accounting-explanation-row"><td>Explanation</td></tr>`;
}

function question() {
    const dom = new JSDOM(`<style>${read('styles.css')}</style><form>
        <div id="question" class="qtype_accounting-question-container" data-allowedit="1"
            data-numberformat="de" data-templateid="entry-template" data-maxrows="3">
            <table class="qtype_accounting-student-table"><tbody class="qtype_accounting-entries">
                ${row(0)}${row(1)}</tbody></table>
            <div class="qtype_accounting-mobile-view"></div>
            <button type="button" class="qtype_accounting-add-debit-entry"></button>
        </div><template id="entry-template">${row('__INDEX__', 'debit')}</template></form>`,
    {runScripts: 'outside-only'});
    const win = dom.window;
    win.eval(readFileSync(process.env.ACCOUNTING_JQUERY || require.resolve('jquery/dist/jquery.js'), 'utf8'));
    win.M = {util: {get_string: key => key}};
    const load = (file, name, exports) => win.eval(`window.${name} = (() => {
        const $ = window.jQuery;
        ${read(file).replace(/^import .*;$/gm, '').replace(/export \{[\s\S]*?\};/, '')}
        return {${exports}};
    })();`);
    load('amd/src/entry_utils.js', 'EntryUtils',
        'formatNumber, isRowEmpty, detectEntryType, applyEntryTypeVisibility, updatePerLabels, ' +
        'findIncompleteRow, countSideRows, clearEntrySideFields, HIDDEN_CLASS');
    load('amd/src/mobile_layout.js', 'MobileLayout', 'init, refreshMobileView, buildMobileView, destroyMobileView');
    load('amd/src/question.js', 'Question', 'init');
    win.Question.init('question');
    const container = win.jQuery('#question');
    return {win, container, close: () => win.close()};
}

test('deleted rows stay hidden, are excluded from mobile cards, and their indices can be reused', () => {
    const {win, container, close} = question();
    try {
        container.find('.qtype_accounting-delete-debit[data-entry="1"]').trigger('click');
        container.find('.qtype_accounting-delete-credit[data-entry="1"]').trigger('click');
        const deleted = container.find('.qtype_accounting-entry-row[data-entry="1"]')[0];
        assert.equal(win.getComputedStyle(deleted).display, 'none');
        assert.equal(win.getComputedStyle(deleted.nextElementSibling).display, 'none');
        assert.equal(win.getComputedStyle(container.find('.qtype_accounting-delete-debit')[0]).visibility, 'hidden');
        win.MobileLayout.buildMobileView(container);
        assert.equal(container.find('.qtype_accounting-mobile-card').length, 2);
        win.MobileLayout.destroyMobileView(container);
        container.find('> .qtype_accounting-add-debit-entry').trigger('click');
        const replacement = container.find('.qtype_accounting-entry-row[data-entry="1"]');
        assert.equal(replacement.length, 1);
        assert.equal(replacement.attr('data-entry-type'), 'debit');
        assert.notEqual(win.getComputedStyle(replacement[0]).display, 'none');
        assert.equal(win.getComputedStyle(container.find('.qtype_accounting-delete-debit')[0]).visibility, 'visible');
    } finally {
        close();
    }
});

test('mobile native dropdowns synchronize with the submitted controls and layout can be rebuilt', () => {
    const {win, container, close} = question();
    try {
        win.MobileLayout.buildMobileView(container);
        const proxy = container.find('.qtype_accounting-mobile-proxy').filter('select').first();
        proxy.val('').trigger('change');
        assert.equal(container.find('select[name="debitaccount_0"]').val(), '');
        win.MobileLayout.destroyMobileView(container);
        assert.equal(container.hasClass('qtype_accounting-mobile-active'), false);
        assert.equal(container.find('.qtype_accounting-mobile-view').children().length, 0);
        assert.equal(win.getComputedStyle(container.find('.qtype_accounting-mobile-view')[0]).display, 'none');
        win.MobileLayout.buildMobileView(container);
        assert.equal(container.find('.qtype_accounting-mobile-card').length, 4);
        assert.equal(container.find('.qtype_accounting-mobile-view')[0].hasAttribute('style'), false);
    } finally {
        close();
    }
});
