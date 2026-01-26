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

    /**
     * Initialize the question interface.
     *
     * @param {string} containerId The ID of the question container.
     * @param {Array} accounts The available accounts for selection.
     * @param {number} maxEntries Maximum number of entries allowed.
     * @param {boolean} allowEdit Whether the user can add/delete entries.
     */
    function init(containerId, accounts, maxEntries, allowEdit) {
        const container = $('#' + containerId);

        if (container.length === 0) {
            return;
        }

        maxEntries = maxEntries || 1;
        allowEdit = allowEdit !== false;

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
            entryRow.find('input[type="number"]').val('');

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

    return {
        init: init
    };
});
