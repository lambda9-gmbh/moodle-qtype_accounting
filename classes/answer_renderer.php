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

/**
 * Correct-answer display for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Builds the table that shows the correct answer in review mode.
 *
 * Kept separate from the main renderer so the renderer can focus on the
 * student-facing question form. The renderer's public correct_response()
 * method delegates here.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_renderer {
    /**
     * Render the correct-answer table for a question.
     *
     * @param object $question The question definition (entries, chartofaccountsid, numberformat).
     * @param array $accounts Account records keyed by ID (already loaded by the caller).
     * @return string The HTML for the correct-answer block, or '' if the question has no entries.
     */
    public function render(object $question, array $accounts): string {
        if (empty($question->entries)) {
            return '';
        }
        $html = \html_writer::start_div('accounting-correct-response');
        $html .= \html_writer::tag('p', get_string('correctansweris', 'qtype_accounting'));
        $html .= \html_writer::start_tag('table', ['class' => 'table table-bordered accounting-solution']);
        $html .= $this->render_header();
        $html .= $this->render_body($question, $accounts);
        $html .= \html_writer::end_tag('table');
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render the two-row table header (Debit/Credit + Account/Amount).
     *
     * @return string The HTML for thead.
     */
    protected function render_header(): string {
        $html = \html_writer::start_tag('thead');
        $html .= \html_writer::start_tag('tr');
        $html .= \html_writer::tag('th', get_string('debit', 'qtype_accounting'), ['colspan' => 2]);
        $html .= \html_writer::tag('th', get_string('credit', 'qtype_accounting'), ['colspan' => 2]);
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::start_tag('tr');
        $html .= \html_writer::tag('th', get_string('account', 'qtype_accounting'));
        $html .= \html_writer::tag('th', get_string('amount', 'qtype_accounting'));
        $html .= \html_writer::tag('th', get_string('account', 'qtype_accounting'));
        $html .= \html_writer::tag('th', get_string('amount', 'qtype_accounting'));
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::end_tag('thead');
        return $html;
    }

    /**
     * Render the tbody: one data row per entry, plus an optional explanation row.
     *
     * @param object $question The question definition.
     * @param array $accounts Account records keyed by ID.
     * @return string The HTML for tbody.
     */
    protected function render_body(object $question, array $accounts): string {
        $numberformat = $question->numberformat ?? 'de';
        $labels = [
            'debit' => [
                'account' => get_string('debitaccount', 'qtype_accounting'),
                'amount' => get_string('debitamount', 'qtype_accounting'),
            ],
            'credit' => [
                'account' => get_string('creditaccount', 'qtype_accounting'),
                'amount' => get_string('creditamount', 'qtype_accounting'),
            ],
        ];
        $html = \html_writer::start_tag('tbody');
        foreach ($question->entries as $entry) {
            $html .= $this->render_entry_row($entry, $accounts, $numberformat, $labels);
            if (!empty($entry['explanation'])) {
                $html .= $this->render_explanation_row($entry['explanation']);
            }
        }
        $html .= \html_writer::end_tag('tbody');
        return $html;
    }

    /**
     * Render a single data row showing the correct entry.
     *
     * @param array $entry The entry record (debitaccountid/debitamount/creditaccountid/creditamount).
     * @param array $accounts Account records keyed by ID.
     * @param string $numberformat 'de' or 'us'.
     * @param array $labels The per-cell data-label strings.
     * @return string The HTML for one tr.
     */
    protected function render_entry_row(array $entry, array $accounts, string $numberformat, array $labels): string {
        $debitname = entry_helper::format_account_display_by_id((int)($entry['debitaccountid'] ?? 0), $accounts);
        $creditname = entry_helper::format_account_display_by_id((int)($entry['creditaccountid'] ?? 0), $accounts);
        $html = \html_writer::start_tag('tr');
        $html .= \html_writer::tag(
            'td',
            s($debitname),
            ['data-label' => $labels['debit']['account'], 'style' => 'text-align: start;']
        );
        $html .= \html_writer::tag(
            'td',
            $this->format_amount($entry['debitamount'], $numberformat),
            ['data-label' => $labels['debit']['amount'], 'style' => 'text-align: end;']
        );
        $html .= \html_writer::tag(
            'td',
            s($creditname),
            ['data-label' => $labels['credit']['account'], 'style' => 'text-align: start;']
        );
        $html .= \html_writer::tag(
            'td',
            $this->format_amount($entry['creditamount'], $numberformat),
            ['data-label' => $labels['credit']['amount'], 'style' => 'text-align: end;']
        );
        $html .= \html_writer::end_tag('tr');
        return $html;
    }

    /**
     * Render the explanation row for an entry that has a teacher-provided explanation.
     *
     * @param string $explanation The explanation text.
     * @return string The HTML for the explanation tr.
     */
    protected function render_explanation_row(string $explanation): string {
        $body = \html_writer::tag('strong', get_string('explanation', 'qtype_accounting') . ': ') . s($explanation);
        $html = \html_writer::start_tag('tr', ['class' => 'accounting-explanation-row']);
        $html .= \html_writer::tag(
            'td',
            $body,
            ['colspan' => 4, 'style' => 'background-color: #fff3cd; color: #856404;']
        );
        $html .= \html_writer::end_tag('tr');
        return $html;
    }

    /**
     * Format an amount for display, showing empty for zero/non-existent amounts.
     *
     * @param float $amount The amount value.
     * @param string $format 'de' (German 1.234,56) or 'us' (US 1,234.56).
     * @return string The formatted amount or '' if effectively zero.
     */
    public function format_amount(float $amount, string $format = 'de'): string {
        if (abs($amount) < 0.01) {
            return '';
        }
        if ($format === 'us') {
            return number_format($amount, 2, '.', ',');
        }
        return number_format($amount, 2, ',', '.');
    }
}
