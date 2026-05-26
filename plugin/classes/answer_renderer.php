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
 * Correct-answer display for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_buchungssatz;

/**
 * Builds the table that shows the correct answer in review mode.
 *
 * Kept separate from the main renderer so the renderer can focus on the
 * student-facing question form. The renderer's public correct_response()
 * method delegates here.
 *
 * @package    qtype_buchungssatz
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
        $html = \html_writer::start_div('buchungssatz-correct-response');
        $html .= \html_writer::tag('p', get_string('correctansweris', 'qtype_buchungssatz'));
        $html .= \html_writer::start_tag('table', ['class' => 'table table-bordered buchungssatz-solution']);
        $html .= $this->render_header();
        $html .= $this->render_body($question, $accounts);
        $html .= \html_writer::end_tag('table');
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render the two-row table header (Soll/Haben + Account/Amount).
     *
     * @return string The HTML for thead.
     */
    protected function render_header(): string {
        $html = \html_writer::start_tag('thead');
        $html .= \html_writer::start_tag('tr');
        $html .= \html_writer::tag('th', get_string('soll', 'qtype_buchungssatz'), ['colspan' => 2]);
        $html .= \html_writer::tag('th', get_string('haben', 'qtype_buchungssatz'), ['colspan' => 2]);
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::start_tag('tr');
        $html .= \html_writer::tag('th', get_string('account', 'qtype_buchungssatz'));
        $html .= \html_writer::tag('th', get_string('amount', 'qtype_buchungssatz'));
        $html .= \html_writer::tag('th', get_string('account', 'qtype_buchungssatz'));
        $html .= \html_writer::tag('th', get_string('amount', 'qtype_buchungssatz'));
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
            'soll' => [
                'account' => get_string('sollkonto', 'qtype_buchungssatz'),
                'amount' => get_string('sollbetrag', 'qtype_buchungssatz'),
            ],
            'haben' => [
                'account' => get_string('habenkonto', 'qtype_buchungssatz'),
                'amount' => get_string('habenbetrag', 'qtype_buchungssatz'),
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
     * @param array $entry The entry record (sollkontoid/sollbetrag/habenkontoid/habenbetrag).
     * @param array $accounts Account records keyed by ID.
     * @param string $numberformat 'de' or 'us'.
     * @param array $labels The per-cell data-label strings.
     * @return string The HTML for one tr.
     */
    protected function render_entry_row(array $entry, array $accounts, string $numberformat, array $labels): string {
        $sollname = entry_helper::format_account_display_by_id((int)($entry['sollkontoid'] ?? 0), $accounts);
        $habenname = entry_helper::format_account_display_by_id((int)($entry['habenkontoid'] ?? 0), $accounts);
        $html = \html_writer::start_tag('tr');
        $html .= \html_writer::tag(
            'td',
            s($sollname),
            ['data-label' => $labels['soll']['account'], 'style' => 'text-align: start;']
        );
        $html .= \html_writer::tag(
            'td',
            $this->format_amount($entry['sollbetrag'], $numberformat),
            ['data-label' => $labels['soll']['amount'], 'style' => 'text-align: end;']
        );
        $html .= \html_writer::tag(
            'td',
            s($habenname),
            ['data-label' => $labels['haben']['account'], 'style' => 'text-align: start;']
        );
        $html .= \html_writer::tag(
            'td',
            $this->format_amount($entry['habenbetrag'], $numberformat),
            ['data-label' => $labels['haben']['amount'], 'style' => 'text-align: end;']
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
        $body = \html_writer::tag('strong', get_string('explanation', 'qtype_buchungssatz') . ': ') . s($explanation);
        $html = \html_writer::start_tag('tr', ['class' => 'buchungssatz-explanation-row']);
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
