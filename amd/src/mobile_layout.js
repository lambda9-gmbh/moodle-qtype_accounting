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

// Breakpoint for mobile layout (must match CSS).
let MOBILE_BREAKPOINT = 768;

// CSS class added to container when mobile layout is active.
let ACTIVE_CLASS = 'qtype_accounting-mobile-active';

// Keep each question's layout options and resize timer independent.
const questionSettings = new WeakMap();

/**
 * Initialize mobile layout management.
 *
 * Sets up a resize listener and builds the mobile view if the viewport
 * is already at mobile width.
 *
 * @param {jQuery} container The question container element.
 * @param {string} selector CSS selector for entry rows.
 * @param {boolean} readonly Whether the question is in readonly mode.
 */
function init(container, selector, readonly) {
    const settings = {selector, readonly, numberFormat: container.data('numberformat') || 'de', resizeTimer: null};
    questionSettings.set(container[0], settings);

    // Set up resize handler.
    $(window).on('resize', function() {
        clearTimeout(settings.resizeTimer);
        settings.resizeTimer = setTimeout(function() {
            handleResize(container);
        }, 300);
    });

    // Build mobile view if already at mobile width.
    if (window.innerWidth <= MOBILE_BREAKPOINT) {
        buildMobileView(container);
    }
}

/**
 * Handle window resize to toggle between mobile and desktop layouts.
 *
 * @param {jQuery} container The question container element.
 */
