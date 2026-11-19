// Run with ./dev/scripts/js-tests.sh basic (Node runs in the Moodle container).
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

// The number helpers have no DOM dependency. Keep the production module intact,
// replacing only its external jQuery import for this isolated test environment.
const source = readFileSync(path.join(__dirname, '../../amd/src/entry_utils.js'), 'utf8')
    .replace("import $ from 'jquery';", 'const $ = () => {};');
const utilities = import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));

test('German grouping is interpreted using the configured format', async () => {
    const {parseNumber, formatNumber} = await utilities;
    assert.equal(parseNumber('1.000', 'de'), '1000');
    assert.equal(parseNumber('1.234,56', 'de'), '1234.56');
    assert.equal(formatNumber('1.000', 'de', 2), '1.000,00');
});

test('US grouping and decimal input stay independent of German formatting', async () => {
    const {parseNumber, formatNumber} = await utilities;
    assert.equal(parseNumber('1,000', 'us'), '1000');
    assert.equal(parseNumber('1,234.56', 'us'), '1234.56');
    assert.equal(formatNumber('1.000', 'us', 2), '1.00');
    assert.equal(formatNumber('1.000', 'de', 2), '1.000,00');
});

test('canonical numbers format correctly without being parsed as display input', async () => {
    const {parseNumber, formatNumber} = await utilities;
    assert.equal(formatNumber(1000.5, 'de', 2), '1.000,50');
    assert.equal(formatNumber(1000.5, 'us', 2), '1,000.50');
    assert.equal(parseNumber('', 'de'), '');
    assert.equal(formatNumber('', 'de', 2), '');
    assert.equal(formatNumber(0, 'de', 2), '0,00');
});

test('monetary arithmetic uses integer cents, including large totals', async () => {
    const {parseCents, formatCents, formatNumber} = await utilities;
    for (const format of ['de', 'us']) {
        const decimal = value => value.replace('.', format === 'de' ? ',' : '.');
        assert.equal(parseCents(decimal('0.10'), format) + parseCents(decimal('0.20'), format), 30n);
        assert.equal(parseCents(decimal('1.005'), format), 101n);
        assert.equal(parseCents(decimal('-1.005'), format), -101n);
        assert.equal(parseCents(decimal('9.995'), format), 1000n);
        assert.equal(parseCents('', format), null);
        assert.equal(parseCents('invalid', format), null);
        const maximum = 999999999999999n;
        const display = formatCents(maximum, format);
        assert.equal(parseCents(display, format), maximum);
        assert.equal(formatNumber(display, format), display);
        assert.equal(parseCents(display, format) * 100n, 99999999999999900n);
        assert.equal(formatNumber(decimal('1.005'), format), decimal('1.01'));
    }
});
