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
 * Helper class for importing chart of accounts from text or ODS files.
 *
 * Each non-empty line is one account name (verbatim, the whole line is the name).
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_helper {
    /** @var int Maximum uncompressed ODS content size (16 MiB). */
    private const MAX_ODS_SIZE = 16777216;

    /**
     * Parse an uploaded chart, selecting the reader by its filename.
     *
     * @param string $data Raw file content.
     * @param string $filename Original filename.
     * @return array Chart name and accounts.
     */
    public static function parse_file(string $data, string $filename = ''): array {
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'ods') {
            return self::parse_ods($data, $filename);
        }
        return self::parse_csv($data, $filename);
    }

    /**
     * Read column A of the first worksheet without extracting archive entries.
     *
     * Repeated rows need only be read once because account names are deduplicated.
     * Formulas are never evaluated; only their saved display text is read.
     *
     * @param string $data Raw ODS content.
     * @param string $filename Original filename.
     * @return array Chart name and accounts.
     * @throws \moodle_exception If the spreadsheet is invalid or too large.
     */
    private static function parse_ods(string $data, string $filename): array {
        $xml = self::read_ods_content($data);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            // Do not load DTDs or substitute entities from an uploaded document.
            if ($xml === '' || !$document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                throw new \moodle_exception('odsinvalid', 'qtype_accounting');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
        $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
        $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
        $sheet = $xpath->query('/office:document-content/office:body/office:spreadsheet/table:table[1]')->item(0);
        if (!$sheet) {
            throw new \moodle_exception('odsinvalid', 'qtype_accounting');
        }
        self::expand_ods_spaces($xpath, $sheet);

        $lines = [];
        foreach ($xpath->query('.//table:table-row', $sheet) as $row) {
            $cell = $xpath->query('./table:table-cell | ./table:covered-table-cell', $row)->item(0);
            if (!$cell || $cell->localName === 'covered-table-cell') {
                continue;
            }
            $paragraphs = [];
            foreach ($xpath->query('./text:p', $cell) as $paragraph) {
                $paragraphs[] = $paragraph->textContent;
            }
            $lines[] = preg_replace('/\R/u', ' ', implode(' ', $paragraphs));
        }

        return self::parse_csv(implode("\n", $lines), $filename);
    }

    /**
     * Read bounded XML content from an ODS archive, cleaning up the temporary file.
     *
     * @param string $data Raw archive bytes.
     * @return string Content XML.
     * @throws \moodle_exception If the archive cannot be read.
     */
    private static function read_ods_content(string $data): string {
        $path = make_request_directory() . '/chart.ods';
        file_put_contents($path, $data);
        $zip = new \ZipArchive();
        try {
            if ($zip->open($path) !== true) {
                throw new \moodle_exception('odsinvalid', 'qtype_accounting');
            }
            try {
                $entry = $zip->statName('content.xml');
                if (!$entry || $entry['size'] > self::MAX_ODS_SIZE) {
                    throw new \moodle_exception('odsinvalid', 'qtype_accounting');
                }
                $xml = $zip->getFromName('content.xml', self::MAX_ODS_SIZE + 1);
                if ($xml === false) {
                    throw new \moodle_exception('odsinvalid', 'qtype_accounting');
                }
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }

        return $xml;
    }

    /**
     * Expand ODF whitespace elements to text, bounded by the account name length.
     *
     * @param \DOMXPath $xpath Namespace-aware query helper.
     * @param \DOMElement $sheet First worksheet.
     */
    private static function expand_ods_spaces(\DOMXPath $xpath, \DOMElement $sheet): void {
        // ODF represents spaces and tabs with elements rather than text nodes.
        foreach ($xpath->query('.//text:s | .//text:tab | .//text:line-break', $sheet) as $space) {
            $count = $space->localName === 's' ? max(1, (int)$space->getAttributeNS(
                'urn:oasis:names:tc:opendocument:xmlns:text:1.0',
                'c'
            )) : 1;
            $space->appendChild($sheet->ownerDocument->createTextNode(str_repeat(' ', min($count, 255))));
        }
    }

    /** @var array Known header keywords to skip (lowercase). User-supplied CSV headers
     *             may be in English or German — list both to remain language-agnostic. */
    public const HEADER_KEYWORDS = [
        'accountname',
        'account name',
        'account',
        'name',
        'kontoname',
        'kontenname',
        'konto',
        'bezeichnung',
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
            throw new \Exception(get_string('csvempty', 'qtype_accounting'));
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
            throw new \Exception(get_string('csvnoentries', 'qtype_accounting'));
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
            $charts = $DB->get_records('qtype_accounting_charts', ['contextid' => $contextid]);
        } else {
            $charts = $DB->get_records('qtype_accounting_charts');
        }

        foreach ($charts as $chart) {
            $chartaccounts = $DB->get_records('qtype_accounting_accounts', ['chartid' => $chart->id]);
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