function handleResize(container) {
    let isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
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

    // Get translated strings.
    let debitLabel = M.util.get_string('debitentries', 'qtype_accounting');
    let creditLabel = M.util.get_string('creditentries', 'qtype_accounting');
    let addDebitLabel = M.util.get_string('adddebitentry', 'qtype_accounting');
    let addCreditLabel = M.util.get_string('addcreditentry', 'qtype_accounting');

    // Create debit section.
    let debitSection = $('<div class="qtype_accounting-mobile-section" data-section="debit">');
    debitSection.append(
        '<div class="qtype_accounting-mobile-section-header qtype_accounting-mobile-debit-header">' +
        '<h4>' + debitLabel + '</h4></div>'
    );
    let debitCards = $('<div class="qtype_accounting-mobile-cards">');
    debitSection.append(debitCards);

    // Create credit section.
    let creditSection = $('<div class="qtype_accounting-mobile-section" data-section="credit">');
    creditSection.append(
        '<div class="qtype_accounting-mobile-section-header qtype_accounting-mobile-credit-header">' +
        '<h4>' + creditLabel + '</h4></div>'
    );
    let creditCards = $('<div class="qtype_accounting-mobile-cards">');
    creditSection.append(creditCards);

    // Iterate visible entry rows and create cards.
    container.find(settings.selector).each(function() {
        let $row = $(this);
        if ($row.css('display') === 'none') {
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

    mobileView.append(debitSection);
    mobileView.append(creditSection);

    // Add buttons (only in edit mode).
    if (!settings.readonly) {
        debitSection.append(
            '<button type="button" class="btn btn-secondary qtype_accounting-add-debit-entry">' +
            addDebitLabel + '</button>'
        );
        creditSection.append(
            '<button type="button" class="btn btn-secondary qtype_accounting-add-credit-entry">' +
            addCreditLabel + '</button>'
        );
    }

    // Activate mobile layout.
    container.addClass(ACTIVE_CLASS);
    mobileView.show();
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
    let accountLabel = M.util.get_string('account', 'qtype_accounting');
    let amountLabel = M.util.get_string('amount', 'qtype_accounting');

    let cells = $row.find('td');

    // Determine which cells to read based on side.
    let accountCell, amountCell;
    if (side === 'debit') {
        accountCell = cells.eq(1); // Debit Account (cell index 1).
        amountCell = cells.eq(2); // Debit Amount (cell index 2).
    } else {
        accountCell = cells.eq(4); // Credit Account (cell index 4).
        amountCell = cells.eq(5); // Credit Amount (cell index 5).
    }

    // Build the card.
    let sideLabel = (side === 'debit')
        ? M.util.get_string('debit', 'qtype_accounting')
        : M.util.get_string('credit', 'qtype_accounting');

    let card = $('<div class="qtype_accounting-mobile-card" data-entry="' + entryIndex +
        '" data-side="' + side + '" role="group" aria-label="' + sideLabel + ' ' + (parseInt(entryIndex, 10) + 1) + '">');

    let cardBody = $('<div class="qtype_accounting-mobile-card-body">');

    if (settings.readonly) {
        // Readonly mode: clone display spans.
        cardBody.append(createReadonlyField(accountLabel, accountCell));
        cardBody.append(createReadonlyField(amountLabel, amountCell));
    } else {
        // Edit mode: create proxy inputs.
        cardBody.append(createProxyAccountField(accountLabel, accountCell));
        cardBody.append(createProxyAmountField(amountLabel, amountCell, settings.numberFormat));
    }

    card.append(cardBody);

    // Add delete button in edit mode.
    if (!settings.readonly) {
        let deleteClass = (side === 'debit') ? 'qtype_accounting-delete-debit' : 'qtype_accounting-delete-credit';
        let actionsDiv = $('<div class="qtype_accounting-mobile-card-actions">');
        actionsDiv.append(
            '<button type="button" class="btn btn-sm btn-outline-danger ' + deleteClass +
            '" data-entry="' + entryIndex + '" title="' + sideLabel + '">' +
            '<i class="fa fa-trash"></i></button>'
        );
        card.append(actionsDiv);
    }

    return card;
}

/**
 * Create a readonly field display for mobile cards.
 *
 * @param {string} label The field label text.
 * @param {jQuery} cell The original table cell containing the readonly span.
 * @return {jQuery} The field wrapper element.
 */
function createReadonlyField(label, cell) {
    let field = $('<div class="qtype_accounting-mobile-field">');
    field.append('<label>' + label + '</label>');

    let span = cell.find('.qtype_accounting-readonly').first();
    if (span.length) {
        field.append(span.clone());
    } else {
        field.append('<span class="qtype_accounting-readonly"></span>');
    }

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
    let field = $('<div class="qtype_accounting-mobile-field">');
    field.append('<label>' + label + '</label>');

    let realSelect = cell.find('select').first();
    if (realSelect.length === 0) {
        // Text input fallback (no accounts available).
        let realInput = cell.find('input').first();
        let proxyInput = $('<input type="text" class="form-control qtype_accounting-mobile-proxy">')
            .val(realInput.val())
            .attr('placeholder', realInput.attr('placeholder') || '')
            .attr('aria-label', label);

        proxyInput.on('input', function() {
            realInput.val($(this).val());
        });

        field.append(proxyInput);
        return field;
    }

    // Clone the select element (options and all), remove name/id.
    let proxySelect = realSelect.clone()
        .removeAttr('name')
        .removeAttr('id')
        .addClass('qtype_accounting-mobile-proxy')
        .attr('aria-label', label);

    // Remove any Select2 artifacts from the clone.
    proxySelect.removeClass('select2-hidden-accessible');
    proxySelect.removeAttr('data-select2-id');
    proxySelect.find('option').removeAttr('data-select2-id');

    // Set current value.
    proxySelect.val(realSelect.val());

    // Sync proxy → real.
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
 * @return {jQuery} The field wrapper element.
 */
function createProxyAmountField(label, cell, numberFormat) {
    let field = $('<div class="qtype_accounting-mobile-field">');
    field.append('<label>' + label + '</label>');

    let realInput = cell.find('input').first();
    let placeholder = (numberFormat === 'us') ? '0.00' : '0,00';

    let proxyInput = $('<input type="text" class="form-control qtype_accounting-mobile-proxy qtype_accounting-amount-input">')
        .val(realInput.val())
        .attr('placeholder', placeholder)
        .attr('inputmode', 'decimal')
        .attr('aria-label', label);

    // Sync proxy → real on input.
    proxyInput.on('input', function() {
        realInput.val($(this).val());
    });

    // Also sync on blur (after formatting by the existing blur handler in question.js).
    proxyInput.on('blur', function() {
        // The amount formatting handler in question.js fires on .qtype_accounting-amount-input blur.
        // After formatting, sync the formatted value to the real input.
        setTimeout(function() {
            realInput.val(proxyInput.val());
        }, 0);
    });

    field.append(proxyInput);
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
    mobileView.empty().hide();
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
