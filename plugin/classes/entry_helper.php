<?php
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

namespace qtype_buchungssatz;

/**
 * Helper class for shared entry rendering logic between student and teacher views.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_helper {
    /**
     * Determine the entry type based on which account fields are filled.
     *
     * @param string $sollkonto The debit account value.
     * @param string $habenkonto The credit account value.
     * @return string The entry type: 'both', 'debit', or 'credit'.
     */
    public static function determine_entry_type($sollkonto, $habenkonto): string {
        $hassoll = !empty($sollkonto) && (string)$sollkonto !== '0';
        $hashaben = !empty($habenkonto) && (string)$habenkonto !== '0';
        if ($hassoll && $hashaben) {
            return 'both';
        } else if ($hassoll) {
            return 'debit';
        } else if ($hashaben) {
            return 'credit';
        }
        return 'both';
    }

    /**
     * Get CSS hidden classes for debit/credit cells based on entry type.
     *
     * @param string $entrytype The entry type: 'both', 'debit', or 'credit'.
     * @return array Associative array with 'debit' and 'credit' keys containing CSS class suffixes.
     */
    public static function get_hidden_classes(string $entrytype): array {
        $hiddenclass = 'buchungssatz-hidden-cell';
        return [
            'debit' => ($entrytype === 'credit') ? ' ' . $hiddenclass : '',
            'credit' => ($entrytype === 'debit') ? ' ' . $hiddenclass : '',
        ];
    }

    /**
     * Render a delete button for one side of an entry.
     *
     * @param string $side The side: 'debit' or 'credit'.
     * @param int|string $index The entry index.
     * @param string $indexattr The data attribute name for the index (e.g., 'data-entry' or 'data-index').
     * @return string The HTML for the delete button.
     */
    public static function render_delete_button(string $side, $index, string $indexattr): string {
        $cssclass = ($side === 'debit') ? 'buchungssatz-delete-debit' : 'buchungssatz-delete-credit';
        $titlekey = ($side === 'debit') ? 'soll' : 'haben';
        $title = get_string($titlekey, 'qtype_buchungssatz');

        return '<button type="button" class="btn btn-sm btn-outline-danger ' . $cssclass . '" ' .
            $indexattr . '="' . s($index) . '" title="' . s($title) . '">' .
            '<i class="fa fa-trash"></i>' .
            '</button>';
    }

    /**
     * Build HTML option elements for an account select dropdown.
     *
     * @param array $accounts Associative array of accountname => label, or array of objects with accountname.
     * @param string $selected The currently selected account name.
     * @param string $placeholder The placeholder text for the empty option.
     * @return string The HTML option elements.
     */
    public static function build_account_options(array $accounts, string $selected, string $placeholder = ''): string {
        $html = '';
        if ($placeholder !== '') {
            $html .= '<option value="">' . s($placeholder) . '</option>';
        }
        foreach ($accounts as $key => $value) {
            if (is_object($value)) {
                $optionvalue = (string)$value->id;
                $label = $value->accountname;
            } else {
                // Associative array: key = account ID, value = account name.
                $optionvalue = (string)$key;
                $label = $value;
            }
            $selectedattr = ($optionvalue === (string)$selected) ? ' selected' : '';
            $html .= '<option value="' . s($optionvalue) . '"' . $selectedattr . '>' . s($label) . '</option>';
        }
        return $html;
    }

    /**
     * Format an account name for display by looking up from ID.
     *
     * @param int $accountid The account ID.
     * @param array $accounts Account records keyed by ID (from Moodle's get_records).
     * @return string The account name, or empty string if not found.
     */
    public static function format_account_display_by_id(int $accountid, array $accounts): string {
        if ($accountid <= 0) {
            return '';
        }
        if (isset($accounts[$accountid])) {
            return $accounts[$accountid]->accountname;
        }
        return '';
    }
}
