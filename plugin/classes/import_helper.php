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

/**
 * Helper class for importing accounting entries from CSV.
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
     * Detect column mapping from header or data row.
     *
     * @param array $firstrow First row (possibly header).
     * @return array Mapping information with 'has_header' and 'columns' keys.
     */
    public static function detect_column_mapping(array $firstrow): array {
        $hasheader = false;
        $columns = [];

        // Check for known header patterns (order matters - more specific patterns first).
        $headerpatterns = [
            'sollbetrag' => ['sollbetrag', 'soll betrag', 'soll-betrag', 'betrag soll', 'debit amount'],
            'habenbetrag' => ['habenbetrag', 'haben betrag', 'haben-betrag', 'betrag haben', 'credit amount'],
            'sollname' => ['sollname', 'soll name', 'sollkontoname', 'soll konto name', 'soll-kontoname', 'debit name'],
            'habenname' => ['habenname', 'haben name', 'habenkontoname', 'haben konto name', 'haben-kontoname', 'credit name'],
            'sollkonto' => ['sollkonto', 'soll-konto', 'konto soll', 'debit account', 'debit', 'soll'],
            'habenkonto' => ['habenkonto', 'haben-konto', 'konto haben', 'credit account', 'credit', 'haben'],
        ];

        // Try to match headers.
        foreach ($firstrow as $colindex => $colvalue) {
            $colvalue = strtolower(trim($colvalue));
            foreach ($headerpatterns as $field => $patterns) {
                // Skip if this field is already mapped.
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

        // If no header detected, assume standard column order.
        if (!$hasheader) {
            $numcols = count($firstrow);

            if ($numcols >= 6) {
                // Full format: Account, Name, Amount for both sides.
                $columns = [
                    'sollkonto' => 0,
                    'sollname' => 1,
                    'sollbetrag' => 2,
                    'habenkonto' => 3,
                    'habenname' => 4,
                    'habenbetrag' => 5,
                ];
            } else if ($numcols >= 4) {
                // Compact format: Account, Amount for both sides.
                $columns = [
                    'sollkonto' => 0,
                    'sollbetrag' => 1,
                    'habenkonto' => 2,
                    'habenbetrag' => 3,
                ];
            } else if ($numcols >= 2) {
                // Minimal format: Just account numbers.
                $columns = [
                    'sollkonto' => 0,
                    'habenkonto' => 1,
                    'sollbetrag' => $numcols > 2 ? 2 : -1,
                    'habenbetrag' => $numcols > 3 ? 3 : -1,
                ];
            }

            // Check if first row looks like data (contains numbers).
            $firstcol = $firstrow[0] ?? '';
            if (preg_match('/^\d/', $firstcol)) {
                $hasheader = false;
            } else {
                // Might be a header we didn't recognize.
                $hasheader = true;
            }
        }

        return [
            'has_header' => $hasheader,
            'columns' => $columns,
        ];
    }

    /**
     * Parse amount string to float, handling German number format.
     *
     * @param string $amount Amount string.
     * @return string Formatted amount with 2 decimal places.
     */
    public static function parse_amount(string $amount): string {
        // Remove currency symbols and whitespace.
        $amount = preg_replace('/[€$£\s]/', '', trim($amount));

        // Handle German number format (1.234,56 -> 1234.56).
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d{2})?$/', $amount)) {
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);
        } else {
            // Handle comma as decimal separator without thousand separators.
            $amount = str_replace(',', '.', $amount);
        }

        $value = floatval($amount);
        return number_format($value, 2, '.', '');
    }

    /**
     * Guess account type based on account number (SKR03/SKR04 pattern).
     *
     * @param string $accountnumber Account number.
     * @return string Account type (asset, liability, equity, revenue, expense).
     */
    public static function guess_account_type(string $accountnumber): string {
        $first = substr($accountnumber, 0, 1);

        switch ($first) {
            case '0':
            case '1':
                return 'asset';
            case '2':
                return 'liability';
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
                return 'expense';
            case '8':
                return 'revenue';
            case '9':
                return 'equity';
            default:
                return 'asset';
        }
    }

    /**
     * Parse CSV data into rows.
     *
     * @param string $csvdata Raw CSV data.
     * @return array Array with 'rows', 'delimiter', 'mapping' keys.
     * @throws \Exception If CSV is empty or has no data rows.
     */
    public static function parse_csv(string $csvdata): array {
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

        if (count($rows) < 2) {
            throw new \Exception(get_string('csvnodata', 'qtype_buchungssatz'));
        }

        $mapping = self::detect_column_mapping($rows[0]);

        return [
            'rows' => $rows,
            'delimiter' => $delimiter,
            'mapping' => $mapping,
        ];
    }

    /**
     * Extract entries from parsed CSV rows.
     *
     * @param array $rows The CSV rows.
     * @param array $mapping The column mapping.
     * @return array Array with 'accounts' and 'entries' keys.
     * @throws \Exception If no valid entries found.
     */
    public static function extract_entries(array $rows, array $mapping): array {
        $hasheader = $mapping['has_header'];
        $colmap = $mapping['columns'];

        // Validate required columns.
        if (!isset($colmap['sollkonto']) || !isset($colmap['habenkonto'])) {
            throw new \Exception(get_string('csvinvalidformat', 'qtype_buchungssatz'));
        }

        // Skip header if present.
        $datarows = $hasheader ? array_slice($rows, 1) : $rows;

        $accounts = [];
        $entries = [];

        foreach ($datarows as $row) {
            // Get debit (Soll) account.
            $sollkonto = trim($row[$colmap['sollkonto']] ?? '');
            $sollname = isset($colmap['sollname']) ? trim($row[$colmap['sollname']] ?? '') : $sollkonto;
            $sollbetrag = self::parse_amount($row[$colmap['sollbetrag']] ?? '0');

            // Get credit (Haben) account.
            $habenkonto = trim($row[$colmap['habenkonto']] ?? '');
            $habenname = isset($colmap['habenname']) ? trim($row[$colmap['habenname']] ?? '') : $habenkonto;
            $habenbetrag = self::parse_amount($row[$colmap['habenbetrag']] ?? '0');

            // Skip empty rows.
            if (empty($sollkonto) && empty($habenkonto)) {
                continue;
            }

            // Collect unique accounts.
            if (!empty($sollkonto) && !isset($accounts[$sollkonto])) {
                $accounts[$sollkonto] = $sollname ?: $sollkonto;
            }
            if (!empty($habenkonto) && !isset($accounts[$habenkonto])) {
                $accounts[$habenkonto] = $habenname ?: $habenkonto;
            }

            // Add entry.
            $entries[] = [
                'sollkonto' => $sollkonto,
                'habenkonto' => $habenkonto,
                'sollbetrag' => $sollbetrag,
                'habenbetrag' => $habenbetrag,
            ];
        }

        if (empty($entries)) {
            throw new \Exception(get_string('csvnoentries', 'qtype_buchungssatz'));
        }

        return [
            'accounts' => $accounts,
            'entries' => $entries,
        ];
    }

    /**
     * Find an existing chart that contains all the required accounts.
     *
     * @param array $accounts Array of account numbers => names to match.
     * @return int|null Chart ID if found, null otherwise.
     */
    public static function find_matching_chart(array $accounts): ?int {
        global $DB;

        $requiredaccounts = array_keys($accounts);
        sort($requiredaccounts);

        $charts = $DB->get_records('qtype_buchungssatz_charts');

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
                return (int)$chart->id;
            }
        }

        return null;
    }
}
