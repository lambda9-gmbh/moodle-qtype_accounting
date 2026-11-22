// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Mobile layout module for the Accounting Entry question type.
 *
 * Creates a card-based mobile view with separate debit and credit sections.
 * Uses proxy inputs that sync to hidden real form fields in the table.
 *
 * @module     qtype_accounting/mobile_layout
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import {getStrings} from 'core/str';
import Pending from 'core/pending';

// Breakpoint for mobile layout (must match CSS).
let MOBILE_BREAKPOINT = 768;

// Below this question width the entry table does not fit, e.g. next to open drawers.
const COMPACT_WIDTH = 600;

// CSS class added to container when mobile layout is active.
let ACTIVE_CLASS = 'qtype_accounting-mobile-active';

// Keep each question's layout options and resize timer independent.
const questionSettings = new WeakMap();

const STRING_KEYS = [
    'debitentries', 'creditentries', 'adddebitentry', 'addcreditentry',
    'account', 'amount', 'debit', 'credit', 'deletedebit', 'deletecredit',
];

// Labels keyed by language string; the keys are used until the strings have loaded.
let strings = Object.fromEntries(STRING_KEYS.map(key => [key, key]));

/**
 * Load the labels of the card view.
 *
 * @return {Promise} Resolved when the labels are available.
 */
const loadStrings = async() => {
    const values = await getStrings(STRING_KEYS.map(key => ({key, component: 'qtype_accounting'})));
    strings = Object.fromEntries(STRING_KEYS.map((key, i) => [key, values[i]]));
};

// Each card field gets a unique ID, so that its label can refer to it.
let fieldCounter = 0;

/**
 * Whether the card view should be used for a question.
 *
 * @param {jQuery} container The question container element.
 * @return {boolean} True for narrow viewports or narrow question containers.
 */
function isCompact(container) {
    return window.innerWidth <= MOBILE_BREAKPOINT || container[0].clientWidth < COMPACT_WIDTH;
}

/**
 * Initialize mobile layout management.
 *
 * Watches the viewport and the question width and builds the mobile view if the
 * question is already too narrow for the table.
 *
 * @param {jQuery} container The question container element.
 * @param {string} selector CSS selector for entry rows.
 * @param {boolean} readonly Whether the question is in readonly mode.
 */
function init(container, selector, readonly) {
    const settings = {
        selector,
        readonly,
        numberFormat: container.data('numberformat') || 'de',
        // Currency symbol shown after the amount inputs ('' for none).
        currencySymbol: String(container.data('currencysymbol') || ''),
        resizeTimer: null,
    };
    questionSettings.set(container[0], settings);

    // Re-check on window resizes and on width changes of the question, e.g. when drawers open.
    const scheduleResize = function() {
        clearTimeout(settings.resizeTimer);
        settings.resizeTimer = setTimeout(function() {
            handleResize(container);
        }, 150);
    };
    $(window).on('resize', scheduleResize);
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(scheduleResize).observe(container[0]);
    }

    // Build mobile view if already too narrow, once its labels are available.
    const pending = new Pending('qtype_accounting/mobile_layout:init');
    loadStrings().catch(() => null).finally(() => {
        if (isCompact(container)) {
            buildMobileView(container);
        }
        pending.resolve();
    });
}

/**
 * Handle viewport and question resizes to toggle between mobile and desktop layouts.
 *
 * @param {jQuery} container The question container element.
 */
function handleResize(container) {
    let isMobile = isCompact(container);
    let isActive = container.hasClass(ACTIVE_CLASS);

    if (isMobile && !isActive) {
        buildMobileView(container);
    } else if (!isMobile && isActive) {
        destroyMobileView(container);
    }
}

/**
 * Build the mobile view: hide the table and show card-based sections.
 *
 * @param {jQuery} container The question container element.
 */
