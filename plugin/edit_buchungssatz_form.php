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
 * Defines the editing form for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Buchungssatz question editing form definition.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_edit_form extends question_edit_form {

    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    protected function definition_inner($mform) {
        global $DB, $PAGE;

        // Number of accounts in dropdown.
        $mform->addElement('text', 'accountsindropdown',
            get_string('accountsindropdown', 'qtype_buchungssatz'), ['size' => 5]);
        $mform->setType('accountsindropdown', PARAM_INT);
        $mform->setDefault('accountsindropdown', 0);
        $mform->addHelpButton('accountsindropdown', 'accountsindropdown', 'qtype_buchungssatz');
        $mform->addRule('accountsindropdown', get_string('err_numeric', 'form'), 'numeric', null, 'client');

        // Number format selection.
        $numberformatoptions = [
            'de' => get_string('numberformat_de', 'qtype_buchungssatz'),
            'us' => get_string('numberformat_us', 'qtype_buchungssatz'),
        ];
        $mform->addElement('select', 'numberformat',
            get_string('numberformat', 'qtype_buchungssatz'), $numberformatoptions);
        $decsep = get_string('decsep', 'langconfig');
        $defaultformat = ($decsep === ',') ? 'de' : 'us';
        $mform->setDefault('numberformat', $defaultformat);
        $mform->addHelpButton('numberformat', 'numberformat', 'qtype_buchungssatz');

        // Determine the effective number format for rendering existing entries.
        $effectiveformat = !empty($this->question->options->numberformat)
            ? $this->question->options->numberformat
            : $defaultformat;

        $mform->addElement('text', 'currency_symbol',
            get_string('currency_symbol', 'qtype_buchungssatz'), ['size' => 5]);
        $mform->setType('currency_symbol', PARAM_TEXT);
        $mform->setDefault('currency_symbol', '€');

        // Decimal places.
        $mform->addElement('text', 'decimalplaces',
            get_string('decimalplaces', 'qtype_buchungssatz'), ['size' => 3]);
        $mform->setType('decimalplaces', PARAM_INT);
        $mform->setDefault('decimalplaces', 2);
        $mform->addHelpButton('decimalplaces', 'decimalplaces', 'qtype_buchungssatz');
        $mform->addRule('decimalplaces', get_string('err_numeric', 'form'), 'numeric', null, 'client');

        // Extra entry deduction.
        $mform->addElement('text', 'extraentrydeduction',
            get_string('extraentrydeduction', 'qtype_buchungssatz'), ['size' => 5]);
        $mform->setType('extraentrydeduction', PARAM_FLOAT);
        $mform->addHelpButton('extraentrydeduction', 'extraentrydeduction', 'qtype_buchungssatz');
        $mform->addRule('extraentrydeduction', get_string('err_numeric', 'form'), 'numeric', null, 'client');

        // All-or-nothing grading checkbox.
        $mform->addElement('advcheckbox', 'allornothinggrading',
            get_string('allornothinggrading', 'qtype_buchungssatz'), null, null, [0, 1]);
        $mform->setDefault('allornothinggrading', 0);
        $mform->addHelpButton('allornothinggrading', 'allornothinggrading', 'qtype_buchungssatz');

        // Chart of accounts selection (last item in general section).
        $charts = $this->get_available_charts();
        $mform->addElement('select', 'chartofaccountsid',
            get_string('chartofaccounts', 'qtype_buchungssatz'), $charts);
        $mform->setType('chartofaccountsid', PARAM_INT);
        $mform->addHelpButton('chartofaccountsid', 'chartofaccounts', 'qtype_buchungssatz');
        $mform->addRule('chartofaccountsid', get_string('err_chartrequired', 'qtype_buchungssatz'),
            'nonzero', null, 'client');

        // Link to manage charts of accounts.
        $coursecontext = $this->context->get_course_context(false);
        if ($coursecontext) {
            $manageurl = new moodle_url('/question/type/buchungssatz/manage_charts.php',
                ['courseid' => $coursecontext->instanceid, 'returnurl' => qualified_me()]);
            $mform->addElement('static', 'managecharts_link', '',
                html_writer::link($manageurl, get_string('managecharts', 'qtype_buchungssatz')));
        }

        // Correct answer entries section.
        $mform->addElement('header', 'answerhdr', get_string('correctanswer', 'qtype_buchungssatz'));
        $mform->setExpanded('answerhdr', true);

        // Get all accounts for dropdowns.
        $allaccounts = $this->get_all_accounts_by_chart();

        // Get current chart ID.
        $currentchartid = 0;
        if (!empty($this->question->options->chartofaccountsid)) {
            $currentchartid = (int)$this->question->options->chartofaccountsid;
        } else if (!empty($this->question->chartofaccountsid)) {
            $currentchartid = (int)$this->question->chartofaccountsid;
        }

        // Load from database if we have a question ID.
        if (!empty($this->question->id)) {
            $options = $DB->get_record('qtype_buchungssatz_options', ['questionid' => $this->question->id]);
            if ($options && !$currentchartid) {
                $currentchartid = (int)$options->chartofaccountsid;
            }
        }

        // Build account options for select elements - only include accounts from the selected chart.
        $sollaccountoptions = ['' => get_string('noaccountselected', 'qtype_buchungssatz')];
        $habenaccountoptions = ['' => get_string('selectaccount', 'qtype_buchungssatz')];
        if ($currentchartid > 0 && isset($allaccounts[$currentchartid])) {
            $sollaccountoptions = $sollaccountoptions + $allaccounts[$currentchartid];
            $habenaccountoptions = $habenaccountoptions + $allaccounts[$currentchartid];
        }

        // Load existing entries for pre-populating the form.
        $existingentries = [];
        if (!empty($this->question->options->entries)) {
            $existingentries = array_values($this->question->options->entries);
        }
        // Default to 2 entries for new questions, otherwise use the number of existing entries.
        $entrycount = count($existingentries) > 0 ? count($existingentries) : 2;

        // Build the table HTML with form elements.
        $tablehtml = $this->build_entries_table($sollaccountoptions, $habenaccountoptions,
            $entrycount, $existingentries, $effectiveformat);
        $mform->addElement('html', $tablehtml);

        // Hidden fields for form element registration.
        // These fields receive their values from the visible form fields via JavaScript on submit.
        for ($i = 0; $i < 20; $i++) {
            $mform->addElement('hidden', "sollkonto[$i]", '');
            $mform->setType("sollkonto[$i]", PARAM_TEXT);
            $mform->addElement('hidden', "sollbetrag[$i]", '');
            $mform->setType("sollbetrag[$i]", PARAM_RAW);
            $mform->addElement('hidden', "habenkonto[$i]", '');
            $mform->setType("habenkonto[$i]", PARAM_TEXT);
            $mform->addElement('hidden', "habenbetrag[$i]", '');
            $mform->setType("habenbetrag[$i]", PARAM_RAW);
            $mform->addElement('hidden', "weight_sollkonto[$i]", '1');
            $mform->setType("weight_sollkonto[$i]", PARAM_INT);
            $mform->addElement('hidden', "weight_sollbetrag[$i]", '1');
            $mform->setType("weight_sollbetrag[$i]", PARAM_INT);
            $mform->addElement('hidden', "weight_habenkonto[$i]", '1');
            $mform->setType("weight_habenkonto[$i]", PARAM_INT);
            $mform->addElement('hidden', "weight_habenbetrag[$i]", '1');
            $mform->setType("weight_habenbetrag[$i]", PARAM_INT);
        }

        // Add JavaScript for dynamic functionality.
        // Pass data via script tag to avoid js_call_amd size limits and HTML encoding issues.
        // Determine course context ID for AJAX calls.
        $coursecontext = $this->context->get_course_context(false);
        $coursecontextid = $coursecontext ? $coursecontext->id : 0;

        $jsdata = [
            'accounts' => $allaccounts,
            'chartId' => $currentchartid,
            'entries' => $existingentries,
            'courseId' => $coursecontextid,
            'numberFormat' => $effectiveformat,
        ];
        $mform->addElement('html', '<script type="application/json" id="buchungssatz-editform-data">' .
            json_encode($jsdata, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>');

        $PAGE->requires->js_call_amd('qtype_buchungssatz/editform', 'init', []);

        // Add the standard "Multiple tries" section (penalty and hints).
        $this->add_interactive_settings();
    }

    /**
     * Build the entries table HTML.
     *
     * @param array $sollaccountoptions Options for debit account select.
     * @param array $habenaccountoptions Options for credit account select.
     * @param int $entrycount Number of entries to show.
     * @param array $existingentries Existing entry data.
     * @param string $numberformat The number format ('de' or 'us').
     * @return string The HTML for the entries table.
     */
    protected function build_entries_table(array $sollaccountoptions, array $habenaccountoptions,
            int $entrycount, array $existingentries, string $numberformat = 'de'): string {

        $perstr = get_string('per', 'qtype_buchungssatz');
        $anstr = get_string('an', 'qtype_buchungssatz');
        $sollstr = get_string('soll', 'qtype_buchungssatz');
        $habenstr = get_string('haben', 'qtype_buchungssatz');
        $kontostr = get_string('account', 'qtype_buchungssatz');
        $betragstr = get_string('amount', 'qtype_buchungssatz');
        $weightstr = get_string('weight', 'qtype_buchungssatz');
        $deletestr = get_string('deleteentry', 'qtype_buchungssatz');
        $addentrystr = get_string('addentry', 'qtype_buchungssatz');

        $html = '<table class="table table-bordered buchungssatz-edit-table" id="buchungssatz-entries-table">';

        // Header row 1.
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th class="buchungssatz-edit-label"></th>';
        $html .= '<th colspan="2" class="text-center">' . $sollstr . '</th>';
        $html .= '<th class="buchungssatz-edit-label"></th>';
        $html .= '<th colspan="2" class="text-center">' . $habenstr . '</th>';
        $html .= '<th class="buchungssatz-edit-actions"></th>';
        $html .= '</tr>';

        // Header row 2.
        $html .= '<tr>';
        $html .= '<th class="buchungssatz-edit-label">' . $perstr . '</th>';
        $html .= '<th>' . $kontostr . '</th>';
        $html .= '<th>' . $betragstr . '</th>';
        $html .= '<th class="buchungssatz-edit-label">' . $anstr . '</th>';
        $html .= '<th>' . $kontostr . '</th>';
        $html .= '<th>' . $betragstr . '</th>';
        $html .= '<th></th>';
        $html .= '</tr>';
        $html .= '</thead>';

        $html .= '<tbody id="buchungssatz-entries-body">';

        // Generate rows for each entry.
        for ($i = 0; $i < $entrycount; $i++) {
            $entry = $existingentries[$i] ?? null;
            $html .= $this->build_entry_rows($i, $sollaccountoptions, $habenaccountoptions, $entry, $i === 0, $numberformat);
        }

        $html .= '</tbody>';

        // Add entry buttons as a table footer row, positioned below the account columns.
        $adddebitentrystr = get_string('adddebitentry', 'qtype_buchungssatz');
        $addcreditentrystr = get_string('addcreditentry', 'qtype_buchungssatz');

        $html .= '<tfoot class="buchungssatz-controls-footer">';
        $html .= '<tr>';
        $html .= '<td></td>'; // Per label column.
        $html .= '<td>';
        $html .= '<button type="button" class="btn btn-secondary btn-sm buchungssatz-add-debit-entry" id="buchungssatz-add-debit-entry">';
        $html .= '+ ' . $adddebitentrystr;
        $html .= '</button>';
        $html .= '</td>';
        $html .= '<td></td>'; // Soll Amount column.
        $html .= '<td></td>'; // an label column.
        $html .= '<td>';
        $html .= '<button type="button" class="btn btn-secondary btn-sm buchungssatz-add-credit-entry" id="buchungssatz-add-credit-entry">';
        $html .= '+ ' . $addcreditentrystr;
        $html .= '</button>';
        $html .= '</td>';
        $html .= '<td></td>'; // Haben Amount column.
        $html .= '<td></td>'; // Actions column.
        $html .= '</tr>';
        $html .= '</tfoot>';

        $html .= '</table>';

        // Template for new rows (hidden, used by JavaScript).
        $html .= '<template id="buchungssatz-entry-template">';
        $html .= $this->build_entry_rows('__INDEX__', $sollaccountoptions, $habenaccountoptions, null, false, $numberformat);
        $html .= '</template>';

        return $html;
    }

    /**
     * Build HTML for a single entry (data row + weight row).
     *
     * @param int|string $index Entry index or placeholder.
     * @param array $sollaccountoptions Options for debit account select.
     * @param array $habenaccountoptions Options for credit account select.
     * @param object|null $entry Existing entry data.
     * @param bool $isfirst Whether this is the first entry.
     * @param string $numberformat The number format ('de' or 'us').
     * @return string The HTML for the entry rows.
     */
    protected function build_entry_rows($index, array $sollaccountoptions, array $habenaccountoptions,
            $entry, bool $isfirst, string $numberformat = 'de'): string {

        $perstr = get_string('per', 'qtype_buchungssatz');
        $anstr = get_string('an', 'qtype_buchungssatz');
        $weightstr = get_string('weight', 'qtype_buchungssatz');

        // Get existing values.
        $sollkonto = $entry->sollkonto ?? '';
        $sollbetragraw = $entry->sollbetrag ?? '';
        $habenkonto = $entry->habenkonto ?? '';
        $habenbetragraw = $entry->habenbetrag ?? '';
        $weightsollkonto = (int)($entry->weight_sollkonto ?? 1);
        $weightsollbetrag = (int)($entry->weight_sollbetrag ?? 1);
        $weighthabenkonto = (int)($entry->weight_habenkonto ?? 1);
        $weighthabenbetrag = (int)($entry->weight_habenbetrag ?? 1);

        // Determine entry type and hidden classes.
        $entrytype = \qtype_buchungssatz\entry_helper::determine_entry_type($sollkonto, $habenkonto);
        $hidden_classes = \qtype_buchungssatz\entry_helper::get_hidden_classes($entrytype);
        $debithidden = $hidden_classes['debit'];
        $credithidden = $hidden_classes['credit'];

        // Format amounts with 2 decimal places for display using the question's number format.
        $sollbetrag = $this->format_amount_for_edit((float)$sollbetragraw, $numberformat);
        $habenbetrag = $this->format_amount_for_edit((float)$habenbetragraw, $numberformat);

        // Build select options HTML for sollkonto.
        $sollselecthtml = '<select name="sollkonto_display[' . $index . ']" class="form-control buchungssatz-sollkonto" data-index="' . $index . '">';
        $sollselecthtml .= \qtype_buchungssatz\entry_helper::build_account_options($sollaccountoptions, (string)$sollkonto, '');
        $sollselecthtml .= '</select>';

        // Build select options HTML for habenkonto.
        $habenselecthtml = '<select name="habenkonto_display[' . $index . ']" class="form-control buchungssatz-habenkonto" data-index="' . $index . '">';
        $habenselecthtml .= \qtype_buchungssatz\entry_helper::build_account_options($habenaccountoptions, (string)$habenkonto, '');
        $habenselecthtml .= '</select>';

        $html = '';

        // Data row.
        $html .= '<tr class="buchungssatz-entry-row" data-entry-index="' . $index . '" data-entry-type="' . $entrytype . '">';
        $html .= '<td class="buchungssatz-edit-label' . $debithidden . '">' . ($isfirst ? $perstr : '') . '</td>';
        $html .= '<td class="buchungssatz-edit-data' . $debithidden . '">' . $sollselecthtml . '</td>';
        $html .= '<td class="buchungssatz-edit-data' . $debithidden . '">';
        $placeholder = ($numberformat === 'us') ? '0.00' : '0,00';
        $html .= '<input type="text" name="sollbetrag_display[' . $index . ']" value="' . s($sollbetrag) . '" ';
        $html .= 'class="form-control buchungssatz-sollbetrag" data-index="' . $index . '" placeholder="' . $placeholder . '">';
        $html .= '</td>';
        $html .= '<td class="buchungssatz-edit-label' . $debithidden . '">';
        $html .= \qtype_buchungssatz\entry_helper::render_delete_button('debit', $index, 'data-index');
        $html .= '</td>';
        $html .= '<td class="buchungssatz-edit-data' . $credithidden . '">' . $habenselecthtml . '</td>';
        $html .= '<td class="buchungssatz-edit-data' . $credithidden . '">';
        $html .= '<input type="text" name="habenbetrag_display[' . $index . ']" value="' . s($habenbetrag) . '" ';
        $html .= 'class="form-control buchungssatz-habenbetrag" data-index="' . $index . '" placeholder="' . $placeholder . '">';
        $html .= '</td>';
        $html .= '<td class="buchungssatz-edit-actions' . $credithidden . '">';
        $html .= \qtype_buchungssatz\entry_helper::render_delete_button('credit', $index, 'data-index');
        $html .= '</td>';
        $html .= '</tr>';

        // Weight row.
        $html .= '<tr class="buchungssatz-weight-row" data-entry-index="' . $index . '">';
        $html .= '<td class="' . trim($debithidden) . '"></td>';
        $html .= '<td class="buchungssatz-weight-cell' . $debithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select('weight_sollkonto_display[' . $index . ']', $weightsollkonto, $index, 'sollkonto');
        $html .= '</td>';
        $html .= '<td class="buchungssatz-weight-cell' . $debithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select('weight_sollbetrag_display[' . $index . ']', $weightsollbetrag, $index, 'sollbetrag');
        $html .= '</td>';
        $html .= '<td class="' . trim($debithidden) . '"></td>';
        $html .= '<td class="buchungssatz-weight-cell' . $credithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select('weight_habenkonto_display[' . $index . ']', $weighthabenkonto, $index, 'habenkonto');
        $html .= '</td>';
        $html .= '<td class="buchungssatz-weight-cell' . $credithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select('weight_habenbetrag_display[' . $index . ']', $weighthabenbetrag, $index, 'habenbetrag');
        $html .= '</td>';
        $html .= '<td class="' . trim($credithidden) . '"></td>';
        $html .= '</tr>';

        return $html;
    }

    /**
     * Build a weight select dropdown with options 1, 2, and 3.
     *
     * @param string $name The form field name.
     * @param int $selectedvalue The currently selected value (1, 2, or 3).
     * @param int|string $index The entry index.
     * @param string $field The field name (sollkonto, sollbetrag, habenkonto, habenbetrag).
     * @return string The HTML for the select dropdown.
     */
    protected function build_weight_select(string $name, int $selectedvalue, $index, string $field): string {
        // Ensure the selected value is within the valid range (1-3), default to 1.
        if ($selectedvalue < 1 || $selectedvalue > 3) {
            $selectedvalue = 1;
        }

        $html = '<select name="' . $name . '" class="form-control buchungssatz-weight" ';
        $html .= 'data-index="' . $index . '" data-field="' . $field . '" style="width: auto; display: inline-block;">';

        for ($i = 1; $i <= 3; $i++) {
            $selected = ($i === $selectedvalue) ? ' selected' : '';
            $html .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Get all accounts grouped by chart ID.
     *
     * @return array The accounts grouped by chart ID.
     */
    protected function get_all_accounts_by_chart(): array {
        global $DB;

        $result = [];
        $charts = $this->get_available_charts();

        foreach (array_keys($charts) as $chartid) {
            if ($chartid == 0) {
                continue;
            }
            $accounts = $DB->get_records('qtype_buchungssatz_accounts',
                ['chartid' => $chartid], 'sortorder, accountname');
            $result[$chartid] = [];
            foreach ($accounts as $account) {
                $result[$chartid][$account->accountname] = $account->accountname;
            }
        }

        return $result;
    }

    /**
     * Get available charts of accounts.
     *
     * @return array The available charts keyed by ID.
     */
    protected function get_available_charts(): array {
        global $DB;

        $charts = [0 => get_string('nochartselected', 'qtype_buchungssatz')];

        $coursecontext = $this->context->get_course_context(false);
        if ($coursecontext) {
            $records = $DB->get_records('qtype_buchungssatz_charts',
                ['contextid' => $coursecontext->id], 'name ASC');
            foreach ($records as $record) {
                $charts[$record->id] = $record->name;
            }
        }

        return $charts;
    }

    /**
     * Format an amount for display in the edit form.
     *
     * Uses the question's number format with 2 decimal places and thousand separators.
     * Returns empty string for zero/empty values.
     *
     * @param float $amount The amount to format.
     * @param string $numberformat The number format ('de' or 'us').
     * @return string The formatted amount or empty string.
     */
    protected function format_amount_for_edit(float $amount, string $numberformat = 'de'): string {
        if (abs($amount) < 0.001) {
            return '';
        }
        if ($numberformat === 'us') {
            return number_format($amount, 2, '.', ',');
        }
        return number_format($amount, 2, ',', '.');
    }

    /**
     * Preprocess the question data for the form.
     *
     * @param object $question The question data.
     * @return object The preprocessed question data.
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        // Load options if they exist.
        if (!empty($question->options)) {
            $question->chartofaccountsid = $question->options->chartofaccountsid ?? 0;
            $question->accountsindropdown = $question->options->accountsindropdown ?? 0;
            $question->numberformat = $question->options->numberformat ?? 'de';
            $question->currency_symbol = $question->options->currency_symbol ?? '€';
            $question->decimalplaces = $question->options->decimalplaces ?? 2;
            $question->extraentrydeduction = $question->options->extraentrydeduction ?? null;
            $question->allornothinggrading = $question->options->allornothinggrading ?? 0;

            // Load entries into array form fields.
            if (!empty($question->options->entries)) {
                $i = 0;
                foreach ($question->options->entries as $entry) {
                    $question->sollkonto[$i] = $entry->sollkonto;
                    $question->sollbetrag[$i] = $entry->sollbetrag;
                    $question->habenkonto[$i] = $entry->habenkonto;
                    $question->habenbetrag[$i] = $entry->habenbetrag;
                    $question->weight_sollkonto[$i] = $entry->weight_sollkonto ?? 1;
                    $question->weight_sollbetrag[$i] = $entry->weight_sollbetrag ?? 1;
                    $question->weight_habenkonto[$i] = $entry->weight_habenkonto ?? 1;
                    $question->weight_habenbetrag[$i] = $entry->weight_habenbetrag ?? 1;
                    $i++;
                }
            }
        }

        return $question;
    }

    /**
     * Perform validation on the form data.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @return array The validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Validate chart of accounts is selected.
        $chartofaccountsid = (int) ($data['chartofaccountsid'] ?? 0);
        if ($chartofaccountsid === 0) {
            $errors['chartofaccountsid'] = get_string('err_chartrequired', 'qtype_buchungssatz');
        }

        // Validate accountsindropdown is not negative.
        $accountsindropdown = (int) ($data['accountsindropdown'] ?? 0);
        if ($accountsindropdown < 0) {
            $errors['accountsindropdown'] = get_string('err_accountsindropdown_negative', 'qtype_buchungssatz');
        }

        // Validate extraentrydeduction is between 0 and 100.
        $extraentrydeduction = $data['extraentrydeduction'] ?? '';
        if ($extraentrydeduction !== '' && $extraentrydeduction !== null) {
            $val = (float) $extraentrydeduction;
            if ($val < 0 || $val > 100) {
                $errors['extraentrydeduction'] = get_string('err_extraentrydeduction_range', 'qtype_buchungssatz');
            }
        }

        $hasentries = false;

        // Get all unique indices from both sollkonto and habenkonto arrays.
        $sollkontoarray = $data['sollkonto'] ?? [];
        $habenkontoarray = $data['habenkonto'] ?? [];
        $allindices = array_unique(array_merge(array_keys($sollkontoarray), array_keys($habenkontoarray)));

        foreach ($allindices as $i) {
            $sollkonto = trim($data['sollkonto'][$i] ?? '');
            $habenkonto = trim($data['habenkonto'][$i] ?? '');
            $sollbetragraw = trim($data['sollbetrag'][$i] ?? '');
            $habenbetragraw = trim($data['habenbetrag'][$i] ?? '');

            // Skip completely empty entries.
            if (empty($sollkonto) && $sollbetragraw === '' && empty($habenkonto) && $habenbetragraw === '') {
                continue;
            }

            $hasentries = true;

            // Debit amount is required if debit account is selected.
            if (!empty($sollkonto)) {
                if ($sollbetragraw === '') {
                    $errors["sollbetrag[$i]"] = get_string('err_sollbetragrequired', 'qtype_buchungssatz');
                } else if (floatval($sollbetragraw) < 0) {
                    $errors["sollbetrag[$i]"] = get_string('err_negativeamount', 'qtype_buchungssatz');
                }
            }

            // Credit amount is required if credit account is selected.
            if (!empty($habenkonto)) {
                if ($habenbetragraw === '') {
                    $errors["habenbetrag[$i]"] = get_string('err_habenamountrequired', 'qtype_buchungssatz');
                } else if (floatval($habenbetragraw) < 0) {
                    $errors["habenbetrag[$i]"] = get_string('err_negativeamount', 'qtype_buchungssatz');
                }
            }
        }

        if (!$hasentries) {
            $errors['sollkonto[0]'] = get_string('err_noentries', 'qtype_buchungssatz');
        }

        return $errors;
    }

    /**
     * Get the question type name.
     *
     * @return string The question type name.
     */
    public function qtype(): string {
        return 'buchungssatz';
    }
}
