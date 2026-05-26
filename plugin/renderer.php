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
 * Buchungssatz question renderer class.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates the output for Buchungssatz questions.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $questiontext = $question->format_questiontext($qa);
        $result = html_writer::tag('div', $questiontext, ['class' => 'qtext']);

        // Show notice when all-or-nothing grading is enabled.
        if (!empty($question->allornothinggrading)) {
            $result .= html_writer::tag(
                'div',
                get_string('allornothinggrading_notice', 'qtype_buchungssatz'),
                ['class' => 'alert alert-warning mt-2 mb-2']
            );
        }

        // Load available accounts for dropdowns.
        $accounts = $this->get_accounts($question->chartofaccountsid);

        // Create a unique container ID for this question.
        $containerid = 'buchungssatz-container-' . $qa->get_slot();
        $templateid = 'buchungssatz-entry-template-' . $qa->get_slot();

        // Get number format settings for data attributes.
        $numberformat = $question->numberformat ?? 'de';
        // Determine how many rows to render (all visible — no hidden pre-rendered rows).
        $visiblerows = 1; // At least one row visible.
        foreach (array_keys($response) as $key) {
            if (preg_match('/^(?:sollkonto|habenkonto)_(\d+)$/', $key, $matches)) {
                $idx = (int)$matches[1];
                if (!empty($response[$key]) && $idx >= $visiblerows) {
                    $visiblerows = $idx + 1;
                }
            }
        }

        // Pre-compute a single shared filtered account list for all dropdowns.
        $accountslimit = $question->accountsindropdown ?? 0;
        $filteredaccounts = $accounts;
        if ($accountslimit > 0 && !$options->readonly) {
            // Collect student-selected account IDs as safety net.
            $selectedids = [];
            foreach ($response as $key => $value) {
                if (preg_match('/^(?:sollkonto|habenkonto)_\d+$/', $key) && $value !== '' && (int)$value > 0) {
                    $selectedids[(int)$value] = true;
                }
            }
            $filteredaccounts = $this->filter_accounts_for_dropdown(
                $accounts,
                $question->get_all_correct_account_ids(),
                $accountslimit,
                array_keys($selectedids),
                $question->dropdownseed ?? 0
            );
        }

        // Wrap everything in our own container with data attributes for JS.
        $containerattrs = [
            'id' => $containerid,
            'data-accounts' => json_encode(array_values($filteredaccounts)),
            'data-allowedit' => !$options->readonly ? '1' : '0',
            'data-numberformat' => $numberformat,
            'data-nextindex' => $visiblerows,
            'data-templateid' => $templateid,
        ];
        $result .= html_writer::start_div('buchungssatz-question-container', $containerattrs);

        // Start the table.
        $result .= '<table class="table table-bordered buchungssatz-student-table">';

        // Define column widths with colgroup for consistent rendering across Bootstrap versions.
        $result .= $this->render_colgroup(!$options->readonly);

        // Header row - always show actions column if not readonly.
        $result .= $this->render_header_row(!$options->readonly);

        $result .= '<tbody class="buchungssatz-entries">';

        // Build per-cell feedback map when in readonly (review) mode.
        $feedbackmap = [];
        if ($options->readonly) {
            $feedbackmap = $this->build_cell_feedback_map($question, $response);
        }

        // Render only the needed rows — all visible (no hidden pre-rendered rows).
        for ($i = 0; $i < $visiblerows; $i++) {
            $result .= $this->render_entry_row(
                $qa,
                $options,
                $i,
                $response,
                $filteredaccounts,
                $filteredaccounts,
                $question,
                false,
                !$options->readonly,
                $feedbackmap[$i] ?? []
            );
        }

        $result .= '</tbody>';

        // Add a <template> element for JS to clone new rows (only in edit mode).
        if (!$options->readonly) {
            $result .= $this->render_template_row($qa, $question, $filteredaccounts, $templateid);
        }

        // Add buttons as a table footer row, positioned below the account columns.
        if (!$options->readonly) {
            $result .= '<tfoot class="buchungssatz-controls-footer">';
            $result .= '<tr>';
            $result .= '<td></td>'; // Per label column.
            $result .= '<td>';
            $result .= html_writer::tag('button', get_string('adddebitentry', 'qtype_buchungssatz'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm buchungssatz-add-debit-entry',
            ]);
            $result .= '</td>';
            $result .= '<td></td>'; // Soll Amount column.
            $result .= '<td></td>'; // The 'an' label column.
            $result .= '<td>';
            $result .= html_writer::tag('button', get_string('addcreditentry', 'qtype_buchungssatz'), [
                'type' => 'button',
                'class' => 'btn btn-secondary btn-sm buchungssatz-add-credit-entry',
            ]);
            $result .= '</td>';
            $result .= '<td></td>'; // Haben Amount column.
            $result .= '<td></td>'; // Actions column.
            $result .= '</tr>';
            $result .= '</tfoot>';
        }

        $result .= '</table>';

        // Add mobile view container (populated by JS on small screens).
        $result .= html_writer::div('', 'buchungssatz-mobile-view', ['style' => 'display:none;']);

        $result .= html_writer::end_div(); // End container.

        // Load language strings for JavaScript.
        $this->page->requires->string_for_js('selectaccount', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('debitentries', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('creditentries', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('adddebitentry', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('addcreditentry', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('account', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('amount', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('soll', 'qtype_buchungssatz');
        $this->page->requires->string_for_js('haben', 'qtype_buchungssatz');

        // Include JavaScript for interactive features.
        // Only pass the container ID - other data is in data attributes to avoid Moodle 3.10 size limits.
        $this->page->requires->js_call_amd('qtype_buchungssatz/question', 'init', [$containerid]);

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
     * @param array $feedbackclasses Per-cell feedback CSS classes keyed by field name.
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
        array $feedbackclasses = []
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

        // Determine entry type and hidden classes.
        $entrytype = \qtype_buchungssatz\entry_helper::determine_entry_type($sollkontoval, $habenkontoval);
        $hiddenclasses = \qtype_buchungssatz\entry_helper::get_hidden_classes($entrytype);
        $debithidden = $hiddenclasses['debit'];
        $credithidden = $hiddenclasses['credit'];

        // Build table row.
        $rowstyle = $hidden ? 'display: none;' : '';
        $html = '<tr class="buchungssatz-entry-row" data-entry="' . $index
            . '" data-entry-type="' . $entrytype . '" style="' . $rowstyle . '">';

        // Per label cell - add data-section for mobile header.
        $html .= '<td class="buchungssatz-label-cell' . $debithidden . '" data-section="soll"></td>';

        // Soll (Debit) account cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell' . $debithidden
            . '" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';
        $html .= $this->render_account_field(
            $readonly,
            $sollkontoval,
            $sollkontoname,
            $sollaccounts,
            $feedbackclasses['sollkonto'] ?? ''
        );
        $html .= '</td>';

        // Get number format settings from question.
        $numberformat = $question->numberformat ?? 'de';

        // Soll (Debit) amount cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell' . $debithidden
            . '" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
        $html .= $this->render_amount_field(
            $readonly,
            $sollbetragval,
            $sollbetragname,
            $feedbackclasses['sollbetrag'] ?? '',
            $numberformat
        );
        $html .= '</td>';

        // The "an" label cell - contains debit delete button if editable.
        $html .= '<td class="buchungssatz-label-cell' . $debithidden . '" data-section="haben">';
        if ($showdelete) {
            $html .= \qtype_buchungssatz\entry_helper::render_delete_button('debit', $index, 'data-entry');
        } else {
            $html .= '';
        }
        $html .= '</td>';

        // Haben (Credit) account cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell' . $credithidden
            . '" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';
        $html .= $this->render_account_field(
            $readonly,
            $habenkontoval,
            $habenkontoname,
            $habenaccounts,
            $feedbackclasses['habenkonto'] ?? ''
        );
        $html .= '</td>';

        // Haben (Credit) amount cell - add data-label for mobile.
        $html .= '<td class="buchungssatz-data-cell' . $credithidden
            . '" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
        $html .= $this->render_amount_field(
            $readonly,
            $habenbetragval,
            $habenbetragname,
            $feedbackclasses['habenbetrag'] ?? '',
            $numberformat
        );
        $html .= '</td>';

        // Delete button cell (credit delete only - debit delete is in the "an" cell).
        if ($showdelete) {
            $html .= '<td class="buchungssatz-actions-cell' . $credithidden . '">';
            $html .= \qtype_buchungssatz\entry_helper::render_delete_button('credit', $index, 'data-entry');
            $html .= '</td>';
        }

        $html .= '</tr>';

        return $html;
    }

    /**
     * Render a <template> element for JS to clone new entry rows.
     *
     * Produces a row identical in structure to render_entry_row() output but
     * with __INDEX__ as a placeholder. JS replaces __INDEX__ with the real
     * index when cloning. Uses all accounts (unfiltered) since we can't know
     * the correct entry for dynamically added rows.
     *
     * @param question_attempt $qa The question attempt object.
     * @param object $question The question object.
     * @param array $accounts All available accounts.
     * @param string $templateid The HTML id for the template element.
     * @return string The HTML <template> element.
     */
    protected function render_template_row(
        question_attempt $qa,
        object $question,
        array $accounts,
        string $templateid
    ): string {
        $placeholder = '__INDEX__';

        $sollkontoname = $qa->get_qt_field_name("sollkonto_{$placeholder}");
        $sollbetragname = $qa->get_qt_field_name("sollbetrag_{$placeholder}");
        $habenkontoname = $qa->get_qt_field_name("habenkonto_{$placeholder}");
        $habenbetragname = $qa->get_qt_field_name("habenbetrag_{$placeholder}");

        $numberformat = $question->numberformat ?? 'de';
        $amountplaceholder = ($numberformat === 'us') ? '0.00' : '0,00';
        $accountlabel = get_string('account', 'qtype_buchungssatz');
        $amountlabel = get_string('amount', 'qtype_buchungssatz');
        $selectplaceholder = get_string('selectaccount', 'qtype_buchungssatz');

        // Build account options HTML.
        $optionshtml = \qtype_buchungssatz\entry_helper::build_account_options($accounts, '', $selectplaceholder);

        $html = '<template id="' . $templateid . '">';
        $html .= '<tr class="buchungssatz-entry-row" data-entry="' . $placeholder . '" data-entry-type="both" style="">';

        // Per label cell.
        $html .= '<td class="buchungssatz-label-cell" data-section="soll"></td>';

        // Soll (Debit) account cell.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . $accountlabel . '">';
        $html .= '<select name="' . $sollkontoname . '" id="' . $sollkontoname . '" ' .
            'class="form-control buchungssatz-account-select" ' .
            'aria-label="' . $accountlabel . '">' . $optionshtml . '</select>';
        $html .= '</td>';

        // Soll (Debit) amount cell.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . $amountlabel . '">';
        $html .= '<input type="text" name="' . $sollbetragname . '" id="' . $sollbetragname . '" value="" ' .
            'class="form-control buchungssatz-amount-input" placeholder="' . $amountplaceholder . '" ' .
            'inputmode="decimal" aria-label="' . $amountlabel . '">';
        $html .= '</td>';

        // The "an" label cell — contains debit delete button.
        $html .= '<td class="buchungssatz-label-cell" data-section="haben">';
        $html .= \qtype_buchungssatz\entry_helper::render_delete_button('debit', $placeholder, 'data-entry');
        $html .= '</td>';

        // Haben (Credit) account cell.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . $accountlabel . '">';
        $html .= '<select name="' . $habenkontoname . '" id="' . $habenkontoname . '" ' .
            'class="form-control buchungssatz-account-select" ' .
            'aria-label="' . $accountlabel . '">' . $optionshtml . '</select>';
        $html .= '</td>';

        // Haben (Credit) amount cell.
        $html .= '<td class="buchungssatz-data-cell" data-label="' . $amountlabel . '">';
        $html .= '<input type="text" name="' . $habenbetragname . '" id="' . $habenbetragname . '" value="" ' .
            'class="form-control buchungssatz-amount-input" placeholder="' . $amountplaceholder . '" ' .
            'inputmode="decimal" aria-label="' . $amountlabel . '">';
        $html .= '</td>';

        // Credit delete button cell.
        $html .= '<td class="buchungssatz-actions-cell">';
        $html .= \qtype_buchungssatz\entry_helper::render_delete_button('credit', $placeholder, 'data-entry');
        $html .= '</td>';

        $html .= '</tr>';
        $html .= '</template>';

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
    protected function render_account_field(
        bool $readonly,
        string $value,
        string $name,
        array $accounts,
        string $feedbackclass = ''
    ): string {
        if ($readonly) {
            // Look up account name from ID for display.
            $displayval = \qtype_buchungssatz\entry_helper::format_account_display_by_id((int)$value, $accounts);
            if (empty($displayval)) {
                $displayval = $value;
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
     * @return string The HTML output.
     */
    protected function render_amount_field(
        bool $readonly,
        string $value,
        string $name,
        string $feedbackclass = '',
        string $numberformat = 'de'
    ): string {
        if ($readonly) {
            $spanclass = 'buchungssatz-readonly';
            if (!empty($feedbackclass)) {
                $spanclass .= ' ' . $feedbackclass;
            }
            // Parse the amount (handles both German and US formats) then format for display.
            $parsedvalue = \qtype_buchungssatz\amount_helper::parse_amount($value, $numberformat);
            $displayval = $this->format_amount_display($parsedvalue, $numberformat);
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
        $optionshtml = \qtype_buchungssatz\entry_helper::build_account_options($accounts, $selected, $placeholder);
        return '<select name="' . $name . '" id="' . $name . '" ' .
            'class="form-control buchungssatz-account-select" ' .
            'aria-label="' . get_string('account', 'qtype_buchungssatz') . '">' .
            $optionshtml . '</select>';
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

        return $DB->get_records(
            'qtype_buchungssatz_accounts',
            ['chartid' => $chartid],
            'accountname'
        );
    }

    /**
     * Filter accounts for dropdowns based on the accountsindropdown setting.
     *
     * Builds a shared pool for all dropdowns: all correct accounts (from all
     * entries) + all student-selected accounts + N additional random accounts
     * chosen via a seeded shuffle for determinism across page reloads.
     *
     * @param array $allaccounts All available accounts from the chart.
     * @param array $correctaccountids Array of correct account IDs from all entries.
     * @param int $limit Number of additional random accounts to include (0 = all).
     * @param array $selectedaccountids Array of student-selected account IDs.
     * @param int $seed Random seed for deterministic shuffling.
     * @return array The filtered list of account records, sorted by account name.
     */
    protected function filter_accounts_for_dropdown(
        array $allaccounts,
        array $correctaccountids,
        int $limit,
        array $selectedaccountids = [],
        int $seed = 0
    ): array {
        // If limit is 0 or no accounts, return all.
        if ($limit <= 0 || empty($allaccounts)) {
            return $allaccounts;
        }

        // Build lookup sets for O(1) membership testing.
        $correctset = array_flip($correctaccountids);
        $selectedset = array_flip($selectedaccountids);

        $result = [];
        $otheraccounts = [];

        // Separate must-include accounts (correct + selected) from others.
        foreach ($allaccounts as $account) {
            if (
                isset($correctset[$account->id]) ||
                    isset($selectedset[$account->id])
            ) {
                $result[$account->id] = $account;
            } else {
                $otheraccounts[$account->id] = $account;
            }
        }

        // If limit is greater than or equal to other accounts available, return all.
        if ($limit >= count($otheraccounts)) {
            return $allaccounts;
        }

        // Select 'limit' random accounts using a seeded shuffle for determinism.
        if (!empty($otheraccounts)) {
            $otherkeys = array_values(array_keys($otheraccounts));
            $this->seeded_shuffle($otherkeys, $seed);
            $selectedkeys = array_slice($otherkeys, 0, $limit);

            foreach ($selectedkeys as $key) {
                $result[$key] = $otheraccounts[$key];
            }
        }

        // Sort by account name for consistent display.
        uasort($result, function ($a, $b) {
            return strcmp($a->accountname, $b->accountname);
        });

        return $result;
    }

    /**
     * Shuffle an array in place using a deterministic seed.
     *
     * Uses Fisher-Yates (Knuth) shuffle with mt_rand seeded by the given value.
     *
     * @param array $array The array to shuffle (modified in place by reference).
     * @param int $seed The random seed.
     */
    protected function seeded_shuffle(array &$array, int $seed): void {
        mt_srand($seed);
        $count = count($array);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }
        // Reseed to avoid predictable sequences in subsequent mt_rand calls.
        mt_srand();
    }

    /**
     * Generate the specific feedback for the question.
     *
     * Compares the student's response against the correct answer using aggregation
     * and shows whether debit/credit sides are correct, partial, or incorrect.
     *
     * @param question_attempt $qa The question attempt object.
     * @return string The HTML output.
     */
    public function specific_feedback(question_attempt $qa): string {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $feedback = $this->calculate_aggregated_feedback($question, $response);

        return $this->render_feedback_summary($feedback);
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
        $sollkontostr = get_string('sollkonto', 'qtype_buchungssatz');
        $sollbetragstr = get_string('sollbetrag', 'qtype_buchungssatz');
        $habenkontostr = get_string('habenkonto', 'qtype_buchungssatz');
        $habenbetragstr = get_string('habenbetrag', 'qtype_buchungssatz');

        $html .= html_writer::start_tag('tbody');
        foreach ($question->entries as $entry) {
            $html .= html_writer::start_tag('tr');
            $sollname = \qtype_buchungssatz\entry_helper::format_account_display_by_id(
                (int)($entry['sollkontoid'] ?? 0),
                $accounts
            );
            $habenname = \qtype_buchungssatz\entry_helper::format_account_display_by_id(
                (int)($entry['habenkontoid'] ?? 0),
                $accounts
            );
            $html .= html_writer::tag(
                'td',
                s($sollname),
                ['data-label' => $sollkontostr, 'style' => 'text-align: start;' ]
            );
            $html .= html_writer::tag(
                'td',
                $this->format_amount_display($entry['sollbetrag'], $numberformat),
                ['data-label' => $sollbetragstr, 'style' => 'text-align: end;' ]
            );
            $html .= html_writer::tag(
                'td',
                s($habenname),
                ['data-label' => $habenkontostr, 'style' => 'text-align: start;' ]
            );
            $html .= html_writer::tag(
                'td',
                $this->format_amount_display($entry['habenbetrag'], $numberformat),
                ['data-label' => $habenbetragstr, 'style' => 'text-align: end;' ]
            );
            $html .= html_writer::end_tag('tr');
            // Show explanation as separate row if present.
            if (!empty($entry['explanation'])) {
                $html .= html_writer::start_tag('tr', ['class' => 'buchungssatz-explanation-row']);
                $html .= html_writer::tag(
                    'td',
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
     * Format an amount for display, showing empty for zero/non-existent amounts.
     *
     * @param float $amount The amount value.
     * @param string $format The number format: 'de' for German (1.234,56) or 'us' for US (1,234.56).
     * @return string The formatted amount or empty string if zero.
     */
    protected function format_amount_display(float $amount, string $format = 'de'): string {
        if (abs($amount) < 0.01) {
            return '';
        }
        if ($format === 'us') {
            return number_format($amount, 2, '.', ',');
        }
        // Default: German/EU format.
        return number_format($amount, 2, ',', '.');
    }

    /**
     * Build per-cell feedback classes for each student entry row.
     *
     * Uses aggregation logic: entries with the same account are summed, then compared
     * against the correct totals. All rows sharing an account get the same feedback.
     *
     * @param object $question The question object with correct entries.
     * @param array $response The student response data.
     * @return array Map of row index => keyed array of CSS classes for sollkonto, sollbetrag, habenkonto, habenbetrag.
     */
    protected function build_cell_feedback_map(object $question, array $response): array {
        $feedbackmap = [];

        if (empty($question->entries)) {
            return $feedbackmap;
        }

        $numberformat = $question->numberformat ?? 'de';

        // Aggregate correct entries.
        $correctaggregated = $this->aggregate_entries_for_feedback($question->entries);

        // Find the highest entry index in the response.
        $maxindex = -1;
        foreach (array_keys($response) as $key) {
            if (preg_match('/^(?:sollkonto|habenkonto)_(\d+)$/', $key, $matches)) {
                $maxindex = max($maxindex, (int)$matches[1]);
            }
        }

        // Aggregate student entries per account ID per side.
        $studentaggregated = ['debit' => [], 'credit' => []];
        for ($i = 0; $i <= $maxindex; $i++) {
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);

            if ($sollkontoid > 0) {
                $amount = \qtype_buchungssatz\amount_helper::parse_amount($response["sollbetrag_{$i}"] ?? '', $numberformat);
                if (!isset($studentaggregated['debit'][$sollkontoid])) {
                    $studentaggregated['debit'][$sollkontoid] = 0;
                }
                $studentaggregated['debit'][$sollkontoid] += $amount;
            }

            if ($habenkontoid > 0) {
                $amount = \qtype_buchungssatz\amount_helper::parse_amount($response["habenbetrag_{$i}"] ?? '', $numberformat);
                if (!isset($studentaggregated['credit'][$habenkontoid])) {
                    $studentaggregated['credit'][$habenkontoid] = 0;
                }
                $studentaggregated['credit'][$habenkontoid] += $amount;
            }
        }

        // Determine per-account correctness for each side.
        $accountstatus = ['debit' => [], 'credit' => []];
        foreach (['debit', 'credit'] as $side) {
            foreach ($studentaggregated[$side] as $account => $studentamount) {
                if (!isset($correctaggregated[$side][$account])) {
                    // Extra account not in the correct answer.
                    $accountstatus[$side][$account] = ['account' => 'incorrect', 'amount' => 'incorrect'];
                } else {
                    $correctamount = $correctaggregated[$side][$account];
                    $amountstatus = (abs($studentamount - $correctamount) < 0.01) ? 'correct' : 'incorrect';
                    $accountstatus[$side][$account] = ['account' => 'correct', 'amount' => $amountstatus];
                }
            }
        }

        // Map back to individual rows.
        for ($i = 0; $i <= $maxindex; $i++) {
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);

            $row = [
                'sollkonto' => '',
                'sollbetrag' => '',
                'habenkonto' => '',
                'habenbetrag' => '',
            ];

            if ($sollkontoid > 0 && isset($accountstatus['debit'][$sollkontoid])) {
                $status = $accountstatus['debit'][$sollkontoid];
                $row['sollkonto'] = 'buchungssatz-' . $status['account'];
                $row['sollbetrag'] = 'buchungssatz-' . $status['amount'];
            }

            if ($habenkontoid > 0 && isset($accountstatus['credit'][$habenkontoid])) {
                $status = $accountstatus['credit'][$habenkontoid];
                $row['habenkonto'] = 'buchungssatz-' . $status['account'];
                $row['habenbetrag'] = 'buchungssatz-' . $status['amount'];
            }

            $feedbackmap[$i] = $row;
        }

        return $feedbackmap;
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
            'debit_status' => 'correct', // One of: correct, partial, incorrect.
            'credit_status' => 'correct', // One of: correct, partial, incorrect.
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
        $numberformat = $question->numberformat ?? 'de';
        $studententries = $this->parse_student_response($response, $numberformat);
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
            $sollkontoid = (int)($entry['sollkontoid'] ?? 0);
            if ($sollkontoid > 0) {
                if (!isset($aggregated['debit'][$sollkontoid])) {
                    $aggregated['debit'][$sollkontoid] = 0;
                }
                $aggregated['debit'][$sollkontoid] += (float)($entry['sollbetrag'] ?? 0);
            }

            // Aggregate Credit (Haben) side.
            $habenkontoid = (int)($entry['habenkontoid'] ?? 0);
            if ($habenkontoid > 0) {
                if (!isset($aggregated['credit'][$habenkontoid])) {
                    $aggregated['credit'][$habenkontoid] = 0;
                }
                $aggregated['credit'][$habenkontoid] += (float)($entry['habenbetrag'] ?? 0);
            }
        }

        return $aggregated;
    }

    /**
     * Parse student response into entries array.
     *
     * @param array $response The response data.
     * @param string $numberformat The number format: 'de' or 'us'.
     * @return array The parsed entries.
     */
    protected function parse_student_response(array $response, string $numberformat = 'de'): array {
        $entries = [];

        // Dynamically find the highest entry index in the response.
        $maxindex = -1;
        foreach (array_keys($response) as $key) {
            if (preg_match('/^(?:sollkonto|habenkonto)_(\d+)$/', $key, $matches)) {
                $maxindex = max($maxindex, (int)$matches[1]);
            }
        }

        for ($i = 0; $i <= $maxindex; $i++) {
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);

            if ($sollkontoid > 0 || $habenkontoid > 0) {
                $entries[] = [
                    'sollkontoid' => $sollkontoid,
                    'sollbetrag' => \qtype_buchungssatz\amount_helper::parse_amount(
                        $response["sollbetrag_{$i}"] ?? '',
                        $numberformat
                    ),
                    'habenkontoid' => $habenkontoid,
                    'habenbetrag' => \qtype_buchungssatz\amount_helper::parse_amount(
                        $response["habenbetrag_{$i}"] ?? '',
                        $numberformat
                    ),
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
            $html .= html_writer::tag(
                'div',
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

            $html .= html_writer::tag(
                'div',
                '<i class="fa ' . $icon . '"></i> ' . implode(' ', $messages),
                ['class' => $alertclass]
            );
        }

        $html .= html_writer::end_div();

        return $html;
    }
}
