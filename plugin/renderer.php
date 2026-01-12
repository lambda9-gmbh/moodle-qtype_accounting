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
 * Buchungssatz question renderer class.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generates the output for Buchungssatz questions.
 */
class qtype_buchungssatz_renderer extends qtype_renderer {

    /**
     * Generate the display of the formulation part of the question.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @return string
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        global $PAGE;

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $questiontext = $question->format_questiontext($qa);
        $result = html_writer::tag('div', $questiontext, ['class' => 'qtext']);

        // Load available accounts for dropdowns.
        $accounts = $this->get_accounts($question->chartofaccountsid);

        // Render entry fields.
        $maxentries = $question->allowmultipleentries ? $question->maxentries : 1;

        $result .= html_writer::start_div('buchungssatz-entries');

        // Header row.
        $result .= $this->render_header_row();

        for ($i = 0; $i < $maxentries; $i++) {
            $result .= $this->render_entry_row($qa, $options, $i, $response, $accounts, $question);
        }

        $result .= html_writer::end_div();

        // Include JavaScript for interactive features.
        $PAGE->requires->js_call_amd('qtype_buchungssatz/question', 'init', [
            $qa->get_outer_question_div_unique_id(),
            $accounts,
        ]);

        return $result;
    }

    /**
     * Render the header row for the booking entries.
     *
     * @return string
     */
    protected function render_header_row(): string {
        $html = html_writer::start_div('buchungssatz-header row');

        $html .= html_writer::div(get_string('soll', 'qtype_buchungssatz'),
            'col-md-6 text-center buchungssatz-soll-header');
        $html .= html_writer::div(get_string('haben', 'qtype_buchungssatz'),
            'col-md-6 text-center buchungssatz-haben-header');

        $html .= html_writer::end_div();

        // Sub-headers.
        $html .= html_writer::start_div('buchungssatz-subheader row');
        $html .= html_writer::div(get_string('account', 'qtype_buchungssatz'), 'col-md-3 text-center');
        $html .= html_writer::div(get_string('amount', 'qtype_buchungssatz'), 'col-md-3 text-center');
        $html .= html_writer::div(get_string('account', 'qtype_buchungssatz'), 'col-md-3 text-center');
        $html .= html_writer::div(get_string('amount', 'qtype_buchungssatz'), 'col-md-3 text-center');
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render a single entry row.
     *
     * @param question_attempt $qa
     * @param question_display_options $options
     * @param int $index
     * @param array $response
     * @param array $accounts
     * @param object $question
     * @return string
     */
    protected function render_entry_row(
        question_attempt $qa,
        question_display_options $options,
        int $index,
        array $response,
        array $accounts,
        object $question
    ): string {
        $sollkontoname = $qa->get_qt_field_name("sollkonto_{$index}");
        $sollbetragname = $qa->get_qt_field_name("sollbetrag_{$index}");
        $habenkontoname = $qa->get_qt_field_name("habenkonto_{$index}");
        $habenbetragname = $qa->get_qt_field_name("habenbetrag_{$index}");

        $sollkontoval = $response["sollkonto_{$index}"] ?? '';
        $sollbetragval = $response["sollbetrag_{$index}"] ?? '';
        $habenkontoval = $response["habenkonto_{$index}"] ?? '';
        $habenbetragval = $response["habenbetrag_{$index}"] ?? '';

        $readonly = $options->readonly;

        $html = html_writer::start_div('buchungssatz-entry row', ['data-entry' => $index]);

        // Soll (Debit) account dropdown.
        $html .= html_writer::start_div('col-md-3');
        if ($readonly) {
            $html .= html_writer::tag('span', s($sollkontoval), ['class' => 'buchungssatz-readonly']);
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $sollkontoname,
                'value' => $sollkontoval,
            ]);
        } else {
            $html .= $this->render_account_select($sollkontoname, $sollkontoval, $accounts,
                get_string('selectaccount', 'qtype_buchungssatz'));
        }
        $html .= html_writer::end_div();

