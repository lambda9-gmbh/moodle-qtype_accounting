<?php
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

namespace qtype_accounting;

/**
 * Helper class for shared entry rendering logic between student and teacher views.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_helper {
    /** Maximum number of editable entries shared by browser and app interfaces. */
    public const MAX_ENTRIES = 100;

    /**
     * Determine the entry type based on which account fields are filled.
     *
     * @param string $debitaccount The debit account value.
     * @param string $creditaccount The credit account value.
     * @return string The entry type: 'both', 'debit', or 'credit'.
     */
    public static function determine_entry_type($debitaccount, $creditaccount): string {
        $hasdebit = !empty($debitaccount) && (string)$debitaccount !== '0';
        $hascredit = !empty($creditaccount) && (string)$creditaccount !== '0';
        if ($hasdebit && $hascredit) {
            return 'both';
        } else if ($hasdebit) {
            return 'debit';
        } else if ($hascredit) {
            return 'credit';
        }
        return 'both';
    }

    /**
     * Get which sides of an entry row are hidden for an entry type.
     *
     * @param string $entrytype 'both', 'debit', or 'credit'.
     * @return array ['debit' => bool, 'credit' => bool], true where the side is hidden.
     */
    public static function get_hidden_sides(string $entrytype): array {
        return ['debit' => $entrytype === 'credit', 'credit' => $entrytype === 'debit'];
    }

    /**
     * Build the template context of the options of an account select.
     *
     * @param array $accounts Account records keyed by ID, or account names keyed by ID.
     * @param string $selected The selected account ID.
     * @return array Options with the keys 'value', 'label' and 'selected'.
     */
    public static function account_options(array $accounts, string $selected): array {
        $options = [];
        foreach ($accounts as $key => $value) {
            $optionvalue = is_object($value) ? (string)$value->id : (string)$key;
            $options[] = [
                'value' => $optionvalue,
                'label' => is_object($value) ? $value->accountname : $value,
                'selected' => $optionvalue === (string)$selected,
            ];
        }
        return $options;
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
