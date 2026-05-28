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

/**
 * Account manager for charts of accounts in qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_buchungssatz;

/**
 * CRUD operations for individual accounts inside a chart of accounts.
 *
 * Chart-level operations live in {@see chart_manager}.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account_manager {
    /**
     * Add an account to a chart.
     *
     * @param int $chartid Chart ID.
     * @param string $accountname Account name.
     * @param int $sortorder Sort order.
     * @return int The new account ID.
     */
    public static function add(int $chartid, string $accountname, int $sortorder = 0): int {
        global $DB;

        $account = new \stdClass();
        $account->chartid = $chartid;
        $account->accountname = $accountname;
        $account->sortorder = $sortorder;

        return $DB->insert_record('qtype_buchungssatz_accounts', $account);
    }

    /**
     * Get accounts for a chart.
     *
     * @param int $chartid Chart ID.
     * @param string $sort SQL ORDER BY clause. Defaults to 'accountname'.
     * @return array The account records.
     */
    public static function get_for_chart(int $chartid, string $sort = 'accountname'): array {
        global $DB;
        return $DB->get_records(
            'qtype_buchungssatz_accounts',
            ['chartid' => $chartid],
            $sort
        );
    }

    /**
     * Update an account.
     *
     * @param int $accountid Account ID.
     * @param string $accountname Account name.
     * @return bool True on success.
     */
    public static function update(int $accountid, string $accountname): bool {
        global $DB;

        $account = $DB->get_record('qtype_buchungssatz_accounts', ['id' => $accountid], '*', MUST_EXIST);
        $account->accountname = $accountname;

        return $DB->update_record('qtype_buchungssatz_accounts', $account);
    }

    /**
     * Delete an account.
     *
     * @param int $accountid Account ID.
     * @return bool True on success.
     */
    public static function delete(int $accountid): bool {
        global $DB;
        return $DB->delete_records('qtype_buchungssatz_accounts', ['id' => $accountid]);
    }

    /**
     * Import accounts from text content into an existing chart.
     *
     * Each line in $content is one account name. Lines whose account name already
     * exists in the chart are skipped (no duplicates created).
     *
     * @param int $chartid Chart ID.
     * @param string $content Text content with one account name per line.
     * @return array ['imported' => int, 'errors' => array]
     */
    public static function import_into_chart(int $chartid, string $content): array {
        global $DB;

        $result = ['imported' => 0, 'errors' => []];

        try {
            $parsed = import_helper::parse_csv($content);
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        // Get existing accounts to avoid duplicates.
        $existing = $DB->get_records_menu(
            'qtype_buchungssatz_accounts',
            ['chartid' => $chartid],
            '',
            'accountname, id'
        );

        foreach ($parsed['accounts'] as $accountname => $accountdata) {
            if (isset($existing[$accountname])) {
                continue;
            }
            try {
                self::add($chartid, $accountdata['accountname'], $accountdata['sortorder']);
                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = $accountname . ': ' . $e->getMessage();
            }
        }

        return $result;
    }
}
