// Run with node --test dev/tests/entry_utils_test.cjs inside the Nix development shell.
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
