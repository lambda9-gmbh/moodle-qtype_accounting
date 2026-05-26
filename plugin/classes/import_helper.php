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
 * Helper class for importing chart of accounts from a text file.
 *
 * Each non-empty line is one account name (verbatim, the whole line is the name).
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_helper {
    /** @var array Known header keywords to skip (lowercase). */
    public const HEADER_KEYWORDS = [
        'kontoname',
        'kontenname',
        'name',
        'bezeichnung',
        'konto',
        'account',
        'account name',
    ];

    /**
     * Parse text data into chart information.
     *
     * Each non-empty line = one account name (verbatim).
     * Duplicate names are skipped (first occurrence kept).
     * If the first non-empty line matches a known header keyword, it is skipped.
     *
     * @param string $data Raw text data.
     * @param string $filename Optional original filename (used as chart name, without extension).
     * @return array Array with 'chartname' and 'accounts' keys.
     * @throws \Exception If data is empty or has no valid accounts.
     */
    public static function parse_csv(string $data, string $filename = ''): array {
        $data = self::normalize_encoding($data);
        $lines = preg_split('/\r\n|\r|\n/', trim($data));
        if (empty($lines)) {
            throw new \Exception(get_string('csvempty', 'qtype_buchungssatz'));
        }

        // Use filename (without extension) as chart name, or fall back to auto-generated name.
        if (!empty($filename)) {
            $chartname = pathinfo($filename, PATHINFO_FILENAME);
        } else {
            $chartname = 'Imported Chart ' . date('Y-m-d H:i');
        }

        $accounts = [];
        $sortorder = 0;
        $firstnonemptyseen = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Skip the first non-empty line if it matches a known header keyword.
            if (!$firstnonemptyseen) {
                $firstnonemptyseen = true;
                if (in_array(strtolower($line), self::HEADER_KEYWORDS)) {
                    continue;
                }
            }

            // Skip duplicates (keep first occurrence).
            if (isset($accounts[$line])) {
                continue;
            }

            $accounts[$line] = [
                'accountname' => $line,
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
     * Normalize encoding of input data to UTF-8.
     *
     * Strips UTF-8 BOM if present and converts Windows-1252 encoded data to UTF-8.
     * This ensures compatibility with CSV files exported from Excel.
     *
     * @param string $data Raw input data.
     * @return string UTF-8 encoded data.
     */
    private static function normalize_encoding(string $data): string {
        // Strip UTF-8 BOM if present.
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            $data = substr($data, 3);
        }

        // If not valid UTF-8, assume Windows-1252 (common Excel export encoding).
        if (!mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        }

        return $data;
    }

    /**
     * Find an existing chart that contains all the required accounts.
     *
     * @param array $accounts Array of account data to match (keyed by account name).
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
            $chartaccountnames = [];
            foreach ($chartaccounts as $acc) {
                $chartaccountnames[] = $acc->accountname;
            }
            sort($chartaccountnames);

            // Check if this chart contains all required accounts.
            $hasallaccounts = true;
            foreach ($requiredaccounts as $required) {
                if (!in_array($required, $chartaccountnames)) {
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
