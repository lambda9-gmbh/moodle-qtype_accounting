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
     */
    function init(containerId) {
        const container = $('#' + containerId);

        if (container.length === 0) {
            return;
        }

        // Read configuration from data attributes (avoids Moodle 3.10 js_call_amd size limits).
        const maxEntries = parseInt(container.data('maxentries'), 10) || 20;
        const allowEdit = container.data('allowedit') === 1 || container.data('allowedit') === '1';
        numberFormat = container.data('numberformat') || 'de';
        decimalPlaces = parseInt(container.data('decimalplaces'), 10) || 2;

        // Enable searchable dropdowns if Select2 is available (desktop only).
        // On mobile, native selects provide better UX.
        const isMobile = window.innerWidth <= 768;
        if (typeof $.fn.select2 !== 'undefined' && !isMobile) {
            container.find('.buchungssatz-account-select').select2({
                placeholder: M.util.get_string('selectaccount', 'qtype_buchungssatz'),
                allowClear: true,
                width: '100%',
                dropdownAutoWidth: true
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

        // Add entry button handlers.
        if (allowEdit && maxEntries > 1) {
            container.find('.buchungssatz-add-debit-entry').on('click', function() {
                addEntry(container, maxEntries, 'debit');
            });

            container.find('.buchungssatz-add-credit-entry').on('click', function() {
                addEntry(container, maxEntries, 'credit');
            });

            // Delete debit button handler (using delegation for dynamically shown rows).
            container.on('click', '.buchungssatz-delete-debit', function() {
                const entryIndex = $(this).data('entry');
                deleteEntrySide(container, entryIndex, 'debit');
            });

            // Delete credit button handler (using delegation for dynamically shown rows).
            container.on('click', '.buchungssatz-delete-credit', function() {
                const entryIndex = $(this).data('entry');
                deleteEntrySide(container, entryIndex, 'credit');
            });

            // Restore entry types from field values (handles page reload with saved responses).
            restoreEntryTypes(container);

            // Update delete button visibility.
            updateDeleteButtons(container);
            updateAddButtons(container, maxEntries);
        }
    }

    /**
     * Restore entry types from field values on visible rows.
     *
     * When a page is reloaded with saved responses, the server renders the correct
     * data-entry-type and hidden-cell classes. This function provides a JS-side safety
     * net: it scans visible rows, detects the entry type from field values, and applies
     * the correct visibility if there is any mismatch.
     *
     * @param {jQuery} container The question container.
     */
    function restoreEntryTypes(container) {
        container.find('.buchungssatz-entry-row').each(function() {
            if ($(this).css('display') === 'none') {
                return; // Skip hidden rows.
            }

            const allCells = $(this).find('td');
            // Detect from field values: check selects and text inputs in soll (cell 1) and haben (cell 4).
            const sollVal = allCells.eq(1).find('select, input').val() || '';
            const habenVal = allCells.eq(4).find('select, input').val() || '';

            let detectedType;
            if (sollVal && habenVal) {
                detectedType = 'both';
            } else if (sollVal) {
                detectedType = 'debit';
            } else if (habenVal) {
                detectedType = 'credit';
            } else {
                detectedType = 'both';
            }

            const currentType = $(this).attr('data-entry-type') || 'both';
            if (currentType !== detectedType) {
                $(this).attr('data-entry-type', detectedType);
                applyEntryTypeVisibility($(this), detectedType);
            }
        });

        updatePerAnLabels(container);
    }

    /**
     * Add a new entry row (show the next hidden row).
     *
     * @param {jQuery} container The question container.
     * @param {number} maxEntries Maximum number of entries.
     * @param {string} entryType The type of entry: 'debit', 'credit', or 'both'.
     */
    function addEntry(container, maxEntries, entryType) {
        entryType = entryType || 'both';

        // First, check if we can complete an existing incomplete row.
        const incompleteRow = findIncompleteRow(container, entryType);
        if (incompleteRow) {
            // Complete the existing row by making it 'both'.
            incompleteRow.attr('data-entry-type', 'both');
            applyEntryTypeVisibility(incompleteRow, 'both');

            // Re-initialize Select2 on the newly visible selects.
            const isMobile = window.innerWidth <= 768;
            if (typeof $.fn.select2 !== 'undefined' && !isMobile) {
                incompleteRow.find('.buchungssatz-account-select').each(function() {
                    if (!$(this).closest('td').hasClass('buchungssatz-hidden-cell') && !$(this).data('select2')) {
                        $(this).select2({
                            placeholder: M.util.get_string('selectaccount', 'qtype_buchungssatz'),
                            allowClear: true,
                            width: '100%',
                            dropdownAutoWidth: true
                        });
                    }
                });
            }

            updatePerAnLabels(container);
            return;
        }

        // No incomplete row to complete, so add a new row.
        const entryRows = container.find('.buchungssatz-entry-row');
        const hiddenRows = entryRows.filter(function() {
            return $(this).css('display') === 'none';
        });

        if (hiddenRows.length > 0) {
            const firstHidden = hiddenRows.first();
            firstHidden.css('display', '');

            // Set the entry type attribute.
            firstHidden.attr('data-entry-type', entryType);

            // Apply hidden-cell class based on entry type.
            applyEntryTypeVisibility(firstHidden, entryType);

            // Re-initialize Select2 if available (desktop only).
            // Only for visible selects.
            const isMobile = window.innerWidth <= 768;
            if (typeof $.fn.select2 !== 'undefined' && !isMobile) {
                firstHidden.find('.buchungssatz-account-select').each(function() {
                    // Check if the parent cell is visible.
                    if (!$(this).closest('td').hasClass('buchungssatz-hidden-cell')) {
                        $(this).select2({
                            placeholder: M.util.get_string('selectaccount', 'qtype_buchungssatz'),
                            allowClear: true,
                            width: '100%',
                            dropdownAutoWidth: true
                        });
                    }
                });
            }

            updateDeleteButtons(container);
            updateAddButtons(container, maxEntries);
            updatePerAnLabels(container);
        }
    }

    /**
     * Find an incomplete row that can be completed with the given entry type.
     *
     * @param {jQuery} container The question container.
     * @param {string} entryType The type of entry being added: 'debit' or 'credit'.
     * @return {jQuery|null} The incomplete row to complete, or null if none found.
     */
    function findIncompleteRow(container, entryType) {
        // Look for visible rows that are incomplete (only one side).
        const visibleRows = container.find('.buchungssatz-entry-row').filter(function() {
            return $(this).css('display') !== 'none';
        });

        let incompleteRow = null;

        visibleRows.each(function() {
            const rowType = $(this).attr('data-entry-type');

            // If adding debit, look for a credit-only row.
            // If adding credit, look for a debit-only row.
            if (entryType === 'debit' && rowType === 'credit') {
                incompleteRow = $(this);
                return false; // Break the loop.
            } else if (entryType === 'credit' && rowType === 'debit') {
                incompleteRow = $(this);
                return false; // Break the loop.
            }
        });

        return incompleteRow;
    }

    /**
     * Apply visibility classes based on entry type.
     *
     * @param {jQuery} row The entry row.
     * @param {string} entryType The type of entry: 'debit', 'credit', or 'both'.
     */
    function applyEntryTypeVisibility(row, entryType) {
        // Get all td cells in the row by index.
        // Structure: 0=Per label, 1=soll account, 2=soll amount, 3=debit delete button, 4=haben account, 5=haben amount, 6=credit delete.
        const allCells = row.find('td');

        const perLabelCell = allCells.eq(0);
        const sollAccountCell = allCells.eq(1);
        const sollAmountCell = allCells.eq(2);
        const debitDeleteCell = allCells.eq(3);  // Contains debit delete button.
        const habenAccountCell = allCells.eq(4);
        const habenAmountCell = allCells.eq(5);
        const creditDeleteCell = allCells.eq(6);  // Contains credit delete button.

        // Reset all hidden classes first.
        perLabelCell.removeClass('buchungssatz-hidden-cell');
        sollAccountCell.removeClass('buchungssatz-hidden-cell');
        sollAmountCell.removeClass('buchungssatz-hidden-cell');
        debitDeleteCell.removeClass('buchungssatz-hidden-cell');
        habenAccountCell.removeClass('buchungssatz-hidden-cell');
        habenAmountCell.removeClass('buchungssatz-hidden-cell');
        creditDeleteCell.removeClass('buchungssatz-hidden-cell');

        if (entryType === 'debit') {
            // Hide credit (haben) side - show only debit fields.
            // Keep debit delete button visible (cell 3), hide haben cells and credit delete.
            habenAccountCell.addClass('buchungssatz-hidden-cell');
            habenAmountCell.addClass('buchungssatz-hidden-cell');
            creditDeleteCell.addClass('buchungssatz-hidden-cell');
        } else if (entryType === 'credit') {
            // Hide debit (soll) side - show only credit fields.
            // Hide per, soll cells, and debit delete button.
            perLabelCell.addClass('buchungssatz-hidden-cell');
            sollAccountCell.addClass('buchungssatz-hidden-cell');
            sollAmountCell.addClass('buchungssatz-hidden-cell');
            debitDeleteCell.addClass('buchungssatz-hidden-cell');
        }
        // 'both' keeps everything visible.
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

            // Reset entry type to 'both' and remove hidden-cell classes.
            entryRow.attr('data-entry-type', 'both');
            entryRow.find('.buchungssatz-hidden-cell').removeClass('buchungssatz-hidden-cell');

            // Hide the row.
            entryRow.css('display', 'none');

            // Also hide any associated explanation row.
            entryRow.next('.buchungssatz-explanation-row').css('display', 'none');

            updateDeleteButtons(container);
            updateAddButtons(container, 50); // Use high number, actual max from init.
            updatePerAnLabels(container);
        }
    }

    /**
     * Delete one side of an entry (debit or credit).
     *
     * @param {jQuery} container The question container.
     * @param {number} entryIndex The index of the entry.
     * @param {string} side The side to delete: 'debit' or 'credit'.
     */
    function deleteEntrySide(container, entryIndex, side) {
        const entryRow = container.find('.buchungssatz-entry-row[data-entry="' + entryIndex + '"]');

        if (entryRow.length === 0) {
            return;
        }

        const currentType = entryRow.attr('data-entry-type') || 'both';

        if (currentType === 'both') {
            // Row has both sides - just hide the deleted side.
            const newType = (side === 'debit') ? 'credit' : 'debit';
            entryRow.attr('data-entry-type', newType);

            // Apply visibility for the new type.
            applyEntryTypeVisibility(entryRow, newType);

            // Clear the hidden side's fields.
            clearEntrySideFields(entryRow, side);

            updatePerAnLabels(container);
        } else if ((currentType === 'debit' && side === 'debit') ||
                   (currentType === 'credit' && side === 'credit')) {
            // Row only has this side - delete the entire row.
            deleteEntry(container, entryIndex);
        }
        // If trying to delete a side that's already hidden, do nothing.
    }

    /**
     * Clear the fields for one side of an entry.
     *
     * @param {jQuery} entryRow The entry row.
     * @param {string} side The side to clear: 'debit' or 'credit'.
     */
    function clearEntrySideFields(entryRow, side) {
        if (side === 'debit') {
            // Clear soll (debit) fields - cells 1 and 2.
            const allCells = entryRow.find('td');
            allCells.eq(1).find('select').val('');
            allCells.eq(2).find('input').val('');
        } else if (side === 'credit') {
            // Clear haben (credit) fields - cells 4 and 5.
            const allCells = entryRow.find('td');
            allCells.eq(4).find('select').val('');
            allCells.eq(5).find('input').val('');
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
            container.find('.buchungssatz-delete-debit, .buchungssatz-delete-credit').css('visibility', 'hidden');
        } else {
            container.find('.buchungssatz-entry-row').each(function() {
                if ($(this).css('display') !== 'none') {
                    $(this).find('.buchungssatz-delete-debit, .buchungssatz-delete-credit').css('visibility', 'visible');
                }
            });
        }
    }

    /**
     * Update add buttons visibility.
     *
     * @param {jQuery} container The question container.
     * @param {number} maxEntries Maximum entries allowed.
     */
    function updateAddButtons(container, maxEntries) {
        const hiddenRows = container.find('.buchungssatz-entry-row').filter(function() {
            return $(this).css('display') === 'none';
        });

        const hasHiddenRows = hiddenRows.length > 0;

        const addDebitButton = container.find('.buchungssatz-add-debit-entry');
        const addCreditButton = container.find('.buchungssatz-add-credit-entry');

        if (hasHiddenRows) {
            addDebitButton.css('display', 'inline-block');
            addCreditButton.css('display', 'inline-block');
        } else {
            addDebitButton.css('display', 'none');
            addCreditButton.css('display', 'none');
        }
    }

    /**
     * Update Per label so it only appears on the first visible debit entry.
     * Note: The "an" cell now contains the debit delete button, so we don't update it.
     *
     * @param {jQuery} container The question container.
     */
    function updatePerAnLabels(container) {
        const entryRows = container.find('.buchungssatz-entry-row');
        let firstDebitFound = false;

        Str.get_string('per', 'qtype_buchungssatz').then(function(perStr) {
            entryRows.each(function() {
                if ($(this).css('display') === 'none') {
                    return; // Skip hidden rows.
                }

                const entryType = $(this).attr('data-entry-type') || 'both';
                const allCells = $(this).find('td');
                const perCell = allCells.eq(0); // First cell is Per label.

                // Handle "Per" label (debit side).
                if (entryType === 'debit' || entryType === 'both') {
                    if (!firstDebitFound) {
                        perCell.text(perStr);
                        firstDebitFound = true;
                    } else {
                        perCell.text('');
                    }
                }
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
