<?php
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
 * Chart of accounts manager for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_buchungssatz;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class for charts of accounts.
 */
class chart_manager {

    /**
     * Create a new chart of accounts.
     *
     * @param string $name Chart name.
     * @param string $description Chart description.
     * @param int $contextid Context ID.
     * @return int The new chart ID.
     */
    public static function create_chart(string $name, string $description, int $contextid): int {
        global $DB, $USER;

        $chart = new \stdClass();
        $chart->name = $name;
        $chart->description = $description;
        $chart->contextid = $contextid;
        $chart->timecreated = time();
        $chart->timemodified = time();
        $chart->usermodified = $USER->id;

        return $DB->insert_record('qtype_buchungssatz_charts', $chart);
    }

    /**
     * Update a chart of accounts.
     *
     * @param int $chartid Chart ID.
     * @param string $name Chart name.
     * @param string $description Chart description.
     * @return bool
     */
    public static function update_chart(int $chartid, string $name, string $description): bool {
        global $DB, $USER;

        $chart = $DB->get_record('qtype_buchungssatz_charts', ['id' => $chartid], '*', MUST_EXIST);
        $chart->name = $name;
        $chart->description = $description;
        $chart->timemodified = time();
        $chart->usermodified = $USER->id;

        return $DB->update_record('qtype_buchungssatz_charts', $chart);
    }

    /**
     * Delete a chart of accounts.
     *
     * @param int $chartid Chart ID.
     * @return bool
     */
    public static function delete_chart(int $chartid): bool {
        global $DB;

        // Delete all accounts in this chart.
        $DB->delete_records('qtype_buchungssatz_accounts', ['chartid' => $chartid]);

        // Delete the chart.
        return $DB->delete_records('qtype_buchungssatz_charts', ['id' => $chartid]);
    }

    /**
     * Get a chart by ID.
     *
     * @param int $chartid Chart ID.
     * @return object|false
     */
    public static function get_chart(int $chartid) {
        global $DB;
        return $DB->get_record('qtype_buchungssatz_charts', ['id' => $chartid]);
    }

    /**
     * Get all charts for a context.
     *
     * @param int $contextid Context ID.
     * @return array
     */
    public static function get_charts_for_context(int $contextid): array {
        global $DB;
        return $DB->get_records('qtype_buchungssatz_charts', ['contextid' => $contextid], 'name ASC');
    }

    /**
     * Add an account to a chart.
     *
     * @param int $chartid Chart ID.
     * @param string $accountnumber Account number.
     * @param string $accountname Account name.
     * @param string $accounttype Account type.
     * @param int $sortorder Sort order.
     * @return int The new account ID.
     */
    public static function add_account(
        int $chartid,
        string $accountnumber,
        string $accountname,
        string $accounttype = 'asset',
        int $sortorder = 0
    ): int {
        global $DB;

        $account = new \stdClass();
        $account->chartid = $chartid;
        $account->accountnumber = $accountnumber;
        $account->accountname = $accountname;
        $account->accounttype = $accounttype;
        $account->sortorder = $sortorder;

        return $DB->insert_record('qtype_buchungssatz_accounts', $account);
    }

    /**
     * Get accounts for a chart.
     *
     * @param int $chartid Chart ID.
     * @return array
     */
    public static function get_accounts(int $chartid): array {
        global $DB;
        return $DB->get_records('qtype_buchungssatz_accounts',
            ['chartid' => $chartid], 'sortorder, accountnumber');
    }

    /**
     * Update an account.
     *
     * @param int $accountid Account ID.
     * @param string $accountnumber Account number.
     * @param string $accountname Account name.
     * @param string $accounttype Account type.
     * @return bool
     */
    public static function update_account(
        int $accountid,
        string $accountnumber,
        string $accountname,
        string $accounttype
    ): bool {
        global $DB;

        $account = $DB->get_record('qtype_buchungssatz_accounts', ['id' => $accountid], '*', MUST_EXIST);
        $account->accountnumber = $accountnumber;
        $account->accountname = $accountname;
        $account->accounttype = $accounttype;

        return $DB->update_record('qtype_buchungssatz_accounts', $account);
    }

    /**
     * Delete an account.
     *
     * @param int $accountid Account ID.
     * @return bool
     */
    public static function delete_account(int $accountid): bool {
        global $DB;
        return $DB->delete_records('qtype_buchungssatz_accounts', ['id' => $accountid]);
    }

