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

define(['jquery', 'qtype_buchungssatz/entry_utils'], function($, EntryUtils) {

    // Module-level settings for number formatting.
    let numberFormat = 'de';
    let decimalPlaces = 2;

    /** CSS selector for entry rows in the student view. */
    var ROW_SELECTOR = '.buchungssatz-entry-row';

    /**
     * Format all non-empty amount fields in a container.
     *
     * @param {jQuery} container The question container.
     */
    function formatAllAmountFields(container) {
        container.find('.buchungssatz-amount-input').each(function() {
            var val = $(this).val();
            if (val !== '') {
                $(this).val(EntryUtils.formatNumber(val, numberFormat, decimalPlaces));
            }
        });
    }

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
        const allowEdit = container.data('allowedit') === 1 || container.data('allowedit') === '1';
        numberFormat = container.data('numberformat') || 'de';
        decimalPlaces = parseInt(container.data('decimalplaces'), 10) || 2;

        // Template-based row cloning: read the next index and locate the template element.
        let nextEntryIndex = parseInt(container.data('nextindex'), 10) || 1;
        const templateId = container.data('templateid');
        const template = templateId ? document.getElementById(templateId) : null;
        const tbody = container.find('.buchungssatz-entries')[0];

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

        // Auto-copy amount from Soll to Haben for convenience (delegated for cloned rows).
        container.on('change', 'input[name*="sollbetrag"]', function() {
            const index = $(this).attr('name').match(/\d+/)[0];
            const habenInput = container.find('input[name*="habenbetrag_' + index + '"]');

            // Only auto-fill if Haben is empty.
            if (habenInput.val() === '' || habenInput.val() === '0') {
                habenInput.val($(this).val());
            }
        });

        // Format amount fields on blur (delegated for cloned rows).
        container.on('blur', '.buchungssatz-amount-input', function() {
            const formatted = EntryUtils.formatNumber($(this).val(), numberFormat, decimalPlaces);
            $(this).val(formatted);
        });

        // Format any pre-existing values (e.g., after "Fill correct answers" page reload).
        formatAllAmountFields(container);

        // Format amount fields after "Fill correct answers" button is clicked (AJAX case).
        $('input.btn[name="fill"]').on('click', function() {
            setTimeout(function() {
                formatAllAmountFields(container);
            }, 500);
        });


        // Add entry button handlers.
        if (allowEdit) {
            container.find('.buchungssatz-add-debit-entry').on('click', function() {
                nextEntryIndex = addEntry(container, template, tbody, nextEntryIndex, 'debit');
            });

            container.find('.buchungssatz-add-credit-entry').on('click', function() {
                nextEntryIndex = addEntry(container, template, tbody, nextEntryIndex, 'credit');
            });

            // Delete debit button handler (using delegation for cloned rows).
            container.on('click', '.buchungssatz-delete-debit', function() {
                const entryIndex = $(this).data('entry');
                deleteEntrySide(container, entryIndex, 'debit');
            });

            // Delete credit button handler (using delegation for cloned rows).
            container.on('click', '.buchungssatz-delete-credit', function() {
                const entryIndex = $(this).data('entry');
                deleteEntrySide(container, entryIndex, 'credit');
            });

            // On form submit, disable empty/hidden rows and re-index the remaining
            // rows so their field names are contiguous (0, 1, 2, …). This prevents
            // index gaps that would cause the PHP renderer to show empty rows.
            var form = container.closest('form');
            if (form.length) {
                form.on('submit', function() {
                    var writeIndex = 0;
                    container.find(ROW_SELECTOR).each(function() {
                        var $row = $(this);
                        var isHidden = $row.css('display') === 'none';
                        var isEmpty = EntryUtils.isRowEmpty(this);

                        if (isHidden || isEmpty) {
                            // Exclude from form submission.
                            $row.find('select, input').prop('disabled', true);
                        } else {
                            // Re-index fields to ensure contiguous indices.
                            $row.find('select, input').each(function() {
                                var name = $(this).attr('name');
                                if (name) {
                                    $(this).attr('name', name.replace(/_\d+$/, '_' + writeIndex));
                                }
                            });
                            writeIndex++;
                        }
                    });
                });
            }

            // Restore entry types from field values (handles page reload with saved responses).
            restoreEntryTypes(container);

            // Update delete button visibility.
            updateDeleteButtons(container);
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
        container.find(ROW_SELECTOR).each(function() {
            if ($(this).css('display') === 'none') {
                return; // Skip hidden rows.
            }

            const detectedType = EntryUtils.detectEntryType(this);
            const currentType = $(this).attr('data-entry-type') || 'both';

            if (currentType !== detectedType) {
                $(this).attr('data-entry-type', detectedType);
                EntryUtils.applyEntryTypeVisibility(this, detectedType);
            }
        });

        EntryUtils.updatePerLabels(container, ROW_SELECTOR);
    }

    /**
     * Initialize Select2 on account selects within a row (desktop only).
     *
     * @param {jQuery} row The row jQuery element.
     */
    function initSelect2OnRow(row) {
        const isMobile = window.innerWidth <= 768;
        if (typeof $.fn.select2 !== 'undefined' && !isMobile) {
            row.find('.buchungssatz-account-select').each(function() {
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
    }

    /**
     * Add a new entry row by cloning the template element.
     *
     * First tries to complete an existing incomplete row (e.g., a debit-only row
     * when adding credit). If no incomplete row exists, clones the template.
     *
     * @param {jQuery} container The question container.
     * @param {HTMLTemplateElement} template The template element to clone.
     * @param {HTMLElement} tbody The tbody element to append to.
     * @param {number} nextIndex The next entry index to use for the cloned row.
     * @param {string} entryType The type of entry: 'debit', 'credit', or 'both'.
     * @return {number} The (possibly incremented) next entry index.
     */
    function addEntry(container, template, tbody, nextIndex, entryType) {
        entryType = entryType || 'both';

        // First, check if we can complete an existing incomplete row.
        const incompleteRow = EntryUtils.findIncompleteRow(container, entryType, ROW_SELECTOR);
        if (incompleteRow) {
            // Complete the existing row by making it 'both'.
            incompleteRow.setAttribute('data-entry-type', 'both');
            EntryUtils.applyEntryTypeVisibility(incompleteRow, 'both');

            initSelect2OnRow($(incompleteRow));
            EntryUtils.updatePerLabels(container, ROW_SELECTOR);
            return nextIndex;
        }

        // No incomplete row to complete — clone a new row from the template.
        if (!template) {
            return nextIndex;
        }

        const clone = template.content.cloneNode(true);
        const index = nextIndex;

        // Replace __INDEX__ placeholder in all relevant attributes and content.
        const tr = clone.querySelector('tr');
        if (!tr) {
            return nextIndex;
        }

        // Update the row's data-entry attribute.
        tr.setAttribute('data-entry', index);
        tr.setAttribute('data-entry-type', entryType);

        // Replace __INDEX__ in name, id, and data-entry attributes of all children.
        var elements = tr.querySelectorAll('[name], [id], [data-entry]');
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (el.name) {
                el.name = el.name.replace(/__INDEX__/g, index);
            }
            if (el.id) {
                el.id = el.id.replace(/__INDEX__/g, index);
            }
            if (el.hasAttribute('data-entry')) {
                el.setAttribute('data-entry', el.getAttribute('data-entry').replace(/__INDEX__/g, index));
            }
        }

        // Apply entry type visibility.
        EntryUtils.applyEntryTypeVisibility(tr, entryType);

        // Append to tbody.
        tbody.appendChild(clone);

        // Initialize Select2 on the new row's selects.
        const $newRow = container.find(ROW_SELECTOR + '[data-entry="' + index + '"]');
        initSelect2OnRow($newRow);

        updateDeleteButtons(container);
        EntryUtils.updatePerLabels(container, ROW_SELECTOR);

        return nextIndex + 1;
    }

    /**
     * Delete an entry row (hide it and clear fields).
     *
     * @param {jQuery} container The question container.
     * @param {number} entryIndex The index of the entry to delete.
     */
    function deleteEntry(container, entryIndex) {
        const visibleRows = container.find(ROW_SELECTOR).filter(function() {
            return $(this).css('display') !== 'none';
        });

        // Don't delete if only one entry remains.
        if (visibleRows.length <= 1) {
            return;
        }

        const entryRow = container.find(ROW_SELECTOR + '[data-entry="' + entryIndex + '"]');
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
            entryRow.find('.' + EntryUtils.HIDDEN_CLASS).removeClass(EntryUtils.HIDDEN_CLASS);

            // Hide the row.
            entryRow.css('display', 'none');

            // Also hide any associated explanation row.
            entryRow.next('.buchungssatz-explanation-row').css('display', 'none');

            updateDeleteButtons(container);
            EntryUtils.updatePerLabels(container, ROW_SELECTOR);
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
        const entryRow = container.find(ROW_SELECTOR + '[data-entry="' + entryIndex + '"]');

        if (entryRow.length === 0) {
            return;
        }

        const currentType = entryRow.attr('data-entry-type') || 'both';

        if (currentType === 'both') {
            // Row has both sides - just hide the deleted side.
            const newType = (side === 'debit') ? 'credit' : 'debit';
            entryRow.attr('data-entry-type', newType);

            // Apply visibility for the new type.
            EntryUtils.applyEntryTypeVisibility(entryRow[0], newType);

            // Clear the hidden side's fields.
            EntryUtils.clearEntrySideFields(entryRow[0], side);

            EntryUtils.updatePerLabels(container, ROW_SELECTOR);
        } else if ((currentType === 'debit' && side === 'debit') ||
                   (currentType === 'credit' && side === 'credit')) {
            // Row only has this side - delete the entire row.
            deleteEntry(container, entryIndex);
        }
        // If trying to delete a side that's already hidden, do nothing.
    }

    /**
     * Update delete button visibility.
     * Hide delete buttons if only one entry is visible.
     *
     * @param {jQuery} container The question container.
     */
    function updateDeleteButtons(container) {
        const visibleRows = container.find(ROW_SELECTOR).filter(function() {
            return $(this).css('display') !== 'none';
        });

        if (visibleRows.length <= 1) {
            container.find('.buchungssatz-delete-debit, .buchungssatz-delete-credit').css('visibility', 'hidden');
        } else {
            container.find(ROW_SELECTOR).each(function() {
                if ($(this).css('display') !== 'none') {
                    $(this).find('.buchungssatz-delete-debit, .buchungssatz-delete-credit').css('visibility', 'visible');
                }
            });
        }
    }

    return {
        init: init
    };
});