        // Soll (Debit) amount.
        $html .= html_writer::start_div('col-md-3');
        if ($readonly) {
            $html .= html_writer::tag('span', s($sollbetragval), ['class' => 'buchungssatz-readonly']);
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $sollbetragname,
                'value' => $sollbetragval,
            ]);
        } else {
            $html .= html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => $sollbetragname,
                'id' => $sollbetragname,
                'value' => $sollbetragval,
                'class' => 'form-control buchungssatz-amount',
                'step' => '0.01',
                'min' => '0',
                'placeholder' => '0.00',
                'aria-label' => get_string('sollamount', 'qtype_buchungssatz'),
            ]);
        }
        $html .= html_writer::end_div();

        // Haben (Credit) account dropdown.
        $html .= html_writer::start_div('col-md-3');
        if ($readonly) {
            $html .= html_writer::tag('span', s($habenkontoval), ['class' => 'buchungssatz-readonly']);
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $habenkontoname,
                'value' => $habenkontoval,
            ]);
        } else {
            $html .= $this->render_account_select($habenkontoname, $habenkontoval, $accounts,
                get_string('selectaccount', 'qtype_buchungssatz'));
        }
        $html .= html_writer::end_div();

        // Haben (Credit) amount.
        $html .= html_writer::start_div('col-md-3');
        if ($readonly) {
            $html .= html_writer::tag('span', s($habenbetragval), ['class' => 'buchungssatz-readonly']);
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $habenbetragname,
                'value' => $habenbetragval,
            ]);
        } else {
            $html .= html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => $habenbetragname,
                'id' => $habenbetragname,
                'value' => $habenbetragval,
                'class' => 'form-control buchungssatz-amount',
                'step' => '0.01',
                'min' => '0',
                'placeholder' => '0.00',
                'aria-label' => get_string('habenamount', 'qtype_buchungssatz'),
            ]);
        }
        $html .= html_writer::end_div();

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render an account selection dropdown.
     *
     * @param string $name
     * @param string $selected
     * @param array $accounts
     * @param string $placeholder
     * @return string
     */
    protected function render_account_select(string $name, string $selected, array $accounts, string $placeholder): string {
        $options = ['' => $placeholder];

        foreach ($accounts as $account) {
            $label = $account->accountnumber . ' - ' . $account->accountname;
            $options[$account->accountnumber] = $label;
        }

        return html_writer::select($options, $name, $selected, null, [
            'class' => 'form-control buchungssatz-account-select',
            'aria-label' => get_string('account', 'qtype_buchungssatz'),
        ]);
    }

    /**
     * Get available accounts from the chart.
     *
     * @param int $chartid
     * @return array
     */
    protected function get_accounts(int $chartid): array {
        global $DB;

        if ($chartid <= 0) {
            return [];
        }

        return $DB->get_records('qtype_buchungssatz_accounts',
            ['chartid' => $chartid], 'sortorder, accountnumber');
    }

    /**
     * Generate the specific feedback for the question.
     *
     * @param question_attempt $qa
     * @return string
     */
    public function specific_feedback(question_attempt $qa): string {
        return '';
    }

    /**
     * Generate the correct response for display.
     *
     * @param question_attempt $qa
     * @return string
     */
    public function correct_response(question_attempt $qa): string {
        $question = $qa->get_question();

        if (empty($question->entries)) {
            return '';
        }

        $html = html_writer::start_div('buchungssatz-correct-response');
        $html .= html_writer::tag('p', get_string('correctansweris', 'qtype_buchungssatz'));

        $html .= html_writer::start_tag('table', ['class' => 'table table-bordered buchungssatz-solution']);
        $html .= html_writer::start_tag('thead');
        $html .= html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('soll', 'qtype_buchungssatz'), ['colspan' => 2]);
        $html .= html_writer::tag('th', get_string('haben', 'qtype_buchungssatz'), ['colspan' => 2]);
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::start_tag('tr');
        $html .= html_writer::tag('th', get_string('account', 'qtype_buchungssatz'));
        $html .= html_writer::tag('th', get_string('amount', 'qtype_buchungssatz'));
        $html .= html_writer::tag('th', get_string('account', 'qtype_buchungssatz'));
        $html .= html_writer::tag('th', get_string('amount', 'qtype_buchungssatz'));
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('thead');

        $html .= html_writer::start_tag('tbody');
        foreach ($question->entries as $entry) {
            $html .= html_writer::start_tag('tr');
            $html .= html_writer::tag('td', s($entry['sollkonto']));
            $html .= html_writer::tag('td', number_format($entry['sollbetrag'], 2, ',', '.'));
            $html .= html_writer::tag('td', s($entry['habenkonto']));
            $html .= html_writer::tag('td', number_format($entry['habenbetrag'], 2, ',', '.'));
            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');

        $html .= html_writer::end_div();

        return $html;
    }
}
