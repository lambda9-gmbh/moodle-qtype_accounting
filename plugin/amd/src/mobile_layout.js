// This file is part of Moodle - https://moodle.org/
//
// MoFT BuSa is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// MoFT BuSa is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with MoFT BuSa.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Mobile layout module for the Buchungssatz question type.
 *
 * Creates a card-based mobile view with separate debit and credit sections.
 * Uses proxy inputs that sync to hidden real form fields in the table.
 *
 * @module     qtype_buchungssatz/mobile_layout
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function ($) {

    /** Breakpoint for mobile layout (must match CSS). */
    var MOBILE_BREAKPOINT = 768;

    /** CSS class added to container when mobile layout is active. */
    var ACTIVE_CLASS = 'buchungssatz-mobile-active';

    /** Stored row selector from init. */
    var rowSelector = '.buchungssatz-entry-row';

    /** Whether the question is readonly (review mode). */
    var isReadonly = false;

    /** Debounce timer for resize handler. */
    var resizeTimer = null;

    /** Number format for amount placeholders. */
    var numberFormat = 'de';

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
        rowSelector = selector;
        isReadonly = readonly;
        numberFormat = container.data('numberformat') || 'de';

        // Set up resize handler.
        $(window).on('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
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
        var isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
        var isActive = container.hasClass(ACTIVE_CLASS);

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
        // Find or create the mobile view container.
        var mobileView = container.find('.buchungssatz-mobile-view');
        if (mobileView.length === 0) {
            return;
        }

        // Clear any existing content.
        mobileView.empty();

        // Get translated strings.
        var debitLabel = M.util.get_string('debitentries', 'qtype_buchungssatz');
        var creditLabel = M.util.get_string('creditentries', 'qtype_buchungssatz');
        var addDebitLabel = M.util.get_string('adddebitentry', 'qtype_buchungssatz');
        var addCreditLabel = M.util.get_string('addcreditentry', 'qtype_buchungssatz');

        // Create debit section.
        var debitSection = $('<div class="buchungssatz-mobile-section" data-section="debit">');
        debitSection.append(
            '<div class="buchungssatz-mobile-section-header buchungssatz-mobile-debit-header">' +
            '<h4>' + debitLabel + '</h4></div>'
        );
        var debitCards = $('<div class="buchungssatz-mobile-cards">');
        debitSection.append(debitCards);

        // Create credit section.
        var creditSection = $('<div class="buchungssatz-mobile-section" data-section="credit">');
        creditSection.append(
            '<div class="buchungssatz-mobile-section-header buchungssatz-mobile-credit-header">' +
            '<h4>' + creditLabel + '</h4></div>'
        );
        var creditCards = $('<div class="buchungssatz-mobile-cards">');
        creditSection.append(creditCards);

        // Iterate visible entry rows and create cards.
        container.find(rowSelector).each(function () {
            var $row = $(this);
            if ($row.css('display') === 'none') {
                return; // Skip hidden rows.
            }

            var entryIndex = $row.attr('data-entry');
            var entryType = $row.attr('data-entry-type') || 'both';

            if (entryType === 'debit' || entryType === 'both') {
                var debitCard = createMobileCard($row, 'debit', entryIndex);
                debitCards.append(debitCard);
            }

            if (entryType === 'credit' || entryType === 'both') {
                var creditCard = createMobileCard($row, 'credit', entryIndex);
                creditCards.append(creditCard);
            }
        });

        mobileView.append(debitSection);
        mobileView.append(creditSection);

        // Add buttons (only in edit mode).
        if (!isReadonly) {
            debitSection.append(
                '<button type="button" class="btn btn-secondary buchungssatz-add-debit-entry">' +
                addDebitLabel + '</button>'
            );
            creditSection.append(
                '<button type="button" class="btn btn-secondary buchungssatz-add-credit-entry">' +
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
     * @return {jQuery} The card element.
     */
    function createMobileCard($row, side, entryIndex) {
        var accountLabel = M.util.get_string('account', 'qtype_buchungssatz');
        var amountLabel = M.util.get_string('amount', 'qtype_buchungssatz');

        var cells = $row.find('td');

        // Determine which cells to read based on side.
        var accountCell, amountCell;
        if (side === 'debit') {
            accountCell = cells.eq(1); // Soll Account (cell index 1).
            amountCell = cells.eq(2);  // Soll Amount (cell index 2).
        } else {
            accountCell = cells.eq(4); // Haben Account (cell index 4).
            amountCell = cells.eq(5);  // Haben Amount (cell index 5).
        }

        // Build the card.
        var sideLabel = (side === 'debit')
            ? M.util.get_string('soll', 'qtype_buchungssatz')
            : M.util.get_string('haben', 'qtype_buchungssatz');

        var card = $('<div class="buchungssatz-mobile-card" data-entry="' + entryIndex +
            '" data-side="' + side + '" role="group" aria-label="' + sideLabel + ' ' + (parseInt(entryIndex, 10) + 1) + '">');

        var cardBody = $('<div class="buchungssatz-mobile-card-body">');

        if (isReadonly) {
            // Readonly mode: clone display spans.
            cardBody.append(createReadonlyField(accountLabel, accountCell));
            cardBody.append(createReadonlyField(amountLabel, amountCell));
        } else {
            // Edit mode: create proxy inputs.
            cardBody.append(createProxyAccountField(accountLabel, accountCell, entryIndex, side));
            cardBody.append(createProxyAmountField(amountLabel, amountCell, entryIndex, side));
        }

        card.append(cardBody);

        // Add delete button in edit mode.
        if (!isReadonly) {
            var deleteClass = (side === 'debit') ? 'buchungssatz-delete-debit' : 'buchungssatz-delete-credit';
            var actionsDiv = $('<div class="buchungssatz-mobile-card-actions">');
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
        var field = $('<div class="buchungssatz-mobile-field">');
        field.append('<label>' + label + '</label>');

        var span = cell.find('.buchungssatz-readonly').first();
        if (span.length) {
            field.append(span.clone());
        } else {
            field.append('<span class="buchungssatz-readonly"></span>');
        }

        return field;
    }

    /**
     * Create a proxy account select field that syncs to the real hidden field.
     *
     * @param {string} label The field label text.
     * @param {jQuery} cell The original table cell containing the real select.
     * @param {string} entryIndex The entry index.
     * @param {string} side The side: 'debit' or 'credit'.
     * @return {jQuery} The field wrapper element.
     */
    function createProxyAccountField(label, cell, entryIndex, side) {
        var field = $('<div class="buchungssatz-mobile-field">');
        field.append('<label>' + label + '</label>');

        var realSelect = cell.find('select').first();
        if (realSelect.length === 0) {
            // Text input fallback (no accounts available).
            var realInput = cell.find('input').first();
            var proxyInput = $('<input type="text" class="form-control buchungssatz-mobile-proxy">')
                .val(realInput.val())
                .attr('placeholder', realInput.attr('placeholder') || '')
                .attr('aria-label', label);

            proxyInput.on('input', function () {
                realInput.val($(this).val());
            });

            field.append(proxyInput);
            return field;
        }

        // Clone the select element (options and all), remove name/id.
        var proxySelect = realSelect.clone()
            .removeAttr('name')
            .removeAttr('id')
            .addClass('buchungssatz-mobile-proxy')
            .attr('aria-label', label);

        // Remove any Select2 artifacts from the clone.
        proxySelect.removeClass('select2-hidden-accessible');
        proxySelect.removeAttr('data-select2-id');
        proxySelect.find('option').removeAttr('data-select2-id');

        // Set current value.
        proxySelect.val(realSelect.val());

        // Sync proxy → real.
        proxySelect.on('change', function () {
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
     * @param {string} entryIndex The entry index.
     * @param {string} side The side: 'debit' or 'credit'.
     * @return {jQuery} The field wrapper element.
     */
    function createProxyAmountField(label, cell, entryIndex, side) {
        var field = $('<div class="buchungssatz-mobile-field">');
        field.append('<label>' + label + '</label>');

        var realInput = cell.find('input').first();
        var placeholder = (numberFormat === 'us') ? '0.00' : '0,00';

        var proxyInput = $('<input type="text" class="form-control buchungssatz-mobile-proxy buchungssatz-amount-input">')
            .val(realInput.val())
            .attr('placeholder', placeholder)
            .attr('inputmode', 'decimal')
            .attr('aria-label', label);

        // Sync proxy → real on input.
        proxyInput.on('input', function () {
            realInput.val($(this).val());
        });

        // Also sync on blur (after formatting by the existing blur handler in question.js).
        proxyInput.on('blur', function () {
            // The amount formatting handler in question.js fires on .buchungssatz-amount-input blur.
            // After formatting, sync the formatted value to the real input.
            setTimeout(function () {
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
        var mobileView = container.find('.buchungssatz-mobile-view');
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

    return {
        init: init,
        buildMobileView: buildMobileView,
        destroyMobileView: destroyMobileView,
        refreshMobileView: refreshMobileView,
        isMobileActive: isMobileActive
    };
});
