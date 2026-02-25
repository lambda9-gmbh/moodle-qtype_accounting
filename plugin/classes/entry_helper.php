<?php
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

namespace qtype_buchungssatz;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for shared entry rendering logic between student and teacher views.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_helper {

    /**
     * Determine the entry type based on which account fields are filled.
     *
     * @param string $sollkonto The debit account value.
     * @param string $habenkonto The credit account value.
     * @return string The entry type: 'both', 'debit', or 'credit'.
     */
    public static function determine_entry_type(string $sollkonto, string $habenkonto): string {
        $hassoll = !empty($sollkonto);
        $hashaben = !empty($habenkonto);
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
            $indexattr . '="' . $index . '" title="' . $title . '">' .
            '<i class="fa fa-trash"></i>' .
            '</button>';
    }

    /**
     * Build HTML option elements for an account select dropdown.
     *
     * @param array $accounts Associative array of accountnumber => label, or array of objects with accountnumber/accountname.
     * @param string $selected The currently selected account number.
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
                $accountnumber = $value->accountnumber;
                $label = $value->accountnumber . ' - ' . $value->accountname;
            } else {
                $accountnumber = (string)$key;
                $label = $value;
            }
            $selectedattr = ((string)$accountnumber === (string)$selected) ? ' selected' : '';
            $html .= '<option value="' . s($accountnumber) . '"' . $selectedattr . '>' . s($label) . '</option>';
        }
        return $html;
    }

    /**
     * Format an account number for display by looking up its name.
     *
     * @param string $accountnumber The account number to look up.
     * @param array $accounts The available accounts (array of objects with accountnumber/accountname).
     * @return string The formatted display string (e.g., "1200 - Bank"), or just the number if not found.
     */
    public static function format_account_display(string $accountnumber, array $accounts): string {
        if (empty($accountnumber)) {
            return '';
        }
        foreach ($accounts as $account) {
            if ($account->accountnumber === $accountnumber) {
                return $accountnumber . ' - ' . $account->accountname;
            }
        }
        return $accountnumber;
    }
}
