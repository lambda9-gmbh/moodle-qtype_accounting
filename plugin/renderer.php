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

        // Get number format settings for data attributes.
        $numberformat = $question->numberformat ?? 'de';
        $decimalplaces = $question->decimalplaces ?? 2;

        // Wrap everything in our own container with data attributes for JS.
        $containerattrs = [
            'id' => $containerid,
            'data-accounts' => json_encode(array_values($accounts)),
            'data-maxentries' => $maxentries,
            'data-allowedit' => !$options->readonly ? '1' : '0',
            'data-numberformat' => $numberformat,
            'data-decimalplaces' => $decimalplaces,
        ];
        $result .= html_writer::start_div('buchungssatz-question-container', $containerattrs);

        // Calculate overall feedback using aggregation (only in readonly/review mode).
        $overallfeedback = null;
        if ($options->readonly) {
            $overallfeedback = $this->calculate_aggregated_feedback($question, $response);
        }

        // Start the table.
        $result .= '<table class="table table-bordered buchungssatz-student-table">';

        // Define column widths with colgroup for consistent rendering across Bootstrap versions.
        $result .= $this->render_colgroup(!$options->readonly);

        // Header row - always show actions column if not readonly.
        $result .= $this->render_header_row(!$options->readonly);

        $result .= '<tbody class="buchungssatz-entries">';

        // Determine which rows should be visible initially.
        $visiblerows = 1; // At least one row visible.
        for ($i = 0; $i < $maxentries; $i++) {
            $hasdata = !empty($response["sollkonto_{$i}"]) || !empty($response["habenkonto_{$i}"]);
            if ($hasdata && $i >= $visiblerows) {
                $visiblerows = $i + 1;
            }
        }

        // In readonly mode (reviewing), ensure we show enough rows for all student entries.
        // We no longer need to show correct entry count since order doesn't matter.
        if ($options->readonly) {
            // Just ensure we show all rows that have data.
            $visiblerows = max($visiblerows, 1);
        }

        // Get the accountsindropdown limit.
        $accountslimit = $question->accountsindropdown ?? 0;

        for ($i = 0; $i < $maxentries; $i++) {
            $hidden = ($i >= $visiblerows);
            $showdelete = !$options->readonly;

            // Filter accounts for this entry based on accountsindropdown setting.
            $correctentry = $question->entries[$i] ?? null;
            $correctsollkonto = $correctentry['sollkonto'] ?? '';
            $correcthabenkonto = $correctentry['habenkonto'] ?? '';

            // If we have a limit and not in readonly mode, filter the accounts.
            if ($accountslimit > 0 && !$options->readonly) {
                $sollaccounts = $this->filter_accounts_for_dropdown($accounts, $correctsollkonto, $accountslimit);
                $habenaccounts = $this->filter_accounts_for_dropdown($accounts, $correcthabenkonto, $accountslimit);
            } else {
                // No limit or readonly mode - show all accounts.
                $sollaccounts = $accounts;
                $habenaccounts = $accounts;
            }

            $result .= $this->render_entry_row($qa, $options, $i, $response, $sollaccounts, $habenaccounts, $question, $hidden, $showdelete, $i === 0, $overallfeedback);
        }

        $result .= '</tbody>';
        $result .= '</table>';

        // Show overall feedback summary after the table in review mode.
        if ($options->readonly && $overallfeedback !== null) {
            $result .= $this->render_feedback_summary($overallfeedback);
        }

        // Add "Add Debit Entry" and "Add Credit Entry" buttons if not readonly.
        if (!$options->readonly) {
            $result .= html_writer::start_div('buchungssatz-controls mt-2');
            $result .= html_writer::tag('button', get_string('adddebitentry', 'qtype_buchungssatz'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm buchungssatz-add-debit-entry mr-2',
            ]);
            $result .= html_writer::tag('button', get_string('addcreditentry', 'qtype_buchungssatz'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm buchungssatz-add-credit-entry',
            ]);
            $result .= html_writer::end_div();
        }

        $result .= html_writer::end_div(); // End container.

        // Load language strings for JavaScript.
        $PAGE->requires->string_for_js('selectaccount', 'qtype_buchungssatz');

        // Include JavaScript for interactive features.
        // Only pass the container ID - other data is in data attributes to avoid Moodle 3.10 size limits.
        $PAGE->requires->js_call_amd('qtype_buchungssatz/question', 'init', [$containerid]);

        return $result;
    }

    /**
     * Render the table header for the booking entries.
     *
     * @param bool $showactions Whether to show an actions column.
     * @return string
     */
    protected function render_header_row(bool $showactions = false): string {
        $perstr = get_string('per', 'qtype_buchungssatz');
        $anstr = get_string('an', 'qtype_buchungssatz');
        $sollstr = get_string('soll', 'qtype_buchungssatz');
        $habenstr = get_string('haben', 'qtype_buchungssatz');
        $kontostr = get_string('account', 'qtype_buchungssatz');
        $betragstr = get_string('amount', 'qtype_buchungssatz');

        $html = '<thead>';

        // First header row - Soll / Haben.
        $html .= '<tr>';
        $html .= '<th class="buchungssatz-label-cell"></th>';
        $html .= '<th colspan="2" class="text-center">' . $sollstr . '</th>';
        $html .= '<th class="buchungssatz-label-cell"></th>';
        $html .= '<th colspan="2" class="text-center">' . $habenstr . '</th>';
        if ($showactions) {
            $html .= '<th class="buchungssatz-actions-cell"></th>';
        }
        $html .= '</tr>';

        // Second header row - Per / Account / Amount / an / Account / Amount.
        $html .= '<tr>';
        $html .= '<th class="buchungssatz-label-cell">' . $perstr . '</th>';
        $html .= '<th>' . $kontostr . '</th>';
        $html .= '<th>' . $betragstr . '</th>';
        $html .= '<th class="buchungssatz-label-cell">' . $anstr . '</th>';
        $html .= '<th>' . $kontostr . '</th>';
        $html .= '<th>' . $betragstr . '</th>';
        if ($showactions) {
            $html .= '<th></th>';
        }
        $html .= '</tr>';

        $html .= '</thead>';

        return $html;
    }

    /**
     * Render colgroup to define explicit column widths.
     *
     * This ensures consistent column widths across Bootstrap 4 (Moodle 3.x) and Bootstrap 5 (Moodle 4.x).
     *
     * @param bool $showactions Whether to include an actions column.
     * @return string The HTML colgroup element.
     */
    protected function render_colgroup(bool $showactions = false): string {
        $html = '<colgroup>';
        // Column 1: Per label (narrow).
        $html .= '<col style="width: 40px;">';
        // Column 2: Soll Account (flexible).
        $html .= '<col style="width: auto;">';
        // Column 3: Soll Amount (wider for numbers like 12.000,55).
        $html .= '<col style="width: 120px;">';
        // Column 4: an label (narrow).
        $html .= '<col style="width: 40px;">';
        // Column 5: Haben Account (flexible).
        $html .= '<col style="width: auto;">';
        // Column 6: Haben Amount (wider for numbers like 12.000,55).
        $html .= '<col style="width: 120px;">';
        if ($showactions) {
            // Column 7: Actions/Delete button (narrow).
            $html .= '<col style="width: 50px;">';
        }
        $html .= '</colgroup>';

        return $html;
    }

    /**
     * Render a single entry row as a table row.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options The display options.
     * @param int $index The entry index.
     * @param array $response The current response data.
     * @param array $sollaccounts The available accounts for Soll (debit) dropdown.
     * @param array $habenaccounts The available accounts for Haben (credit) dropdown.
     * @param object $question The question object.
     * @param bool $hidden Whether the row should be hidden initially.
     * @param bool $showdelete Whether to show the delete button.
     * @param bool $isfirst Whether this is the first entry row.
     * @param array|null $overallfeedback The overall aggregated feedback (for review mode).
     * @return string The HTML output.
     */
    protected function render_entry_row(
        question_attempt $qa,
        question_display_options $options,
        int $index,
        array $response,
        array $sollaccounts,
        array $habenaccounts,
        object $question,
        bool $hidden = false,
        bool $showdelete = false,
        bool $isfirst = false,
        ?array $overallfeedback = null
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

        // Get Per/an labels.
        $perstr = get_string('per', 'qtype_buchungssatz');
        $anstr = get_string('an', 'qtype_buchungssatz');

        // No per-field highlighting - we show overall feedback summary instead.
        $feedbackclass = '';

        // Build table row.
        $rowstyle = $hidden ? 'display: none;' : '';
        $html = '<tr class="buchungssatz-entry-row" data-entry="' . $index . '" data-entry-type="both" style="' . $rowstyle . '">';

        // Per label cell (only show text on first row) - add data-section for mobile header.
        $html .= '<td class="buchungssatz-label-cell" data-section="soll">' . ($isfirst ? $perstr : '') . '</td>';

        // Soll (Debit) account cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';
        $html .= $this->render_account_field($readonly, $sollkontoval, $sollkontoname, $sollaccounts, $feedbackclass);
        $html .= '</td>';

        // Get number format settings from question.
        $numberformat = $question->numberformat ?? 'de';
        $decimalplaces = $question->decimalplaces ?? 2;

        // Soll (Debit) amount cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
        $html .= $this->render_amount_field($readonly, $sollbetragval, $sollbetragname, $feedbackclass, $numberformat, $decimalplaces);
        $html .= '</td>';

        // "an" label cell - contains debit delete button if editable.
        $html .= '<td class="buchungssatz-label-cell" data-section="haben">';
        if ($showdelete) {
            $html .= '<button type="button" class="btn btn-outline-danger btn-sm buchungssatz-delete-debit" ';
            $html .= 'data-entry="' . $index . '" title="' . get_string('soll', 'qtype_buchungssatz') . '">';
            $html .= '<i class="fa fa-trash"></i>';
            $html .= '</button>';
        } else {
            $html .= ($isfirst ? $anstr : '');
        }
        $html .= '</td>';

        // Haben (Credit) account cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';
        $html .= $this->render_account_field($readonly, $habenkontoval, $habenkontoname, $habenaccounts, $feedbackclass);
        $html .= '</td>';

        // Haben (Credit) amount cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
        $html .= $this->render_amount_field($readonly, $habenbetragval, $habenbetragname, $feedbackclass, $numberformat, $decimalplaces);
        $html .= '</td>';

        // Delete button cell (credit delete only - debit delete is in the "an" cell).
        if ($showdelete) {
            $html .= '<td class="buchungssatz-actions-cell">';
            $html .= '<button type="button" class="btn btn-outline-danger btn-sm buchungssatz-delete-credit" ';
            $html .= 'data-entry="' . $index . '" title="' . get_string('haben', 'qtype_buchungssatz') . '">';
            $html .= '<i class="fa fa-trash"></i>';
            $html .= '</button>';
            $html .= '</td>';
        }

        $html .= '</tr>';

        return $html;
    }

    /**
     * Render an account field (select or readonly display).
     *
     * @param bool $readonly Whether the field is readonly.
     * @param string $value The current value.
     * @param string $name The field name.
     * @param array $accounts The available accounts.
     * @param string $feedbackclass CSS class for feedback styling.
     * @return string The HTML output.
     */
    protected function render_account_field(bool $readonly, string $value, string $name, array $accounts, string $feedbackclass = ''): string {
        if ($readonly) {
            // Look up account name for display.
            $displayval = $value;
            if (!empty($value)) {
                foreach ($accounts as $account) {
                    if ($account->accountnumber === $value) {
                        $displayval = $value . ' - ' . $account->accountname;
                        break;
                    }
                }
            }
            $spanclass = 'buchungssatz-readonly';
            if (!empty($feedbackclass)) {
                $spanclass .= ' ' . $feedbackclass;
            }
            $html = '<span class="' . $spanclass . '">' . s($displayval) . '</span>';
            $html .= '<input type="hidden" name="' . $name . '" value="' . s($value) . '">';
            return $html;
        }

        // If no accounts are available, render a text input instead of a dropdown.
        if (empty($accounts)) {
            return '<input type="text" name="' . $name . '" id="' . $name . '" value="' . s($value) . '" ' .
                   'class="form-control buchungssatz-account-input" ' .
                   'placeholder="' . get_string('enteraccount', 'qtype_buchungssatz') . '" ' .
                   'aria-label="' . get_string('account', 'qtype_buchungssatz') . '">';
        }

        return $this->render_account_select($name, $value, $accounts, get_string('selectaccount', 'qtype_buchungssatz'));
    }

    /**
     * Render an amount field (input or readonly display).
     *
     * @param bool $readonly Whether the field is readonly.
     * @param string $value The current value.
     * @param string $name The field name.
     * @param string $feedbackclass CSS class for feedback styling.
     * @param string $numberformat The number format ('de' or 'us').
     * @param int $decimalplaces The number of decimal places.
     * @return string The HTML output.
     */
    protected function render_amount_field(
        bool $readonly,
        string $value,
        string $name,
        string $feedbackclass = '',
        string $numberformat = 'de',
        int $decimalplaces = 2
    ): string {
        if ($readonly) {
            $spanclass = 'buchungssatz-readonly';
            if (!empty($feedbackclass)) {
                $spanclass .= ' ' . $feedbackclass;
            }
            // Parse the amount (handles both German and US formats) then format for display.
            $parsedvalue = $this->parse_amount_input($value);
            $displayval = $this->format_amount_display($parsedvalue, $numberformat, $decimalplaces);
            $html = '<span class="' . $spanclass . '" style="text-align: end;">' . s($displayval) . '</span>';
            $html .= '<input type="hidden" name="' . $name . '" value="' . s($value) . '">';
            return $html;
        }

        // Use type="text" to allow formatted numbers with thousand separators.
        $placeholder = ($numberformat === 'us') ? '0.00' : '0,00';
        return '<input type="text" name="' . $name . '" id="' . $name . '" value="' . s($value) . '" ' .
               'class="form-control buchungssatz-amount-input" placeholder="' . $placeholder . '" ' .
               'inputmode="decimal" ' .
               'aria-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
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
            'id' => $name,
            'class' => 'form-control buchungssatz-account-select',
            'aria-label' => get_string('account', 'qtype_buchungssatz'),
        ]);
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
     * Filter accounts for a dropdown based on the accountsindropdown setting.
     *
     * If limit is 0, returns all accounts.
     * Otherwise, returns the correct account plus the specified number of random other accounts.
     * E.g., if limit = 3, shows 1 correct + 3 random = 4 accounts total.
     *
     * @param array $allaccounts All available accounts from the chart.
     * @param string $correctaccountnumber The correct account number for this field.
     * @param int $limit The number of random accounts to add (0 = show all).
     * @return array The filtered list of account records, sorted by account number.
     */
    protected function filter_accounts_for_dropdown(array $allaccounts, string $correctaccountnumber, int $limit): array {
        // If limit is 0 or no accounts, return all.
        if ($limit <= 0 || empty($allaccounts)) {
            return $allaccounts;
        }

        $result = [];
        $otheraccounts = [];

        // Separate the correct account from others.
        foreach ($allaccounts as $account) {
            if ($account->accountnumber === $correctaccountnumber) {
                $result[$account->id] = $account;
            } else {
                $otheraccounts[$account->id] = $account;
            }
        }

        // If limit is greater than or equal to other accounts available, return all.
        if ($limit >= count($otheraccounts)) {
            return $allaccounts;
        }

        // Select 'limit' random accounts from the other accounts.
        if (!empty($otheraccounts)) {
            $otherkeys = array_keys($otheraccounts);
            shuffle($otherkeys);
            $selectedkeys = array_slice($otherkeys, 0, $limit);

            foreach ($selectedkeys as $key) {
                $result[$key] = $otheraccounts[$key];
            }
        }

        // Sort by account number for consistent display.
        uasort($result, function($a, $b) {
            return strcmp($a->accountnumber, $b->accountnumber);
        });

        return $result;
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

        $numberformat = $question->numberformat ?? 'de';
        $decimalplaces = $question->decimalplaces ?? 2;

        $sollkontostr = get_string('sollkonto', 'qtype_buchungssatz');
        $sollbetragstr = get_string('sollbetrag', 'qtype_buchungssatz');
        $habenkontostr = get_string('habenkonto', 'qtype_buchungssatz');
        $habenbetragstr = get_string('habenbetrag', 'qtype_buchungssatz');

        $html .= html_writer::start_tag('tbody');
        foreach ($question->entries as $entry) {
            $html .= html_writer::start_tag('tr');
            $html .= html_writer::tag('td', s($this->get_account_display($entry['sollkonto'], $accounts)),
                ['data-label' => $sollkontostr, 'style' => 'text-align: start;' ]);
            $html .= html_writer::tag('td', $this->format_amount_display($entry['sollbetrag'], $numberformat, $decimalplaces),
                ['data-label' => $sollbetragstr, 'style' => 'text-align: end;' ]);
            $html .= html_writer::tag('td', s($this->get_account_display($entry['habenkonto'], $accounts)),
                ['data-label' => $habenkontostr, 'style' => 'text-align: start;' ]);
            $html .= html_writer::tag('td', $this->format_amount_display($entry['habenbetrag'], $numberformat, $decimalplaces),
                ['data-label' => $habenbetragstr, 'style' => 'text-align: end;' ]);
            $html .= html_writer::end_tag('tr');
            // Show explanation as separate row if present.
            if (!empty($entry['explanation'])) {
                $html .= html_writer::start_tag('tr', ['class' => 'buchungssatz-explanation-row']);
                $html .= html_writer::tag('td',
                    html_writer::tag('strong', get_string('explanation', 'qtype_buchungssatz') . ': ') . s($entry['explanation']),
                    ['colspan' => 4, 'style' => 'background-color: #fff3cd; color: #856404;']
                );
                $html .= html_writer::end_tag('tr');
            }
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

    /**
     * Parse an amount input string that may be in German or US format.
     *
     * Handles formats like:
     * - "12000" (plain number)
     * - "12.000,00" (German format)
     * - "12,000.00" (US format)
     *
     * @param string $value The input value to parse.
     * @return float The parsed numeric value.
     */
    protected function parse_amount_input(string $value): float {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        // Find positions of last comma and last dot.
        $lastcomma = strrpos($value, ',');
        $lastdot = strrpos($value, '.');

        if ($lastcomma !== false && $lastdot !== false) {
            // Both separators present - the last one is the decimal separator.
            if ($lastcomma > $lastdot) {
                // German format: 12.000,00.
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // US format: 12,000.00.
                $value = str_replace(',', '', $value);
            }
        } else if ($lastcomma !== false) {
            // Only comma present - could be German decimal separator.
            // Check if there are exactly 2-3 digits after comma (likely decimal).
            $aftercomma = substr($value, $lastcomma + 1);
            if (strlen($aftercomma) <= 3 && ctype_digit($aftercomma)) {
                // Treat comma as decimal separator.
                $value = str_replace(',', '.', $value);
            }
            // Otherwise it might be a thousand separator with no decimals.
            // e.g., "12,000" - remove the comma.
            else {
                $value = str_replace(',', '', $value);
            }
        }
        // If only dot present, PHP's floatval handles it correctly.

        return (float)$value;
    }

    /**
     * Format an amount for display, showing empty for zero/non-existent amounts.
     *
     * @param float $amount The amount value.
     * @param string $format The number format: 'de' for German (1.234,56) or 'us' for US (1,234.56).
     * @param int $decimalplaces The number of decimal places to display.
     * @return string The formatted amount or empty string if zero.
     */
    protected function format_amount_display(float $amount, string $format = 'de', int $decimalplaces = 2): string {
        if (abs($amount) < 0.01) {
            return '';
        }
        if ($format === 'us') {
            return number_format($amount, $decimalplaces, '.', ',');
        }
        // Default: German/EU format.
        return number_format($amount, $decimalplaces, ',', '.');
    }

    /**
     * Calculate aggregated feedback by comparing student response to correct answer.
     *
     * Uses the same aggregation logic as grading: sums amounts per account on each side,
     * then compares the aggregated totals.
     *
     * @param object $question The question object with correct entries.
     * @param array $response The student's response data.
     * @return array Feedback with status per side ('correct', 'partial', 'incorrect') and details.
     */
    protected function calculate_aggregated_feedback(object $question, array $response): array {
        $feedback = [
            'debit_status' => 'correct',   // 'correct', 'partial', or 'incorrect'.
            'credit_status' => 'correct',  // 'correct', 'partial', or 'incorrect'.
            'all_correct' => true,
            'debit_details' => [],
            'credit_details' => [],
        ];

        if (empty($question->entries)) {
            return $feedback;
        }

        // Aggregate correct entries.
        $correctaggregated = $this->aggregate_entries_for_feedback($question->entries);

        // Parse and aggregate student entries.
        $studententries = $this->parse_student_response($response);
        $studentaggregated = $this->aggregate_entries_for_feedback($studententries);

        // Compare Debit (Soll) side.
        $debitcorrectcount = 0;
        $debittotalcount = 0;
        foreach ($correctaggregated['debit'] as $account => $correctamount) {
            $debittotalcount++;
            $studentamount = $studentaggregated['debit'][$account] ?? null;
            if ($studentamount === null) {
                // Student missing this account.
                $feedback['debit_details'][] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => null,
                    'status' => 'missing',
                ];
            } else if ($studentamount != $correctamount) {
                // Amount doesn't match.
                $feedback['debit_details'][] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => $studentamount,
                    'status' => 'wrong_amount',
                ];
            } else {
                // Correct.
                $debitcorrectcount++;
                $feedback['debit_details'][] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => $studentamount,
                    'status' => 'correct',
                ];
            }
        }

        // Determine debit status.
        if ($debittotalcount > 0) {
            if ($debitcorrectcount === $debittotalcount) {
                $feedback['debit_status'] = 'correct';
            } else if ($debitcorrectcount > 0) {
                $feedback['debit_status'] = 'partial';
            } else {
                $feedback['debit_status'] = 'incorrect';
            }
        }

        // Compare Credit (Haben) side.
        $creditcorrectcount = 0;
        $credittotalcount = 0;
        foreach ($correctaggregated['credit'] as $account => $correctamount) {
            $credittotalcount++;
            $studentamount = $studentaggregated['credit'][$account] ?? null;
            if ($studentamount === null) {
                // Student missing this account.
                $feedback['credit_details'][] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => null,
                    'status' => 'missing',
                ];
            } else if ($studentamount != $correctamount) {
                // Amount doesn't match.
                $feedback['credit_details'][] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => $studentamount,
                    'status' => 'wrong_amount',
                ];
            } else {
                // Correct.
                $creditcorrectcount++;
                $feedback['credit_details'][] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => $studentamount,
                    'status' => 'correct',
                ];
            }
        }

        // Determine credit status.
        if ($credittotalcount > 0) {
            if ($creditcorrectcount === $credittotalcount) {
                $feedback['credit_status'] = 'correct';
            } else if ($creditcorrectcount > 0) {
                $feedback['credit_status'] = 'partial';
            } else {
                $feedback['credit_status'] = 'incorrect';
            }
        }

        // Check for extra accounts that student provided but aren't in the correct answer.
        $feedback['has_extra_debit'] = false;
        $feedback['has_extra_credit'] = false;

        foreach ($studentaggregated['debit'] as $account => $amount) {
            if (!isset($correctaggregated['debit'][$account])) {
                $feedback['has_extra_debit'] = true;
                // Only downgrade from 'correct' to 'partial' for extra accounts.
                // If already 'incorrect', keep it that way.
                if ($feedback['debit_status'] === 'correct') {
                    $feedback['debit_status'] = 'partial';
                }
                break;
            }
        }

        foreach ($studentaggregated['credit'] as $account => $amount) {
            if (!isset($correctaggregated['credit'][$account])) {
                $feedback['has_extra_credit'] = true;
                // Only downgrade from 'correct' to 'partial' for extra accounts.
                // If already 'incorrect', keep it that way.
                if ($feedback['credit_status'] === 'correct') {
                    $feedback['credit_status'] = 'partial';
                }
                break;
            }
        }

        $feedback['all_correct'] = ($feedback['debit_status'] === 'correct' && $feedback['credit_status'] === 'correct');

        return $feedback;
    }

    /**
     * Aggregate entries by account on each side for feedback display.
     *
     * @param array $entries The entries to aggregate.
     * @return array Aggregated data with 'debit' and 'credit' keys, each account => amount.
     */
    protected function aggregate_entries_for_feedback(array $entries): array {
        $aggregated = [
            'debit' => [],
            'credit' => [],
        ];

        foreach ($entries as $entry) {
            // Aggregate Debit (Soll) side.
            $sollkonto = $entry['sollkonto'] ?? '';
            if (!empty($sollkonto)) {
                if (!isset($aggregated['debit'][$sollkonto])) {
                    $aggregated['debit'][$sollkonto] = 0;
                }
                $aggregated['debit'][$sollkonto] += (float)($entry['sollbetrag'] ?? 0);
            }

            // Aggregate Credit (Haben) side.
            $habenkonto = $entry['habenkonto'] ?? '';
            if (!empty($habenkonto)) {
                if (!isset($aggregated['credit'][$habenkonto])) {
                    $aggregated['credit'][$habenkonto] = 0;
                }
                $aggregated['credit'][$habenkonto] += (float)($entry['habenbetrag'] ?? 0);
            }
        }

        return $aggregated;
    }

    /**
     * Parse student response into entries array.
     *
     * @param array $response The response data.
     * @return array The parsed entries.
     */
    protected function parse_student_response(array $response): array {
        $entries = [];
        $maxentries = 20;

        for ($i = 0; $i < $maxentries; $i++) {
            $sollkonto = trim($response["sollkonto_{$i}"] ?? '');
            $habenkonto = trim($response["habenkonto_{$i}"] ?? '');

            if (!empty($sollkonto) || !empty($habenkonto)) {
                $entries[] = [
                    'sollkonto' => $sollkonto,
                    'sollbetrag' => $this->parse_amount_input($response["sollbetrag_{$i}"] ?? ''),
                    'habenkonto' => $habenkonto,
                    'habenbetrag' => $this->parse_amount_input($response["habenbetrag_{$i}"] ?? ''),
                ];
            }
        }

        return $entries;
    }

    /**
     * Render the feedback summary showing overall correct/incorrect per side.
     *
     * @param array $feedback The aggregated feedback data.
     * @return string The HTML output.
     */
    protected function render_feedback_summary(array $feedback): string {
        $html = html_writer::start_div('buchungssatz-feedback-summary mt-3');

        if ($feedback['all_correct']) {
            $html .= html_writer::tag('div',
                '<i class="fa fa-check-circle"></i> ' . get_string('allcorrect', 'qtype_buchungssatz'),
                ['class' => 'alert alert-success']
            );
        } else {
            // Show which side(s) are incorrect or partially incorrect.
            $messages = [];

            if ($feedback['debit_status'] === 'incorrect') {
                $messages[] = get_string('debitincorrect', 'qtype_buchungssatz');
            } else if ($feedback['debit_status'] === 'partial') {
                if (!empty($feedback['has_extra_debit'])) {
                    $messages[] = get_string('debithasextraaccounts', 'qtype_buchungssatz');
                } else {
                    $messages[] = get_string('debitpartiallyincorrect', 'qtype_buchungssatz');
                }
            }

            if ($feedback['credit_status'] === 'incorrect') {
                $messages[] = get_string('creditincorrect', 'qtype_buchungssatz');
            } else if ($feedback['credit_status'] === 'partial') {
                if (!empty($feedback['has_extra_credit'])) {
                    $messages[] = get_string('credithasextraaccounts', 'qtype_buchungssatz');
                } else {
                    $messages[] = get_string('creditpartiallyincorrect', 'qtype_buchungssatz');
                }
            }

            // Determine alert class based on whether it's fully wrong or partially wrong.
            $haspartial = ($feedback['debit_status'] === 'partial' || $feedback['credit_status'] === 'partial');
            $alertclass = $haspartial ? 'alert alert-warning' : 'alert alert-danger';
            $icon = $haspartial ? 'fa-exclamation-triangle' : 'fa-times-circle';

            $html .= html_writer::tag('div',
                '<i class="fa ' . $icon . '"></i> ' . implode(' ', $messages),
                ['class' => $alertclass]
            );
        }

        $html .= html_writer::end_div();

        return $html;
    }
}
