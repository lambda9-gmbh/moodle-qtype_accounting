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
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_renderer extends qtype_renderer {

    /**
     * Generate the display of the formulation part of the question.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options The display options.
     * @return string The HTML output.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        global $PAGE;

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $questiontext = $question->format_questiontext($qa);
        $result = html_writer::tag('div', $questiontext, ['class' => 'qtext']);

        // Load available accounts for dropdowns.
        $accounts = $this->get_accounts($question->chartofaccountsid);

        // Render entry fields - always allow up to 20 entries for students.
        $maxentries = 20;

        // Create a unique container ID for this question.
        $containerid = 'buchungssatz-container-' . $qa->get_slot();

        // Wrap everything in our own container.
        $result .= html_writer::start_div('buchungssatz-question-container', ['id' => $containerid]);

        $result .= html_writer::start_div('buchungssatz-entries');

        // Header row - always show actions column if not readonly.
        $result .= $this->render_header_row(!$options->readonly);

        // Determine which rows should be visible initially.
        $visiblerows = 1; // At least one row visible.
        for ($i = 0; $i < $maxentries; $i++) {
            $hasdata = !empty($response["sollkonto_{$i}"]) || !empty($response["habenkonto_{$i}"]);
            if ($hasdata && $i >= $visiblerows) {
                $visiblerows = $i + 1;
            }
        }

        for ($i = 0; $i < $maxentries; $i++) {
            $hidden = ($i >= $visiblerows);
            $showdelete = !$options->readonly;
            $result .= $this->render_entry_row($qa, $options, $i, $response, $accounts, $question, $hidden, $showdelete);
        }

        $result .= html_writer::end_div();

        // Add "Add Entry" button if not readonly.
        if (!$options->readonly) {
            $result .= html_writer::start_div('buchungssatz-controls mt-2');
            $result .= html_writer::tag('button', get_string('addentry', 'qtype_buchungssatz'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm buchungssatz-add-entry',
            ]);
            $result .= html_writer::end_div();
        }

        $result .= html_writer::end_div(); // End container.

        // Load language strings for JavaScript.
        $PAGE->requires->string_for_js('selectaccount', 'qtype_buchungssatz');

        // Include JavaScript for interactive features.
        $PAGE->requires->js_call_amd('qtype_buchungssatz/question', 'init', [
            $containerid,
            $accounts,
            $maxentries,
            !$options->readonly,
        ]);

        return $result;
    }

    /**
     * Render the header row for the booking entries.
     *
     * @param bool $showactions Whether to show an actions column.
     * @return string
     */
    protected function render_header_row(bool $showactions = false): string {
        $html = '';

        // First row - main headers (Soll / Haben).
        $html .= html_writer::start_div('buchungssatz-header-main',
            ['style' => 'display: flex; border-bottom: 1px solid #dee2e6;']);

        $html .= html_writer::div(get_string('soll', 'qtype_buchungssatz'), '',
            ['style' => 'flex: 1; text-align: center; font-weight: bold; padding: 0.5rem;']);
        $html .= html_writer::div(get_string('haben', 'qtype_buchungssatz'), '',
            ['style' => 'flex: 1; text-align: center; font-weight: bold; padding: 0.5rem;']);

        if ($showactions) {
            $html .= html_writer::div('', '', ['style' => 'flex: 0 0 28px;']);
        }

        $html .= html_writer::end_div();

        // Second row - sub headers (Account / Amount / Account / Amount).
        $html .= html_writer::start_div('buchungssatz-header-sub',
            ['style' => 'display: flex; margin-bottom: 0.5rem; padding: 0.25rem 0;']);

        $html .= html_writer::div(get_string('account', 'qtype_buchungssatz'), 'buchungssatz-header-account pr-2',
            ['style' => 'text-align: center; font-weight: bold;']);
        $html .= html_writer::div(get_string('amount', 'qtype_buchungssatz'), 'buchungssatz-header-amount pr-2',
            ['style' => 'font-weight: bold;']);
        $html .= html_writer::div(get_string('account', 'qtype_buchungssatz'), 'buchungssatz-header-account pr-2',
            ['style' => 'text-align: center; font-weight: bold;']);
        $html .= html_writer::div(get_string('amount', 'qtype_buchungssatz'), 'buchungssatz-header-amount pr-2',
            ['style' => 'font-weight: bold;']);

        if ($showactions) {
            $html .= html_writer::div('', '', ['style' => 'flex: 0 0 28px;']);
        }

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render a single entry row.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options The display options.
     * @param int $index The entry index.
     * @param array $response The current response data.
     * @param array $accounts The available accounts.
     * @param object $question The question object.
     * @param bool $hidden Whether the row should be hidden initially.
     * @param bool $showdelete Whether to show the delete button.
     * @return string The HTML output.
     */
    protected function render_entry_row(
        question_attempt $qa,
        question_display_options $options,
        int $index,
        array $response,
        array $accounts,
        object $question,
        bool $hidden = false,
        bool $showdelete = false
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

        $rowattrs = ['data-entry' => $index];
        if ($hidden) {
            $rowattrs['style'] = 'display: none;';
        } else {
            $rowattrs['style'] = 'display: flex;';
        }
        $html = html_writer::start_div('buchungssatz-entry mb-2 align-items-center', $rowattrs);

        // Soll (Debit) side.
        $html .= $this->render_account_amount($readonly, $sollkontoval, $sollkontoname, $accounts, $sollbetragval, $sollbetragname);
        // Haben (Credit) side.
        $html .= $this->render_account_amount($readonly, $habenkontoval, $habenkontoname, $accounts, $habenbetragval, $habenbetragname);

        // Delete button column.
        if ($showdelete) {
            $html .= html_writer::start_div('', ['style' => 'flex: 0 0 28px;']);
            $html .= html_writer::tag('button', '×', [
                'type' => 'button',
                'class' => 'btn btn-outline-danger btn-sm buchungssatz-delete-entry',
                'data-entry' => $index,
                'title' => get_string('delete', 'moodle'),
                'style' => 'line-height: 1; padding: 0.2rem 0.4rem; font-size: 1rem;',
            ]);
            $html .= html_writer::end_div();
        }

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render an account selection dropdown.
     *
     * @param string $name The field name.
     * @param string $selected The selected account number.
     * @param array $accounts The available accounts.
     * @param string $placeholder The placeholder text.
     * @return string The HTML output.
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
     * Render an account and amount field pair.
     *
     * @param bool $readonly Whether the fields are readonly.
     * @param string $kontoval The account value.
     * @param string $kontoname The account field name.
     * @param array $accounts The available accounts.
     * @param string $betragval The amount value.
     * @param string $betragname The amount field name.
     * @param bool $addborder Whether to add a right border.
     * @return string The HTML output.
     */
    protected function render_account_amount(bool $readonly, string $kontoval, string $kontoname, array $accounts, string $betragval, string $betragname, bool $addborder = false): string {
        $html = html_writer::start_div('', ['class' => 'buchungssatz-account pr-2']);
        if ($readonly) {
            // Look up account name for display.
            $displayval = $kontoval;
            if (!empty($kontoval)) {
                foreach ($accounts as $account) {
                    if ($account->accountnumber === $kontoval) {
                        $displayval = $kontoval . ' - ' . $account->accountname;
                        break;
                    }
                }
            }
            $html .= html_writer::tag('span', s($displayval), ['class' => 'buchungssatz-readonly']);
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $kontoname,
                'value' => $kontoval,
            ]);
        } else {
            $html .= $this->render_account_select($kontoname, $kontoval, $accounts,
                get_string('selectaccount', 'qtype_buchungssatz'));
        }
        $html .= html_writer::end_div();

        // Amount field.
        $amountstyle = $addborder ? 'border-right: 1px solid #dee2e6; padding-right: 0.5rem; margin-right: 0.5rem;' : '';
        $html .= html_writer::start_div('', ['class' => 'buchungssatz-amount pr-2', 'style' => $amountstyle]);
        if ($readonly) {
            $html .= html_writer::tag('span', s($betragval), ['class' => 'buchungssatz-readonly']);
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => $betragname,
                'value' => $betragval,
            ]);
        } else {
            $html .= html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => $betragname,
                'id' => $betragname,
                'value' => $betragval,
                'class' => 'form-control buchungssatz-amount-input',
                'step' => '0.01',
                'min' => '0',
                'placeholder' => '0.00',
                'aria-label' => get_string('sollamount', 'qtype_buchungssatz'),
            ]);
        }
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Get available accounts from the chart.
     *
     * @param int $chartid The chart of accounts ID.
     * @return array The list of account records.
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
     * @param question_attempt $qa The question attempt object.
     * @return string The HTML output.
     */
    public function specific_feedback(question_attempt $qa): string {
        return '';
    }

    /**
     * Generate the correct response for display.
     *
     * @param question_attempt $qa The question attempt object.
     * @return string The HTML output.
     */
    public function correct_response(question_attempt $qa): string {
        $question = $qa->get_question();

        if (empty($question->entries)) {
            return '';
        }

        // Load accounts for name lookup.
        $accounts = $this->get_accounts($question->chartofaccountsid);

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
            $html .= html_writer::tag('td', s($this->get_account_display($entry['sollkonto'], $accounts)));
            $html .= html_writer::tag('td', number_format($entry['sollbetrag'], 2, ',', '.'));
            $html .= html_writer::tag('td', s($this->get_account_display($entry['habenkonto'], $accounts)));
            $html .= html_writer::tag('td', number_format($entry['habenbetrag'], 2, ',', '.'));
            $html .= html_writer::end_tag('tr');
        }
        $html .= html_writer::end_tag('tbody');
        $html .= html_writer::end_tag('table');

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Get display string for an account (number + name).
     *
     * @param string $accountnumber The account number to look up.
     * @param array $accounts The available accounts.
     * @return string The formatted account display string.
     */
    protected function get_account_display(string $accountnumber, array $accounts): string {
        if (empty($accountnumber)) {
            return '';
        }
        foreach ($accounts as $account) {
            if ($account->accountnumber === $accountnumber) {
                return $accountnumber . ' - ' . $account->accountname;
            }
        }
        return $accountnumber;
    }
}
