// This file is part of MoFT BuSa - Moodle Question Type Buchungssatz.
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
// along with MoFT BuSa.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for the Buchungssatz question type student interface.
 *
 * @module     qtype_buchungssatz/question
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str'], function($, Str) {

    // Module-level settings for number formatting.
    let numberFormat = 'de';
    let decimalPlaces = 2;

    /**
     * Initialize the question interface.
     *
     * @param {string} containerId The ID of the question container.
     * @param {Array} accounts The available accounts for selection.
     * @param {number} maxEntries Maximum number of entries allowed.
     * @param {boolean} allowEdit Whether the user can add/delete entries.
     * @param {string} numFormat The number format ('de' or 'us').
     * @param {number} decimals The number of decimal places.
     */
    function init(containerId, accounts, maxEntries, allowEdit, numFormat, decimals) {
        const container = $('#' + containerId);

        if (container.length === 0) {
            return;
        }

        maxEntries = maxEntries || 1;
        allowEdit = allowEdit !== false;
        numberFormat = numFormat || 'de';
        decimalPlaces = decimals || 2;

        // Enable searchable dropdowns if Select2 is available.
        if (typeof $.fn.select2 !== 'undefined') {
            container.find('.buchungssatz-account-select').select2({
                placeholder: M.util.get_string('selectaccount', 'qtype_buchungssatz'),
                allowClear: true,
                width: '100%'
            });
        }

        // Auto-copy amount from Soll to Haben for convenience.
        container.find('input[name*="sollbetrag"]').on('change', function() {
            const index = $(this).attr('name').match(/\d+/)[0];
            const habenInput = container.find('input[name*="habenbetrag_' + index + '"]');

            // Only auto-fill if Haben is empty.
            if (habenInput.val() === '' || habenInput.val() === '0') {
                habenInput.val($(this).val());
            }
        });

        // Format amount fields on blur.
        container.find('.buchungssatz-amount-input').on('blur', function() {
            const formatted = formatNumber($(this).val());
            $(this).val(formatted);
        });

        // Add entry button handler.
        if (allowEdit && maxEntries > 1) {
            container.find('.buchungssatz-add-entry').on('click', function() {
                addEntry(container, maxEntries);
            });

            // Delete entry button handler (using delegation for dynamically shown rows).
            container.on('click', '.buchungssatz-delete-entry', function() {
                const entryIndex = $(this).data('entry');
                deleteEntry(container, entryIndex);
            });

            // Update delete button visibility.
            updateDeleteButtons(container);
        }
    }

    /**
     * Add a new entry row (show the next hidden row).
     *
     * @param {jQuery} container The question container.
     * @param {number} maxEntries Maximum number of entries.
     */
    function addEntry(container, maxEntries) {
        const entryRows = container.find('.buchungssatz-entry-row');
        const hiddenRows = entryRows.filter(function() {
            return $(this).css('display') === 'none';
        });

        if (hiddenRows.length > 0) {
            const firstHidden = hiddenRows.first();
            firstHidden.css('display', '');

            // Re-initialize Select2 if available.
            if (typeof $.fn.select2 !== 'undefined') {
                firstHidden.find('.buchungssatz-account-select').select2({
                    placeholder: M.util.get_string('selectaccount', 'qtype_buchungssatz'),
                    allowClear: true,
                    width: '100%'
                });
            }

            updateDeleteButtons(container);
            updateAddButton(container, maxEntries);
            updatePerAnLabels(container);
        }
    }

    /**
     * Delete an entry row (hide it and clear fields).
     *
     * @param {jQuery} container The question container.
     * @param {number} entryIndex The index of the entry to delete.
     */
    function deleteEntry(container, entryIndex) {
        const visibleRows = container.find('.buchungssatz-entry-row').filter(function() {
            return $(this).css('display') !== 'none';
        });

        // Don't delete if only one entry remains.
        if (visibleRows.length <= 1) {
            return;
        }

        const entryRow = container.find('.buchungssatz-entry-row[data-entry="' + entryIndex + '"]');
        if (entryRow.length > 0) {
            // Clear the fields.
            entryRow.find('select').val('');
            entryRow.find('input.buchungssatz-amount-input').val('');

            // Destroy Select2 if active.
            if (typeof $.fn.select2 !== 'undefined') {
                entryRow.find('.buchungssatz-account-select').each(function() {
                    if ($(this).data('select2')) {
                        $(this).select2('destroy');
                    }
                });
            }

            // Hide the row.
            entryRow.css('display', 'none');

            // Also hide any associated explanation row.
            entryRow.next('.buchungssatz-explanation-row').css('display', 'none');

            updateDeleteButtons(container);
            updateAddButton(container, 50); // Use high number, actual max from init.
            updatePerAnLabels(container);
        }
    }

    /**
     * Update delete button visibility.
     * Hide delete buttons if only one entry is visible.
     *
     * @param {jQuery} container The question container.
     */
    function updateDeleteButtons(container) {
        const visibleRows = container.find('.buchungssatz-entry-row').filter(function() {
            return $(this).css('display') !== 'none';
        });

        if (visibleRows.length <= 1) {
            container.find('.buchungssatz-delete-entry').css('visibility', 'hidden');
        } else {
            container.find('.buchungssatz-entry-row').each(function() {
                if ($(this).css('display') !== 'none') {
                    $(this).find('.buchungssatz-delete-entry').css('visibility', 'visible');
                }
            });
        }
    }

    /**
     * Update add button visibility.
     *
     * @param {jQuery} container The question container.
     * @param {number} maxEntries Maximum entries allowed.
     */
    function updateAddButton(container, maxEntries) {
        const visibleRows = container.find('.buchungssatz-entry-row').filter(function() {
            return $(this).css('display') !== 'none';
        });

        const addButton = container.find('.buchungssatz-add-entry');
        if (visibleRows.length >= maxEntries) {
            addButton.css('display', 'none');
        } else {
            addButton.css('display', 'inline-block');
        }
    }

    /**
     * Update Per/an labels so they only appear on the first visible row.
     *
     * @param {jQuery} container The question container.
     */
    function updatePerAnLabels(container) {
        const entryRows = container.find('.buchungssatz-entry-row');
        let isFirst = true;

        Str.get_strings([
            {key: 'per', component: 'qtype_buchungssatz'},
            {key: 'an', component: 'qtype_buchungssatz'}
        ]).then(function(strings) {
            const perStr = strings[0];
            const anStr = strings[1];

            entryRows.each(function() {
                if ($(this).css('display') === 'none') {
                    return; // Skip hidden rows.
                }

                const labelCells = $(this).find('.buchungssatz-label-cell');
                if (labelCells.length >= 2) {
                    $(labelCells[0]).text(isFirst ? perStr : '');
                    $(labelCells[1]).text(isFirst ? anStr : '');
                }

                isFirst = false;
            });
        });
    }

    /**
     * Parse a formatted number string to a plain number.
     * Handles both German (1.234,56) and US (1,234.56) formats.
     *
     * @param {string} value The formatted number string.
     * @return {string} The plain number string (e.g., "1234.56").
     */
    function parseNumber(value) {
        if (!value || value.trim() === '') {
            return '';
        }

        let cleaned = value.trim();

        // Detect format based on the position of comma and dot.
        const lastComma = cleaned.lastIndexOf(',');
        const lastDot = cleaned.lastIndexOf('.');

        if (lastComma > lastDot) {
            // German format: comma is decimal separator.
            cleaned = cleaned.replace(/\./g, ''); // Remove thousand separators.
            cleaned = cleaned.replace(',', '.'); // Replace decimal comma with dot.
        } else if (lastDot > lastComma) {
            // US format: dot is decimal separator.
            cleaned = cleaned.replace(/,/g, ''); // Remove thousand separators.
        }

        return cleaned;
    }

    /**
     * Format a number according to the question's number format settings.
     *
     * @param {string|number} value The number to format.
     * @return {string} The formatted number string.
     */
    function formatNumber(value) {
        if (value === '' || value === null || value === undefined) {
            return '';
        }

        // Parse to float first.
        const plainValue = parseNumber(String(value));
        const num = parseFloat(plainValue);

        if (isNaN(num)) {
            return '';
        }

        // Format according to settings.
        if (numberFormat === 'us') {
            return num.toLocaleString('en-US', {
                minimumFractionDigits: decimalPlaces,
                maximumFractionDigits: decimalPlaces
            });
        }

        // Default: German/EU format.
        return num.toLocaleString('de-DE', {
            minimumFractionDigits: decimalPlaces,
            maximumFractionDigits: decimalPlaces
        });
    }

    return {
        init: init
    };
});
