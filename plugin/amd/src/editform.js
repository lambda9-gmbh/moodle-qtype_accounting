// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for the Buchungssatz question edit form.
 *
 * @module     qtype_buchungssatz/editform
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    /**
     * Initialize the edit form enhancements.
     */
    function init() {
        // Show/hide entry fields based on allowmultipleentries setting.
        var allowMultiple = $('select[name="allowmultipleentries"]');

        allowMultiple.on('change', function() {
            toggleEntryFields($(this).val() === '1');
        });

        // Initial state.
        toggleEntryFields(allowMultiple.val() === '1');

        // Auto-fill Haben amount when Soll amount is entered.
        $('input[name*="sollbetrag"]').on('change', function() {
            var match = $(this).attr('name').match(/entries\[(\d+)\]/);
            if (match) {
                var index = match[1];
                var habenInput = $('input[name="entries[' + index + '][habenbetrag]"]');
                if (habenInput.val() === '' || habenInput.val() === '0') {
                    habenInput.val($(this).val());
                }
            }
        });

        // Calculate total fraction.
        $('input[name*="fraction"]').on('change', updateTotalFraction);
        updateTotalFraction();
    }

    /**
     * Toggle visibility of entry fields beyond the first one.
     *
     * @param {boolean} show Whether to show multiple entries.
     */
    function toggleEntryFields(show) {
        $('.buchungssatz-entry').each(function(index) {
            if (index > 0) {
                $(this).toggle(show);
            }
        });
    }

    /**
     * Update and display the total fraction.
     */
    function updateTotalFraction() {
        var total = 0;

        $('input[name*="fraction"]').each(function() {
            var val = parseFloat($(this).val()) || 0;
            total += val;
        });

        var display = $('#total-fraction-display');
        if (display.length === 0) {
            display = $('<div id="total-fraction-display" class="alert alert-info mt-2"></div>');
            $('input[name*="fraction"]').last().closest('.fitem').after(display);
        }

        display.text('Total points: ' + total.toFixed(2));
    }

    return {
        init: init
    };
});