function buildMobileView(container) {
    const settings = questionSettings.get(container[0]);
    if (!settings) {
        return;
    }
    // Find or create the mobile view container.
    let mobileView = container.find('.qtype_accounting-mobile-view');
    if (mobileView.length === 0) {
        return;
    }

    // Clear any existing content.
    mobileView.empty();

    const section = side => {
        const element = $('<div class="qtype_accounting-mobile-section">').attr('data-section', side);
        element.append($('<div class="qtype_accounting-mobile-section-header">')
            .addClass('qtype_accounting-mobile-' + side + '-header')
            .append($('<h4>').text(strings[side + 'entries'])));
        const cards = $('<div class="qtype_accounting-mobile-cards">');
        element.append(cards);
        return [element, cards];
    };
    const [debitSection, debitCards] = section('debit');
    const [creditSection, creditCards] = section('credit');

    // Iterate visible entry rows and create cards.
    container.find(settings.selector).each(function() {
        let $row = $(this);
        if ($row.hasClass('qtype_accounting-deleted-row')) {
            return; // Skip hidden rows.
        }

        let entryIndex = $row.attr('data-entry');
        let entryType = $row.attr('data-entry-type') || 'both';

        if (entryType === 'debit' || entryType === 'both') {
            let debitCard = createMobileCard($row, 'debit', entryIndex, settings);
            debitCards.append(debitCard);
        }

        if (entryType === 'credit' || entryType === 'both') {
            let creditCard = createMobileCard($row, 'credit', entryIndex, settings);
            creditCards.append(creditCard);
        }
    });

    // Each side keeps at least one entry.
    [debitCards, creditCards].forEach(function(cards) {
        if (cards.children().length <= 1) {
            cards.find('.qtype_accounting-mobile-card-actions').hide();
        }
    });

    mobileView.append(debitSection);
    mobileView.append(creditSection);

    // Add buttons (only in edit mode).
    if (!settings.readonly) {
        [[debitSection, 'debit'], [creditSection, 'credit']].forEach(([element, side]) => {
            element.append($('<button type="button" class="btn btn-secondary">')
                .addClass('qtype_accounting-add-' + side + '-entry')
                .text(strings['add' + side + 'entry']));
        });
    }

    // Activate mobile layout.
    container.addClass(ACTIVE_CLASS);
}

/**
 * Create a mobile card for one side of an entry.
 *
 * @param {jQuery} $row The table row element.
 * @param {string} side The side: 'debit' or 'credit'.
 * @param {string} entryIndex The entry index from data-entry attribute.
 * @param {Object} settings Options for this question.
 * @return {jQuery} The card element.
 */
function createMobileCard($row, side, entryIndex, settings) {
    const cells = $row.find('td');
    // Debit account and amount are cells 1 and 2, credit account and amount cells 4 and 5.
    const accountCell = cells.eq(side === 'debit' ? 1 : 4);
    const amountCell = cells.eq(side === 'debit' ? 2 : 5);

    const card = $('<div class="qtype_accounting-mobile-card" role="group">')
        .attr('data-entry', entryIndex)
        .attr('data-side', side)
        .attr('aria-label', strings[side] + ' ' + (parseInt(entryIndex, 10) + 1));
    const cardBody = $('<div class="qtype_accounting-mobile-card-body">');

    if (settings.readonly) {
        // Readonly mode: clone display spans.
        cardBody.append(createReadonlyField(strings.account, accountCell));
        cardBody.append(createReadonlyField(strings.amount, amountCell));
    } else {
        // Edit mode: create proxy inputs.
        cardBody.append(createProxyAccountField(strings.account, accountCell));
        cardBody.append(createProxyAmountField(strings.amount, amountCell, settings.numberFormat, settings.currencySymbol));
    }
    card.append(cardBody);

    if (!settings.readonly) {
        const label = strings['delete' + side];
        const button = $('<button type="button" class="btn btn-sm btn-outline-danger">')
            .addClass('qtype_accounting-delete-' + side)
            .attr('data-entry', entryIndex)
            .attr('title', label)
            .attr('aria-label', label)
            .append($('<i class="fa fa-trash" aria-hidden="true">'));
        card.append($('<div class="qtype_accounting-mobile-card-actions">').append(button));
    }

    return card;
}

/**
 * Create the wrapper of a card field with a label for the given control ID.
 *
 * @param {string} label The field label text.
 * @param {string|null} controlId The ID of the labelled control, or null for text that is not a control.
 * @return {jQuery} The field wrapper element.
 */
function createField(label, controlId) {
    const field = $('<div class="qtype_accounting-mobile-field">');
    const labelElement = controlId ? $('<label>').attr('for', controlId) : $('<span class="qtype_accounting-mobile-label">');
    field.append(labelElement.text(label));
    return field;
}