    /**
     * Import accounts from CSV content.
     *
     * Expected CSV format: accountnumber,accountname,accounttype
     *
     * @param int $chartid Chart ID.
     * @param string $csvcontent CSV content.
     * @param string $delimiter CSV delimiter.
     * @return array ['imported' => int, 'errors' => array]
     */
    public static function import_from_csv(int $chartid, string $csvcontent, string $delimiter = ','): array {
        global $DB;

        $result = ['imported' => 0, 'errors' => []];
        $lines = explode("\n", $csvcontent);
        $sortorder = 0;

        // Get existing accounts to avoid duplicates.
        $existing = $DB->get_records_menu('qtype_buchungssatz_accounts',
            ['chartid' => $chartid], '', 'accountnumber, id');

        foreach ($lines as $linenum => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Skip header line.
            if ($linenum === 0 && stripos($line, 'accountnumber') !== false) {
                continue;
            }

            $parts = str_getcsv($line, $delimiter);

            if (count($parts) < 2) {
                $result['errors'][] = "Line " . ($linenum + 1) . ": Invalid format";
                continue;
            }

            $accountnumber = trim($parts[0]);
            $accountname = trim($parts[1]);
            $accounttype = isset($parts[2]) ? trim($parts[2]) : 'asset';

            // Validate account type.
            $validtypes = ['asset', 'liability', 'equity', 'revenue', 'expense'];
            if (!in_array($accounttype, $validtypes)) {
                $accounttype = 'asset';
            }

            // Skip if account already exists.
            if (isset($existing[$accountnumber])) {
                continue;
            }

            try {
                self::add_account($chartid, $accountnumber, $accountname, $accounttype, $sortorder++);
                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = "Line " . ($linenum + 1) . ": " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Export accounts to CSV.
     *
     * @param int $chartid Chart ID.
     * @return string CSV content.
     */
    public static function export_to_csv(int $chartid): string {
        $accounts = self::get_accounts($chartid);

        $csv = "accountnumber,accountname,accounttype\n";

        foreach ($accounts as $account) {
            $csv .= '"' . str_replace('"', '""', $account->accountnumber) . '",';
            $csv .= '"' . str_replace('"', '""', $account->accountname) . '",';
            $csv .= '"' . $account->accounttype . "\"\n";
        }

        return $csv;
    }

    /**
     * Create a default German SKR03 chart of accounts (simplified).
     *
     * @param int $contextid Context ID.
     * @return int Chart ID.
     */
    public static function create_default_skr03(int $contextid): int {
        $chartid = self::create_chart('SKR03 (vereinfacht)', 'Standardkontenrahmen 03 - vereinfacht', $contextid);

        $accounts = [
            // Aktivkonten (Assets).
            ['0400', 'Technische Anlagen und Maschinen', 'asset'],
            ['0420', 'Geschäftsausstattung', 'asset'],
            ['0650', 'Büroeinrichtung', 'asset'],
            ['1000', 'Kasse', 'asset'],
            ['1200', 'Bank', 'asset'],
            ['1400', 'Forderungen aus Lieferungen und Leistungen', 'asset'],
            ['1576', 'Abziehbare Vorsteuer 19%', 'asset'],
            ['1571', 'Abziehbare Vorsteuer 7%', 'asset'],

            // Passivkonten (Liabilities).
            ['1600', 'Verbindlichkeiten aus Lieferungen und Leistungen', 'liability'],
            ['1776', 'Umsatzsteuer 19%', 'liability'],
            ['1771', 'Umsatzsteuer 7%', 'liability'],

            // Eigenkapital (Equity).
            ['0800', 'Gezeichnetes Kapital', 'equity'],
            ['0860', 'Gewinnrücklagen', 'equity'],
            ['9000', 'Eigenkapital', 'equity'],

            // Erträge (Revenue).
            ['8000', 'Erlöse 19% USt', 'revenue'],
            ['8100', 'Erlöse 7% USt', 'revenue'],
            ['8200', 'Erlöse steuerfrei', 'revenue'],

            // Aufwendungen (Expenses).
            ['3000', 'Rohstoffe', 'expense'],
            ['3100', 'Fertigungsmaterial', 'expense'],
            ['3400', 'Wareneinkauf 19%', 'expense'],
            ['4100', 'Löhne', 'expense'],
            ['4120', 'Gehälter', 'expense'],
            ['4200', 'Sozialversicherung', 'expense'],
            ['4210', 'Arbeitgeberanteil Sozialversicherung', 'expense'],
            ['4400', 'Abschreibungen auf Sachanlagen', 'expense'],
            ['4500', 'Fahrzeugkosten', 'expense'],
            ['4600', 'Werbekosten', 'expense'],
            ['4700', 'Kosten des Geldverkehrs', 'expense'],
            ['4800', 'Reparaturen und Instandhaltung', 'expense'],
            ['4900', 'Fremdleistungen', 'expense'],
            ['4910', 'Porto', 'expense'],
            ['4920', 'Telefon', 'expense'],
            ['4930', 'Bürobedarf', 'expense'],
            ['4940', 'Zeitschriften, Bücher', 'expense'],
            ['4950', 'Rechts- und Beratungskosten', 'expense'],
            ['4960', 'Miete', 'expense'],
            ['4970', 'Nebenkosten des Geldverkehrs', 'expense'],
        ];

        $sortorder = 0;
        foreach ($accounts as $account) {
            self::add_account($chartid, $account[0], $account[1], $account[2], $sortorder++);
        }

        return $chartid;
    }
}
