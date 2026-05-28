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
    /** @var \qtype_buchungssatz\feedback_calculator Lazily-constructed feedback calculator. */
    protected $feedbackcalculator;

    /**
     * Get the feedback calculator instance, constructing it on first use.
     *
     * @return \qtype_buchungssatz\feedback_calculator
     */
    protected function feedback_calculator(): \qtype_buchungssatz\feedback_calculator {
        if ($this->feedbackcalculator === null) {
            $this->feedbackcalculator = new \qtype_buchungssatz\feedback_calculator();
        }
        return $this->feedbackcalculator;
    }

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
        $containerid = 'buchungssatz-container-' . $qa->get_slot();
        $templateid = 'buchungssatz-entry-template-' . $qa->get_slot();
        $numberformat = $question->numberformat ?? 'de';
        $visiblerows = $this->count_visible_rows($response);
        $filteredaccounts = $this->build_filtered_accounts($question, $response, (bool)$options->readonly);

        $result = html_writer::tag('div', $question->format_questiontext($qa), ['class' => 'qtext']);
        if (!empty($question->allornothinggrading)) {
            $result .= html_writer::tag(
                'div',
                get_string('allornothinggrading_notice', 'qtype_buchungssatz'),
                ['class' => 'alert alert-warning mt-2 mb-2']
            );
        }

        $containerattrs = [
            'id' => $containerid,
            'data-accounts' => json_encode(array_values($filteredaccounts)),
            'data-allowedit' => !$options->readonly ? '1' : '0',
            'data-numberformat' => $numberformat,
            'data-nextindex' => $visiblerows,
            'data-templateid' => $templateid,
        ];
        $result .= html_writer::start_div('buchungssatz-question-container', $containerattrs);
        $result .= $this->render_question_table($qa, $options, $question, $response, $filteredaccounts, $visiblerows, $templateid);
        $result .= html_writer::div('', 'buchungssatz-mobile-view', ['style' => 'display:none;']);
        $result .= html_writer::end_div();

        $this->register_js_strings_and_module($containerid);
        return $result;
    }

    /**
     * Determine how many entry rows to render based on populated response keys.
     *
     * @param array $response The student response data.
     * @return int At least 1; one more than the highest populated index found in the response.
     */
    protected function count_visible_rows(array $response): int {
        $visiblerows = 1;
        foreach (array_keys($response) as $key) {
            if (preg_match('/^(?:sollkonto|habenkonto)_(\d+)$/', $key, $matches)) {
                $idx = (int)$matches[1];
                if (!empty($response[$key]) && $idx >= $visiblerows) {
                    $visiblerows = $idx + 1;
                }
            }
        }
        return $visiblerows;
    }

    /**
     * Build the filtered account list used by all dropdowns on this question.
     *
     * In edit mode with a per-question dropdown limit, the list is narrowed to the correct
     * accounts plus a random sample plus any accounts the student has already selected.
     *
     * @param object $question The question definition.
     * @param array $response The current student response (used to preserve selected accounts).
     * @param bool $readonly True when rendering the review/feedback view (no filtering).
     * @return array Account records keyed by ID.
     */
    protected function build_filtered_accounts(object $question, array $response, bool $readonly): array {
        return (new \qtype_buchungssatz\account_provider())
            ->build_filtered_accounts($question, $response, $readonly);
    }

    /**
     * Render the main question table (colgroup, header, body rows, template, footer controls).
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options The display options.
     * @param object $question The question definition.
     * @param array $response The student response data.
     * @param array $filteredaccounts The shared dropdown account list.
     * @param int $visiblerows How many entry rows to render.
     * @param string $templateid The DOM id used for the JS row template (only in edit mode).
     * @return string The HTML for the table.
     */
    protected function render_question_table(
        question_attempt $qa,
        question_display_options $options,
        object $question,
        array $response,
        array $filteredaccounts,
        int $visiblerows,
        string $templateid
    ): string {
        $html = '<table class="table table-bordered buchungssatz-student-table">';
        $html .= $this->render_colgroup(!$options->readonly);
        $html .= $this->render_header_row(!$options->readonly);
        $html .= '<tbody class="buchungssatz-entries">';

        $feedbackmap = $options->readonly
            ? $this->feedback_calculator()->build_cell_feedback_map($question, $response)
            : [];
        for ($i = 0; $i < $visiblerows; $i++) {
            $html .= $this->render_entry_row(
                $qa,
                $options,
                $i,
                $response,
                ['soll' => $filteredaccounts, 'haben' => $filteredaccounts],
                $question,
                false,
                !$options->readonly,
                $feedbackmap[$i] ?? []
            );
        }
        $html .= '</tbody>';

        if (!$options->readonly) {
            $html .= $this->render_template_row($qa, $question, $filteredaccounts, $templateid);
            $html .= $this->render_footer_controls();
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * Render the table footer with the "Add debit" / "Add credit" buttons (edit mode only).
     *
     * @return string The HTML for the tfoot row.
     */
    protected function render_footer_controls(): string {
        $html = '<tfoot class="buchungssatz-controls-footer">';
        $html .= '<tr>';
        $html .= '<td></td>'; // Per label column.
        $html .= '<td>';
        $html .= html_writer::tag('button', get_string('adddebitentry', 'qtype_buchungssatz'), [
            'type' => 'button',
            'class' => 'btn btn-secondary btn-sm buchungssatz-add-debit-entry',
        ]);
        $html .= '</td>';
        $html .= '<td></td>'; // Soll Amount column.
        $html .= '<td></td>'; // The 'an' label column.
        $html .= '<td>';
        $html .= html_writer::tag('button', get_string('addcreditentry', 'qtype_buchungssatz'), [
            'type' => 'button',
            'class' => 'btn btn-secondary btn-sm buchungssatz-add-credit-entry',
        ]);
        $html .= '</td>';
        $html .= '<td></td>'; // Haben Amount column.
        $html .= '<td></td>'; // Actions column.
        $html .= '</tr>';
        $html .= '</tfoot>';
        return $html;
    }

    /**
     * Register the JS language strings the student-side AMD module needs and load the module.
     *
     * @param string $containerid The DOM id of the question container.
     */
    protected function register_js_strings_and_module(string $containerid): void {
        $strings = [
            'selectaccount', 'debitentries', 'creditentries', 'adddebitentry', 'addcreditentry',
            'account', 'amount', 'soll', 'haben',
        ];
        foreach ($strings as $key) {
            $this->page->requires->string_for_js($key, 'qtype_buchungssatz');
        }
        // Only the container ID is passed - other data is in data attributes to avoid Moodle 3.10 size limits.
        $this->page->requires->js_call_amd('qtype_buchungssatz/question', 'init', [$containerid]);
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
     * @param array $accounts Available accounts for the dropdowns, keyed 'soll' and 'haben'.
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
        array $accounts,
        object $question,
        bool $hidden = false,
        bool $showdelete = false,
        array $feedbackclasses = []
    ): string {
        $vals = [
            'sollkonto' => $response["sollkonto_{$index}"] ?? '',
            'sollbetrag' => $response["sollbetrag_{$index}"] ?? '',
            'habenkonto' => $response["habenkonto_{$index}"] ?? '',
            'habenbetrag' => $response["habenbetrag_{$index}"] ?? '',
        ];
        $entrytype = \qtype_buchungssatz\entry_helper::determine_entry_type($vals['sollkonto'], $vals['habenkonto']);
        $hiddenclasses = \qtype_buchungssatz\entry_helper::get_hidden_classes($entrytype);
        $numberformat = $question->numberformat ?? 'de';

        $rowstyle = $hidden ? 'display: none;' : '';
        $html = '<tr class="buchungssatz-entry-row" data-entry="' . $index
            . '" data-entry-type="' . $entrytype . '" style="' . $rowstyle . '">';
        $html .= $this->render_debit_cells(
            $qa,
            $options,
            $index,
            $vals,
            $accounts['soll'],
            $hiddenclasses,
            $showdelete,
            $numberformat,
            $feedbackclasses
        );
        $html .= $this->render_credit_cells(
            $qa,
            $options,
            $index,
            $vals,
            $accounts['haben'],
            $hiddenclasses,
            $showdelete,
            $numberformat,
            $feedbackclasses
        );
        $html .= '</tr>';
        return $html;
    }

    /**
     * Render the debit (Soll) side cells of an entry row plus the central "an" label cell.
     *
     * @param question_attempt $qa The question attempt.
     * @param question_display_options $options Display options (readonly flag).
     * @param int $index Entry row index.
     * @param array $vals Pre-extracted values keyed by 'sollkonto' / 'sollbetrag' / 'habenkonto' / 'habenbetrag'.
     * @param array $sollaccounts Accounts available in the debit dropdown.
     * @param array $hiddenclasses Hidden-cell classes from entry_helper::get_hidden_classes().
     * @param bool $showdelete Whether to render the debit delete button.
     * @param string $numberformat 'de' or 'us'.
     * @param array $feedbackclasses Per-field feedback CSS classes (sollkonto/sollbetrag/...).
     * @return string HTML for the per-label cell, debit account cell, debit amount cell, and 'an' cell.
     */
    protected function render_debit_cells(
        question_attempt $qa,
        question_display_options $options,
        int $index,
        array $vals,
        array $sollaccounts,
        array $hiddenclasses,
        bool $showdelete,
        string $numberformat,
        array $feedbackclasses
    ): string {
        $debithidden = $hiddenclasses['debit'];
        $html = '<td class="buchungssatz-label-cell' . $debithidden . '" data-section="soll"></td>';
        $html .= '<td class="buchungssatz-data-cell' . $debithidden
            . '" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';
        $html .= $this->render_account_field(
            $options->readonly,
            $vals['sollkonto'],
            $qa->get_qt_field_name("sollkonto_{$index}"),
            $sollaccounts,
            $feedbackclasses['sollkonto'] ?? ''
        );
        $html .= '</td>';
        $html .= '<td class="buchungssatz-data-cell' . $debithidden
            . '" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
        $html .= $this->render_amount_field(
            $options->readonly,
            $vals['sollbetrag'],
            $qa->get_qt_field_name("sollbetrag_{$index}"),
            $feedbackclasses['sollbetrag'] ?? '',
            $numberformat
        );
        $html .= '</td>';
        // The "an" label cell - contains debit delete button if editable.
        $html .= '<td class="buchungssatz-label-cell' . $debithidden . '" data-section="haben">';
        if ($showdelete) {
            $html .= \qtype_buchungssatz\entry_helper::render_delete_button('debit', $index, 'data-entry');
        }
        $html .= '</td>';
        return $html;
    }

    /**
     * Render the credit (Haben) side cells of an entry row plus the credit delete cell (if shown).
     *
     * @param question_attempt $qa The question attempt.
     * @param question_display_options $options Display options (readonly flag).
     * @param int $index Entry row index.
     * @param array $vals Pre-extracted values keyed by 'sollkonto' / 'sollbetrag' / 'habenkonto' / 'habenbetrag'.
     * @param array $habenaccounts Accounts available in the credit dropdown.
     * @param array $hiddenclasses Hidden-cell classes from entry_helper::get_hidden_classes().
     * @param bool $showdelete Whether to render the credit delete cell.
     * @param string $numberformat 'de' or 'us'.
     * @param array $feedbackclasses Per-field feedback CSS classes.
     * @return string HTML for the credit account cell, credit amount cell, and optional actions cell.
     */
    protected function render_credit_cells(
        question_attempt $qa,
        question_display_options $options,
        int $index,
        array $vals,
        array $habenaccounts,
        array $hiddenclasses,
        bool $showdelete,
        string $numberformat,
        array $feedbackclasses
    ): string {
        $credithidden = $hiddenclasses['credit'];
        $html = '<td class="buchungssatz-data-cell' . $credithidden
            . '" data-label="' . get_string('account', 'qtype_buchungssatz') . '">';
        $html .= $this->render_account_field(
            $options->readonly,
            $vals['habenkonto'],
            $qa->get_qt_field_name("habenkonto_{$index}"),
            $habenaccounts,
            $feedbackclasses['habenkonto'] ?? ''
        );
        $html .= '</td>';
        $html .= '<td class="buchungssatz-data-cell' . $credithidden
            . '" data-label="' . get_string('amount', 'qtype_buchungssatz') . '">';
        $html .= $this->render_amount_field(
            $options->readonly,
            $vals['habenbetrag'],
            $qa->get_qt_field_name("habenbetrag_{$index}"),
            $feedbackclasses['habenbetrag'] ?? '',
            $numberformat
        );
        $html .= '</td>';
        if ($showdelete) {
            $html .= '<td class="buchungssatz-actions-cell' . $credithidden . '">';
            $html .= \qtype_buchungssatz\entry_helper::render_delete_button('credit', $index, 'data-entry');
            $html .= '</td>';
        }
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
     * Generate the specific feedback for the question.
     *
     * Compares the student's response against the correct answer using aggregation
     * and shows whether debit/credit sides are correct, partial, or incorrect.
     *
     * @param question_attempt $qa The question attempt object.
     * @return string The HTML output.
     */
    public function specific_feedback(question_attempt $qa): string {
        $feedback = $this->feedback_calculator()->calculate_aggregated_feedback(
            $qa->get_question(),
            $qa->get_last_qt_data()
        );
        return (new \qtype_buchungssatz\feedback_renderer())->render($feedback);
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
        $accounts = (new \qtype_buchungssatz\account_provider())->get_for_chart($question->chartofaccountsid);
        return (new \qtype_buchungssatz\answer_renderer())->render($question, $accounts);
    }

    /**
     * Legacy stub kept until extension points are cleaned up.
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
}
