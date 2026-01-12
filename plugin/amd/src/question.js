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
 * JavaScript for the Buchungssatz question type student interface.
 *
 * @module     qtype_buchungssatz/question
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    /**
     * Initialize the question interface.
     *
     * @param {string} questionDivId The ID of the question container.
     * @param {Array} accounts The available accounts for selection.
     */
    function init(questionDivId, accounts) {
        var container = $('#' + questionDivId);

        if (container.length === 0) {
            return;
        }

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
            var index = $(this).attr('name').match(/\d+/)[0];
            var habenInput = container.find('input[name*="habenbetrag_' + index + '"]');

            // Only auto-fill if Haben is empty.
            if (habenInput.val() === '' || habenInput.val() === '0') {
                habenInput.val($(this).val());
            }
        });

        // Validate that Soll and Haben amounts balance.
        container.find('.buchungssatz-amount').on('change', function() {
            validateBalance(container);
        });

        // Initial validation.
        validateBalance(container);
    }

    /**
     * Validate that total Soll equals total Haben.
     *
     * @param {jQuery} container The question container.
     */
    function validateBalance(container) {
        var totalSoll = 0;
        var totalHaben = 0;

        container.find('input[name*="sollbetrag"]').each(function() {
            var val = parseFloat($(this).val()) || 0;
            totalSoll += val;
        });

        container.find('input[name*="habenbetrag"]').each(function() {
            var val = parseFloat($(this).val()) || 0;
            totalHaben += val;
        });

        // Show balance indicator.
        var balanceIndicator = container.find('.buchungssatz-balance');
        if (balanceIndicator.length === 0) {
            balanceIndicator = $('<div class="buchungssatz-balance alert"></div>');
            container.find('.buchungssatz-entries').append(balanceIndicator);
        }

        var diff = Math.abs(totalSoll - totalHaben);
        if (diff < 0.01 && totalSoll > 0) {
            balanceIndicator
                .removeClass('alert-warning alert-danger')
                .addClass('alert-success')
                .text(M.util.get_string('balanced', 'qtype_buchungssatz') ||
                    'Soll = Haben (' + totalSoll.toFixed(2) + ')');
        } else if (totalSoll > 0 || totalHaben > 0) {
            balanceIndicator
                .removeClass('alert-success alert-danger')
                .addClass('alert-warning')
                .text('Soll: ' + totalSoll.toFixed(2) + ' / Haben: ' + totalHaben.toFixed(2) +
                    ' (Differenz: ' + diff.toFixed(2) + ')');
        } else {
            balanceIndicator.hide();
        }
    }

    return {
        init: init
    };
});
