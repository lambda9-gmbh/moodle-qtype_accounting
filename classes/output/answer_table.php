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

namespace qtype_accounting\output;

use qtype_accounting\amount_helper;
use qtype_accounting\currency_helper;
use qtype_accounting\entry_helper;

/**
 * The correct answer of an accounting question, shown in review.
 *
 * Rendered by the template qtype_accounting/answer_table.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_table implements \renderable, \templatable {
    /**
     * Create the table.
     *
     * @param object $question The question with its correct entries, number format and currency.
     * @param array $accounts Account records keyed by ID.
     */
    public function __construct(
        /** @var object The question. */
        protected object $question,
        /** @var array Account records keyed by ID. */
        protected array $accounts
    ) {
    }

    /**
     * Export the table for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array The template context.
     */
    public function export_for_template(\renderer_base $output): array {
        $numberformat = $this->question->numberformat ?? 'de';
        $symbol = currency_helper::get_symbol($this->question->currency ?? null);
        $entries = [];
        foreach ($this->question->entries as $entry) {
            $row = ['explanation' => (string)($entry['explanation'] ?? '')];
            foreach (['debit', 'credit'] as $side) {
                $row[$side . 'account'] = entry_helper::format_account_display_by_id(
                    (int)($entry[$side . 'accountid'] ?? 0),
                    $this->accounts
                );
                $row[$side . 'amount'] = self::format_amount($entry[$side . 'amount'] ?? 0, $numberformat, $symbol);
            }
            $entries[] = $row;
        }
        return ['entries' => $entries];
    }

    /**
     * Format a canonical amount for display; zero amounts of one-sided entries stay empty.
     *
     * @param string|int|float $amount Canonical decimal amount.
     * @param string $numberformat 'de' or 'us'.
     * @param string $symbol Currency symbol shown after non-empty amounts ('' for none).
     * @return string The formatted amount.
     */
    public static function format_amount($amount, string $numberformat, string $symbol = ''): string {
        $cents = amount_helper::parse_cents((string)$amount, 'us');
        if ($cents === 0) {
            return '';
        }
        $formatted = amount_helper::format_cents($cents, $numberformat);
        return $symbol === '' ? $formatted : $formatted . "\u{00A0}" . $symbol;
    }
}
