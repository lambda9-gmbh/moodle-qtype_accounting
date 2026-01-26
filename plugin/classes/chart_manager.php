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
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_manager {

    /**
     * Create a new chart of accounts.
     *
     * @param string $name Chart name.
     * @param int $contextid Context ID.
     * @return int The new chart ID.
     */
    public static function create_chart(string $name, int $contextid): int {
        global $DB, $USER;

        $chart = new \stdClass();
        $chart->name = $name;
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
     * @return bool True on success.
     */
    public static function update_chart(int $chartid, string $name): bool {
        global $DB, $USER;

        $chart = $DB->get_record('qtype_buchungssatz_charts', ['id' => $chartid], '*', MUST_EXIST);
        $chart->name = $name;
        $chart->timemodified = time();
        $chart->usermodified = $USER->id;

        return $DB->update_record('qtype_buchungssatz_charts', $chart);
    }

    /**
     * Delete a chart of accounts.
     *
     * @param int $chartid Chart ID.
     * @return bool True on success.
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
     * @return object|false The chart record or false if not found.
     */
    public static function get_chart(int $chartid) {
        global $DB;
        return $DB->get_record('qtype_buchungssatz_charts', ['id' => $chartid]);
    }

    /**
     * Get all charts for a context.
     *
     * @param int $contextid Context ID.
     * @return array The chart records.
     */
    public static function get_charts_for_context(int $contextid): array {
        global $DB;
        return $DB->get_records('qtype_buchungssatz_charts', ['contextid' => $contextid], 'name ASC');
    }

    /**
     * Find a chart by name within a context.
     *
     * @param string $name Chart name.
     * @param int $contextid Context ID.
     * @return object|false The chart record or false if not found.
     */
    public static function get_chart_by_name(string $name, int $contextid) {
        global $DB;
        return $DB->get_record('qtype_buchungssatz_charts', ['name' => $name, 'contextid' => $contextid]);
    }

    /**
     * Add an account to a chart.
     *
     * @param int $chartid Chart ID.
     * @param string $accountnumber Account number (Kontonr).
     * @param string $accountname Account name (Name).
     * @param int $accountclass Account class 0-5 (Kontokl).
     * @param int $sortorder Sort order.
     * @return int The new account ID.
     */
    public static function add_account(
        int $chartid,
        string $accountnumber,
        string $accountname,
        int $accountclass = 0,
        int $sortorder = 0
    ): int {
        global $DB;

        // Validate accountclass is in range 0-5.
        if ($accountclass < 0 || $accountclass > 5) {
            $accountclass = 0;
        }

        $account = new \stdClass();
        $account->chartid = $chartid;
        $account->accountnumber = $accountnumber;
        $account->accountname = $accountname;
        $account->accountclass = $accountclass;
        $account->sortorder = $sortorder;

        return $DB->insert_record('qtype_buchungssatz_accounts', $account);
    }

    /**
     * Get accounts for a chart.
     *
     * @param int $chartid Chart ID.
     * @return array The account records.
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
     * @param string $accountnumber Account number (Kontonr).
     * @param string $accountname Account name (Name).
     * @param int $accountclass Account class 0-5 (Kontokl).
     * @return bool True on success.
     */
    public static function update_account(
        int $accountid,
        string $accountnumber,
        string $accountname,
        int $accountclass
    ): bool {
        global $DB;

        // Validate accountclass is in range 0-5.
        if ($accountclass < 0 || $accountclass > 5) {
            $accountclass = 0;
        }

        $account = $DB->get_record('qtype_buchungssatz_accounts', ['id' => $accountid], '*', MUST_EXIST);
        $account->accountnumber = $accountnumber;
        $account->accountname = $accountname;
        $account->accountclass = $accountclass;

        return $DB->update_record('qtype_buchungssatz_accounts', $account);
    }

    /**
     * Delete an account.
     *
     * @param int $accountid Account ID.
     * @return bool True on success.
     */
    public static function delete_account(int $accountid): bool {
        global $DB;
        return $DB->delete_records('qtype_buchungssatz_accounts', ['id' => $accountid]);
    }

    /**
     * Import accounts from CSV content to an existing chart.
     *
     * Expected CSV format: Liste;Kontokl;Kontonr;Name
     *
     * @param int $chartid Chart ID.
     * @param string $csvcontent CSV content.
     * @return array ['imported' => int, 'errors' => array]
     */
    public static function import_from_csv(int $chartid, string $csvcontent): array {
        global $DB;

        $result = ['imported' => 0, 'errors' => []];

        try {
            $parsed = import_helper::parse_csv($csvcontent);
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        // Get existing accounts to avoid duplicates.
        $existing = $DB->get_records_menu('qtype_buchungssatz_accounts',
            ['chartid' => $chartid], '', 'accountnumber, id');

        foreach ($parsed['accounts'] as $accountnumber => $accountdata) {
            // Skip if account already exists.
            if (isset($existing[$accountnumber])) {
                continue;
            }

            try {
                self::add_account(
                    $chartid,
                    $accountdata['accountnumber'],
                    $accountdata['accountname'],
                    $accountdata['accountclass'],
                    $accountdata['sortorder']
                );
                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = $accountnumber . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Export accounts to CSV.
     *
     * Exports in format: Liste;Kontokl;Kontonr;Name
     *
     * @param int $chartid Chart ID.
     * @return string CSV content.
     */
    public static function export_to_csv(int $chartid): string {
        $chart = self::get_chart($chartid);
        $accounts = self::get_accounts($chartid);
        $chartname = $chart ? $chart->name : 'Unknown Chart';

        $csv = "Liste;Kontokl;Kontonr;Name\n";

        foreach ($accounts as $account) {
            $csv .= '"' . str_replace('"', '""', $chartname) . '";';
            $csv .= $account->accountclass . ';';
            $csv .= '"' . str_replace('"', '""', $account->accountnumber) . '";';
            $csv .= '"' . str_replace('"', '""', $account->accountname) . "\"\n";
        }

        return $csv;
    }

    /**
     * Import a complete chart of accounts from CSV.
     *
     * Expected CSV format: Liste;Kontokl;Kontonr;Name
     * The chart name is extracted from the "Liste" column.
     *
     * @param string $csvcontent CSV content.
     * @param int $contextid Context ID.
     * @return array ['chartid' => int, 'chartname' => string, 'imported' => int, 'errors' => array]
     */
    public static function import_chart_from_csv(string $csvcontent, int $contextid): array {
        $result = ['chartid' => 0, 'chartname' => '', 'imported' => 0, 'errors' => []];

        try {
            $parsed = import_helper::parse_csv($csvcontent);
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        // Create the chart with name from CSV.
        $chartname = $parsed['chartname'];
        $chartid = self::create_chart($chartname, $contextid);
        $result['chartid'] = $chartid;
        $result['chartname'] = $chartname;

        // Import all accounts.
        foreach ($parsed['accounts'] as $accountnumber => $accountdata) {
            try {
                self::add_account(
                    $chartid,
                    $accountdata['accountnumber'],
                    $accountdata['accountname'],
                    $accountdata['accountclass'],
                    $accountdata['sortorder']
                );
                $result['imported']++;
            } catch (\Exception $e) {
                $result['errors'][] = $accountnumber . ': ' . $e->getMessage();
            }
        }

        // If no accounts were imported, delete the empty chart.
        if ($result['imported'] === 0) {
            self::delete_chart($chartid);
            $result['chartid'] = 0;
            $result['chartname'] = '';
            $result['errors'][] = get_string('csvnoentries', 'qtype_buchungssatz');
        }

        return $result;
    }

    /**
     * Create a default German SKR03 chart of accounts (simplified).
     *
     * @param int $contextid Context ID.
     * @return int Chart ID.
     */
    public static function create_default_skr03(int $contextid): int {
        $chartid = self::create_chart('SKR03 (vereinfacht)', $contextid);

        // Account class mapping:
        // 0 = Anlage- und Kapitalkonten (Fixed assets)
        // 1 = Finanz- und Privatkonten (Financial accounts)
        // 2 = Eigenkapital (Equity) - not used in SKR03
        // 3 = Fremdkapital (Liabilities)
        // 4 = Aufwendungen (Expenses)
        // 5 = Erträge (Revenue) - SKR03 uses 8xxx for revenue.
        $accounts = [
            // Anlagekonten (class 0).
            ['0400', 'Technische Anlagen und Maschinen', 0],
            ['0420', 'Geschäftsausstattung', 0],
            ['0650', 'Büroeinrichtung', 0],

            // Finanzkonten (class 1).
            ['1000', 'Kasse', 1],
            ['1200', 'Bank', 1],
            ['1400', 'Forderungen aus Lieferungen und Leistungen', 1],
            ['1576', 'Abziehbare Vorsteuer 19%', 1],
            ['1571', 'Abziehbare Vorsteuer 7%', 1],
            ['1600', 'Verbindlichkeiten aus Lieferungen und Leistungen', 1],
            ['1776', 'Umsatzsteuer 19%', 1],
            ['1771', 'Umsatzsteuer 7%', 1],

            // Aufwendungen (class 4).
            ['3000', 'Rohstoffe', 4],
            ['3100', 'Fertigungsmaterial', 4],
            ['3400', 'Wareneinkauf 19%', 4],
            ['4100', 'Löhne', 4],
            ['4120', 'Gehälter', 4],
            ['4200', 'Sozialversicherung', 4],
            ['4210', 'Arbeitgeberanteil Sozialversicherung', 4],
            ['4400', 'Abschreibungen auf Sachanlagen', 4],
            ['4500', 'Fahrzeugkosten', 4],
            ['4600', 'Werbekosten', 4],
            ['4700', 'Kosten des Geldverkehrs', 4],
            ['4800', 'Reparaturen und Instandhaltung', 4],
            ['4900', 'Fremdleistungen', 4],
            ['4910', 'Porto', 4],
            ['4920', 'Telefon', 4],
            ['4930', 'Bürobedarf', 4],
            ['4940', 'Zeitschriften, Bücher', 4],
            ['4950', 'Rechts- und Beratungskosten', 4],
            ['4960', 'Miete', 4],
            ['4970', 'Nebenkosten des Geldverkehrs', 4],

            // Erträge (class 5 - using 8xxx numbers for SKR03 compatibility).
            ['8000', 'Erlöse 19% USt', 5],
            ['8100', 'Erlöse 7% USt', 5],
            ['8200', 'Erlöse steuerfrei', 5],

            // Eigenkapital (class 2).
            ['0800', 'Gezeichnetes Kapital', 2],
            ['0860', 'Gewinnrücklagen', 2],
            ['9000', 'Eigenkapital', 2],
        ];

        $sortorder = 0;
        foreach ($accounts as $account) {
            self::add_account($chartid, $account[0], $account[1], $account[2], $sortorder++);
        }

        return $chartid;
    }
}