/**
 * Create a readonly field display for mobile cards.
 *
 * @param {string} label The field label text.
 * @param {jQuery} cell The original table cell containing the readonly span.
 * @return {jQuery} The field wrapper element.
 */
function createReadonlyField(label, cell) {
    const field = createField(label, null);
    const span = cell.find('.qtype_accounting-readonly').first();
    field.append(span.length ? span.clone() : $('<span class="qtype_accounting-readonly">'));
    // Keep the correctness text for screen readers that follows the reviewed value.
    field.append(cell.find('.accesshide').clone());
    return field;
}

/**
 * Create a proxy account select field that syncs to the real hidden field.
 *
 * @param {string} label The field label text.
 * @param {jQuery} cell The original table cell containing the real select.
 * @return {jQuery} The field wrapper element.
 */
function createProxyAccountField(label, cell) {
    const id = 'qtype_accounting-mobile-field-' + (++fieldCounter);
    const field = createField(label, id);

    const realSelect = cell.find('select').first();
    if (realSelect.length === 0) {
        // Text input fallback (no accounts available).
        const realInput = cell.find('input').first();
        const proxyInput = $('<input type="text" class="form-control qtype_accounting-mobile-proxy">')
            .attr('id', id)
            .val(realInput.val())
            .attr('placeholder', realInput.attr('placeholder') || '');
        proxyInput.on('input', function() {
            realInput.val($(this).val());
        });
        field.append(proxyInput);
        return field;
    }

    // Clone the select element (options and all) without its name.
    const proxySelect = realSelect.clone()
        .removeAttr('name')
        .removeAttr('aria-label')
        .attr('id', id)
        .addClass('qtype_accounting-mobile-proxy');
    proxySelect.val(realSelect.val());
    proxySelect.on('change', function() {
        realSelect.val($(this).val()).trigger('change');
    });

    field.append(proxySelect);
    return field;
}

/**
 * Create a proxy amount input field that syncs to the real hidden field.
 *
 * @param {string} label The field label text.
 * @param {jQuery} cell The original table cell containing the real input.
 * @param {string} numberFormat Amount format for this question.
 * @param {string} currencySymbol Currency symbol shown after the input ('' for none).
 * @return {jQuery} The field wrapper element.
 */
function createProxyAmountField(label, cell, numberFormat, currencySymbol) {
    const id = 'qtype_accounting-mobile-field-' + (++fieldCounter);
    const field = createField(label, id);

    const realInput = cell.find('input').first();
    const proxyInput = $('<input type="text" class="form-control qtype_accounting-mobile-proxy qtype_accounting-amount-input">')
        .attr('id', id)
        .val(realInput.val())
        .attr('placeholder', numberFormat === 'us' ? '0.00' : '0,00')
        .attr('inputmode', 'decimal');

    // Sync proxy → real on input.
    proxyInput.on('input', function() {
        realInput.val($(this).val());
    });

    // The amount formatting handler in question.js fires on blur; sync the formatted value afterwards.
    proxyInput.on('blur', function() {
        setTimeout(function() {
            realInput.val(proxyInput.val());
        }, 0);
    });

    if (currencySymbol) {
        const group = $('<div class="qtype_accounting-amount-group">').append(proxyInput);
        group.append($('<span class="qtype_accounting-currency" aria-hidden="true">').text(currencySymbol));
        field.append(group);
    } else {
        field.append(proxyInput);
    }
    return field;
}

/**
 * Destroy the mobile view and restore the desktop table.
 *
 * @param {jQuery} container The question container element.
 */
function destroyMobileView(container) {
    container.removeClass(ACTIVE_CLASS);
    let mobileView = container.find('.qtype_accounting-mobile-view');
    mobileView.empty();
}

/**
 * Refresh the mobile view after entry changes (add/delete).
 *
 * Only rebuilds if mobile layout is currently active.
 *
 * @param {jQuery} container The question container element.
 */
function refreshMobileView(container) {
    if (!container.hasClass(ACTIVE_CLASS)) {
        return;
    }
    buildMobileView(container);
}

/**
 * Check if the mobile layout is currently active.
 *
 * @param {jQuery} container The question container element.
 * @return {boolean} True if mobile layout is active.
 */
function isMobileActive(container) {
    return container.hasClass(ACTIVE_CLASS);
}

export {init, buildMobileView, destroyMobileView, refreshMobileView, isMobileActive};
