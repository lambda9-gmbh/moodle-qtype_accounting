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
 * Helper class for importing chart of accounts from CSV.
 *
 * Supports two CSV formats:
 *
 * Multi-column format:
 * Liste;Kontokl;Kontonr;Name
 * Kontenplan LTN 2.08.2024;0;01000;Immaterielle Vermögensgegenstände
 *
 * Where:
 * - Liste: Chart name (same value for all rows)
 * - Kontokl: Account class (0-5)
 * - Kontonr: Account number
 * - Name: Account name
 *
 * Simple format (one account per line):
 * 0110 Immaterielle Vermögensgegenstände
 * 0200 Grundstücke
 *
 * Where each line is: account number, whitespace, account name.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_helper {

    /**
     * Detect the CSV delimiter from a line.
     *
     * @param string $line First line of CSV.
     * @return string The detected delimiter (tab, semicolon, or comma).
     */
    public static function detect_delimiter(string $line): string {
        $tabcount = substr_count($line, "\t");
        $semicoloncount = substr_count($line, ';');
        $commacount = substr_count($line, ',');

        if ($tabcount >= $semicoloncount && $tabcount >= $commacount) {
            return "\t";
        }
        if ($semicoloncount >= $commacount) {
            return ';';
        }
        return ',';
    }

    /**
     * Detect column mapping from header row.
     *
     * Expected columns: Liste, Kontokl, Kontonr, Name
     *
     * @param array $firstrow First row (header).
     * @return array Mapping information with 'has_header' and 'columns' keys.
     */
    public static function detect_column_mapping(array $firstrow): array {
        $columns = [];

        // Header patterns for the new format.
        $headerpatterns = [
            'liste' => ['liste', 'list', 'name', 'chartname', 'chart name', 'kontenplan'],
            'kontokl' => ['kontokl', 'kontoklasse', 'accountclass', 'account class', 'class', 'klasse'],
            'kontonr' => ['kontonr', 'kontonummer', 'accountnumber', 'account number', 'number', 'nummer', 'konto'],
            'name' => ['name', 'accountname', 'account name', 'bezeichnung', 'beschreibung'],
        ];

        // Try to match headers.
        $hasheader = false;
        foreach ($firstrow as $colindex => $colvalue) {
            $colvalue = strtolower(trim($colvalue));
            foreach ($headerpatterns as $field => $patterns) {
                if (isset($columns[$field])) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if ($colvalue === $pattern || strpos($colvalue, $pattern) !== false) {
                        $columns[$field] = $colindex;
                        $hasheader = true;
                        break 2;
                    }
                }
            }
        }

        // If no header detected, assume standard column order: Liste, Kontokl, Kontonr, Name.
        if (!$hasheader) {
            $numcols = count($firstrow);
            if ($numcols >= 4) {
                $columns = [
                    'liste' => 0,
                    'kontokl' => 1,
                    'kontonr' => 2,
                    'name' => 3,
                ];
            }

            // Check if first row looks like data by examining the account number column (index 2).
            // If Kontonr (account number) starts with a digit, it's a data row.
            // If it doesn't, it's likely a header row.
            $accountnumbercol = trim($firstrow[2] ?? '');
            if (!empty($accountnumbercol) && !preg_match('/^\d/', $accountnumbercol)) {
                // Account number doesn't start with digit - looks like a header.
                $hasheader = true;
            }
        }

        return [
            'has_header' => $hasheader,
            'columns' => $columns,
        ];
    }

    /**
     * Validate account class value (must be 0-5).
     *
     * @param mixed $value Value to validate.
     * @return int Valid account class (0-5), defaults to 0 if invalid.
     */
    public static function validate_account_class($value): int {
        $intval = (int) $value;
        if ($intval < 0 || $intval > 5) {
            return 0;
        }
        return $intval;
    }

    /**
     * Parse CSV data into chart information.
     *
     * @param string $csvdata Raw CSV data.
     * @param string $filename Optional original filename (used as chart name for simple format).
     * @return array Array with 'chartname' and 'accounts' keys.
     * @throws \Exception If CSV is empty, invalid format, or has no data rows.
     */
    public static function parse_csv(string $csvdata, string $filename = ''): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvdata));
        if (empty($lines)) {
            throw new \Exception(get_string('csvempty', 'qtype_buchungssatz'));
        }

        $delimiter = self::detect_delimiter($lines[0]);

        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

        // Detect simple format (single column: "accountnumber accountname" per line).
        if (self::is_simple_format($rows)) {
            return self::parse_simple_format($lines, $filename);
        }

        if (count($rows) < 2) {
            throw new \Exception(get_string('csvnodata', 'qtype_buchungssatz'));
        }

        $mapping = self::detect_column_mapping($rows[0]);
        $colmap = $mapping['columns'];

        // Validate required columns.
        $requiredcols = ['kontokl', 'kontonr', 'name'];
        foreach ($requiredcols as $col) {
            if (!isset($colmap[$col])) {
                throw new \Exception(get_string('csvinvalidformat', 'qtype_buchungssatz'));
            }
        }

        // Skip header if present.
        $datarows = $mapping['has_header'] ? array_slice($rows, 1) : $rows;

        if (empty($datarows)) {
            throw new \Exception(get_string('csvnodata', 'qtype_buchungssatz'));
        }

        // Extract chart name from first data row's Liste column.
        $chartname = '';
        if (isset($colmap['liste'])) {
            $chartname = trim($datarows[0][$colmap['liste']] ?? '');
        }
        if (empty($chartname)) {
            $chartname = 'Imported Chart ' . date('Y-m-d H:i');
        }

        // Extract accounts.
        $accounts = [];
        $sortorder = 0;
        foreach ($datarows as $row) {
            $kontonr = trim($row[$colmap['kontonr']] ?? '');
            $name = trim($row[$colmap['name']] ?? '');
            $kontokl = self::validate_account_class($row[$colmap['kontokl']] ?? 0);

            // Skip empty rows.
            if (empty($kontonr)) {
                continue;
            }

            // Skip duplicates (keep first occurrence).
            if (isset($accounts[$kontonr])) {
                continue;
            }

            $accounts[$kontonr] = [
                'accountnumber' => $kontonr,
                'accountname' => $name ?: $kontonr,
                'accountclass' => $kontokl,
                'sortorder' => $sortorder++,
            ];
        }

        if (empty($accounts)) {
            throw new \Exception(get_string('csvnoentries', 'qtype_buchungssatz'));
        }

        return [
            'chartname' => $chartname,
            'accounts' => $accounts,
        ];
    }

    /**
     * Detect if the parsed rows are in simple single-column format.
     *
     * Simple format: each line is "accountnumber accountname" with no CSV delimiters.
     *
     * @param array $rows Parsed CSV rows.
     * @return bool True if simple format detected.
     */
    public static function is_simple_format(array $rows): bool {
        // If any row has multiple columns, it's the multi-column format.
        foreach ($rows as $row) {
            if (count($row) > 1) {
                return false;
            }
        }
        // Check that at least one row starts with a digit (account number).
        foreach ($rows as $row) {
            if (preg_match('/^\d/', trim($row[0]))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse simple single-column format where each line is "accountnumber accountname".
     *
     * @param array $lines Raw lines from the CSV file.
     * @param string $filename Optional original filename (used as chart name, without extension).
     * @return array Array with 'chartname' and 'accounts' keys.
     * @throws \Exception If no valid entries found.
     */
    public static function parse_simple_format(array $lines, string $filename = ''): array {
        $accounts = [];
        $sortorder = 0;

        // Use filename (without extension) as chart name, or fall back to auto-generated name.
        if (!empty($filename)) {
            $chartname = pathinfo($filename, PATHINFO_FILENAME);
        } else {
            $chartname = 'Imported Chart ' . date('Y-m-d H:i');
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Skip header lines (lines that don't start with a digit).
            if (!preg_match('/^\d/', $line)) {
                continue;
            }

            // Split on first whitespace: account number + account name.
            $parts = preg_split('/\s+/', $line, 2);
            $kontonr = $parts[0];
            $name = isset($parts[1]) ? trim($parts[1]) : $kontonr;

            // Skip duplicates (keep first occurrence).
            if (isset($accounts[$kontonr])) {
                continue;
            }

            $accounts[$kontonr] = [
                'accountnumber' => $kontonr,
                'accountname' => $name,
                'accountclass' => 0,
                'sortorder' => $sortorder++,
            ];
        }

        if (empty($accounts)) {
            throw new \Exception(get_string('csvnoentries', 'qtype_buchungssatz'));
        }

        return [
            'chartname' => $chartname,
            'accounts' => $accounts,
        ];
    }

    /**
     * Find an existing chart that contains all the required accounts.
     *
     * @param array $accounts Array of account data to match (keyed by account number).
     * @param int $contextid Optional context ID to scope the search to. 0 means all contexts.
     * @return int|null Chart ID if found, null otherwise.
     */
    public static function find_matching_chart(array $accounts, int $contextid = 0): ?int {
        global $DB;

        $requiredaccounts = array_keys($accounts);
        sort($requiredaccounts);

        if ($contextid > 0) {
            $charts = $DB->get_records('qtype_buchungssatz_charts', ['contextid' => $contextid]);
        } else {
            $charts = $DB->get_records('qtype_buchungssatz_charts');
        }

        foreach ($charts as $chart) {
            $chartaccounts = $DB->get_records('qtype_buchungssatz_accounts', ['chartid' => $chart->id]);
            $chartaccountnumbers = [];
            foreach ($chartaccounts as $acc) {
                $chartaccountnumbers[] = $acc->accountnumber;
            }
            sort($chartaccountnumbers);

            // Check if this chart contains all required accounts.
            $hasallaccounts = true;
            foreach ($requiredaccounts as $required) {
                if (!in_array($required, $chartaccountnumbers)) {
                    $hasallaccounts = false;
                    break;
                }
            }

            if ($hasallaccounts) {
                return (int) $chart->id;
            }
        }

        return null;
    }
}
