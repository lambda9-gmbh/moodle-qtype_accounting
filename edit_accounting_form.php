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
 * Defines the editing form for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Buchungssatz question editing form definition.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_accounting_edit_form extends question_edit_form {
    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    protected function definition_inner($mform) {
        $defaultformat = $this->add_display_options_section($mform);
        $effectiveformat = !empty($this->question->options->numberformat)
            ? $this->question->options->numberformat
            : $defaultformat;

        $this->add_grading_section($mform);
        $this->add_chart_section($mform);
        $this->add_entries_section($mform, $effectiveformat);

        // Add the standard "Multiple tries" section (penalty and hints).
        $this->add_interactive_settings();
    }

    /**
     * Add the display-options section: accountsindropdown + numberformat.
     *
     * @param \MoodleQuickForm $mform The form being built.
     * @return string The default number format ('de' or 'us'), so callers can derive the effective format.
     */
    protected function add_display_options_section($mform): string {
        $mform->addElement(
            'text',
            'accountsindropdown',
            get_string('accountsindropdown', 'qtype_accounting'),
            ['size' => 5]
        );
        $mform->setType('accountsindropdown', PARAM_INT);
        $mform->setDefault('accountsindropdown', 0);
        $mform->addHelpButton('accountsindropdown', 'accountsindropdown', 'qtype_accounting');
        $mform->addRule('accountsindropdown', get_string('err_numeric', 'form'), 'numeric', null, 'client');

        $numberformatoptions = [
            'de' => get_string('numberformat_de', 'qtype_accounting'),
            'us' => get_string('numberformat_us', 'qtype_accounting'),
        ];
        $mform->addElement(
            'select',
            'numberformat',
            get_string('numberformat', 'qtype_accounting'),
            $numberformatoptions
        );
        $decsep = get_string('decsep', 'langconfig');
        $defaultformat = ($decsep === ',') ? 'de' : 'us';
        $mform->setDefault('numberformat', $defaultformat);
        $mform->addHelpButton('numberformat', 'numberformat', 'qtype_accounting');
        return $defaultformat;
    }

    /**
     * Add the grading-behaviour section: extra-entry deduction + all-or-nothing toggle.
     *
     * @param \MoodleQuickForm $mform The form being built.
     */
    protected function add_grading_section($mform): void {
        $deductionoptions = [
            '0.0' => get_string('none'),
            '1.0' => '100%',
            '0.9' => '90%',
            '0.8333333' => '83.33333%',
            '0.8' => '80%',
            '0.75' => '75%',
            '0.7' => '70%',
            '0.6666667' => '66.66667%',
            '0.6' => '60%',
            '0.5' => '50%',
            '0.4' => '40%',
            '0.3333333' => '33.33333%',
            '0.3' => '30%',
            '0.25' => '25%',
            '0.2' => '20%',
            '0.1666667' => '16.66667%',
            '0.1428571' => '14.28571%',
            '0.125' => '12.5%',
            '0.1111111' => '11.11111%',
            '0.1' => '10%',
            '0.05' => '5%',
        ];
        $mform->addElement(
            'select',
            'extraentrydeduction',
            get_string('extraentrydeduction', 'qtype_accounting'),
            $deductionoptions
        );
        $mform->setDefault('extraentrydeduction', '0.0');
        $mform->addHelpButton('extraentrydeduction', 'extraentrydeduction', 'qtype_accounting');

        $mform->addElement(
            'advcheckbox',
            'allornothinggrading',
            get_string('allornothinggrading', 'qtype_accounting'),
            null,
            null,
            [0, 1]
        );
        $mform->setDefault('allornothinggrading', 0);
        $mform->addHelpButton('allornothinggrading', 'allornothinggrading', 'qtype_accounting');
    }

    /**
     * Add the chart-of-accounts selector and the manage-charts link.
     *
     * @param \MoodleQuickForm $mform The form being built.
     */
    protected function add_chart_section($mform): void {
        $charts = $this->get_available_charts();
        $mform->addElement(
            'select',
            'chartofaccountsid',
            get_string('chartofaccounts', 'qtype_accounting'),
            $charts
        );
        $mform->setType('chartofaccountsid', PARAM_INT);
        $mform->addHelpButton('chartofaccountsid', 'chartofaccounts', 'qtype_accounting');

        $coursecontext = $this->context->get_course_context(false);
        if (!$coursecontext) {
            return;
        }
        $manageurl = new moodle_url(
            '/question/type/accounting/manage_charts.php',
            ['courseid' => $coursecontext->instanceid, 'returnurl' => qualified_me()]
        );
        $mform->addElement(
            'static',
            'managecharts_link',
            '',
            html_writer::link($manageurl, get_string('managecharts', 'qtype_accounting'))
        );
    }

    /**
     * Add the correct-answer-entries section: the entries table, hidden form fields, and JS hookup.
     *
     * @param \MoodleQuickForm $mform The form being built.
     * @param string $effectiveformat The number format to render existing entries with ('de' or 'us').
     */
    protected function add_entries_section($mform, string $effectiveformat): void {
        global $DB, $PAGE;

        $mform->addElement('header', 'answerhdr', get_string('correctanswer', 'qtype_accounting'));
        $mform->setExpanded('answerhdr', true);
        $mform->addElement('static', 'balancevalidation', '', '');

        $allaccounts = $this->get_all_accounts_by_chart();
        $currentchartid = $this->resolve_current_chart_id($DB);

        // Build account options for select elements - only include accounts from the selected chart.
        $debitaccountoptions = ['' => get_string('noaccountselected', 'qtype_accounting')];
        $creditaccountoptions = ['' => get_string('selectaccount', 'qtype_accounting')];
        if ($currentchartid > 0 && isset($allaccounts[$currentchartid])) {
            $debitaccountoptions = $debitaccountoptions + $allaccounts[$currentchartid];
            $creditaccountoptions = $creditaccountoptions + $allaccounts[$currentchartid];
        }

        $existingentries = !empty($this->question->options->entries)
            ? array_values($this->question->options->entries)
            : [];
        // Default to 2 entries for new questions, otherwise use the number of existing entries.
        $entrycount = count($existingentries) > 0 ? count($existingentries) : 2;

        $mform->addElement('html', $this->build_entries_table(
            $debitaccountoptions,
            $creditaccountoptions,
            $entrycount,
            $existingentries,
            $effectiveformat
        ));

        // Hidden fields for form element registration; populated via JS on submit.
        for ($i = 0; $i < 20; $i++) {
            $mform->addElement('hidden', "debitaccount[$i]", '');
            $mform->setType("debitaccount[$i]", PARAM_RAW);
            $mform->addElement('hidden', "debitamount[$i]", '');
            $mform->setType("debitamount[$i]", PARAM_RAW);
            $mform->addElement('hidden', "creditaccount[$i]", '');
            $mform->setType("creditaccount[$i]", PARAM_RAW);
            $mform->addElement('hidden', "creditamount[$i]", '');
            $mform->setType("creditamount[$i]", PARAM_RAW);
            $mform->addElement('hidden', "weight_debitaccount[$i]", '1');
            $mform->setType("weight_debitaccount[$i]", PARAM_INT);
            $mform->addElement('hidden', "weight_debitamount[$i]", '1');
            $mform->setType("weight_debitamount[$i]", PARAM_INT);
            $mform->addElement('hidden', "weight_creditaccount[$i]", '1');
            $mform->setType("weight_creditaccount[$i]", PARAM_INT);
            $mform->addElement('hidden', "weight_creditamount[$i]", '1');
            $mform->setType("weight_creditamount[$i]", PARAM_INT);
        }

        $coursecontext = $this->context->get_course_context(false);
        $coursecontextid = $coursecontext ? $coursecontext->id : 0;
        $jsdata = [
            'accounts' => $allaccounts,
            'chartId' => $currentchartid,
            'entries' => $existingentries,
            'courseId' => $coursecontextid,
            'numberFormat' => $effectiveformat,
        ];
        $mform->addElement('html', '<script type="application/json" id="qtype_accounting-editform-data">' .
            json_encode($jsdata, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>');

        $PAGE->requires->string_for_js('err_balancemismatch', 'qtype_accounting');
        $PAGE->requires->string_for_js('err_debitaccountrequired', 'qtype_accounting');
        $PAGE->requires->string_for_js('err_creditaccountrequired', 'qtype_accounting');
        $PAGE->requires->js_call_amd('qtype_accounting/editform', 'init', []);
    }

    /**
     * Resolve the current chart-of-accounts ID for the question being edited.
     *
     * Checks $this->question->options, then $this->question->chartofaccountsid, then the DB row.
     *
     * @param \moodle_database $db The Moodle DB handle.
     * @return int The resolved chart ID, or 0 if none.
     */
    protected function resolve_current_chart_id($db): int {
        if (!empty($this->question->options->chartofaccountsid)) {
            return (int)$this->question->options->chartofaccountsid;
        }
        if (!empty($this->question->chartofaccountsid)) {
            return (int)$this->question->chartofaccountsid;
        }
        if (!empty($this->question->id)) {
            $options = $db->get_record('qtype_accounting_options', ['questionid' => $this->question->id]);
            if ($options) {
                return (int)$options->chartofaccountsid;
            }
        }
        return 0;
    }

    /**
     * Build the entries table HTML.
     *
     * @param array $debitaccountoptions Options for debit account select.
     * @param array $creditaccountoptions Options for credit account select.
     * @param int $entrycount Number of entries to show.
     * @param array $existingentries Existing entry data.
     * @param string $numberformat The number format ('de' or 'us').
     * @return string The HTML for the entries table.
     */
    protected function build_entries_table(
        array $debitaccountoptions,
        array $creditaccountoptions,
        int $entrycount,
        array $existingentries,
        string $numberformat = 'de'
    ): string {
        return (new \qtype_accounting\entries_table_builder())->build_table(
            $debitaccountoptions,
            $creditaccountoptions,
            $entrycount,
            $existingentries,
            $numberformat
        );
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
            $accounts = $DB->get_records(
                'qtype_accounting_accounts',
                ['chartid' => $chartid],
                'accountname'
            );
            $result[$chartid] = [];
            foreach ($accounts as $account) {
                $result[$chartid][$account->id] = $account->accountname;
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

        $charts = [0 => get_string('nochartselected', 'qtype_accounting')];

        $coursecontext = $this->context->get_course_context(false);
        if ($coursecontext) {
            $records = $DB->get_records(
                'qtype_accounting_charts',
                ['contextid' => $coursecontext->id],
                'name ASC'
            );
            foreach ($records as $record) {
                $charts[$record->id] = $record->name;
            }
        }

        return $charts;
    }

    /**
     * Preprocess the question data for the form.
     *
     * @param object $question The question data.
     * @return object The preprocessed question data.
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        if (!empty($question->options)) {
            $this->populate_options_fields($question);
            $this->populate_entry_fields($question);
        }
        return $question;
    }

    /**
     * Copy the options-table fields onto the question object for the form.
     *
     * Also normalises extraentrydeduction to the closest select-option key so the dropdown
     * shows the right item when the DB stores a slightly different float.
     *
     * @param object $question The question being preprocessed (modified in place).
     */
    protected function populate_options_fields(object $question): void {
        $question->chartofaccountsid = $question->options->chartofaccountsid ?? 0;
        $question->accountsindropdown = $question->options->accountsindropdown ?? 0;
        $question->numberformat = $question->options->numberformat ?? 'de';
        $question->extraentrydeduction = $this->closest_deduction_key(
            $question->options->extraentrydeduction ?? '0.0'
        );
        $question->allornothinggrading = $question->options->allornothinggrading ?? 0;
    }

    /**
     * Find the deduction-select option key whose float value is closest to a given value.
     *
     * @param string|float $value The DB value to match.
     * @return string The closest matching select-option key.
     */
    protected function closest_deduction_key($value): string {
        $deductionkeys = ['0.0', '1.0', '0.9', '0.8333333', '0.8', '0.75', '0.7', '0.6666667',
            '0.6', '0.5', '0.4', '0.3333333', '0.3', '0.25', '0.2', '0.1666667',
            '0.1428571', '0.125', '0.1111111', '0.1', '0.05'];
        $bestkey = '0.0';
        $bestdiff = PHP_FLOAT_MAX;
        $dbval = (float)$value;
        foreach ($deductionkeys as $key) {
            $diff = abs($dbval - (float)$key);
            if ($diff < $bestdiff) {
                $bestdiff = $diff;
                $bestkey = $key;
            }
        }
        return $bestkey;
    }

    /**
     * Copy the saved entry rows onto the question object as the 8 form-array fields.
     *
     * @param object $question The question being preprocessed (modified in place).
     */
    protected function populate_entry_fields(object $question): void {
        if (empty($question->options->entries)) {
            return;
        }
        $i = 0;
        foreach ($question->options->entries as $entry) {
            $question->debitaccount[$i] = $entry->debitaccountid ?? '';
            $question->debitamount[$i] = $entry->debitamount;
            $question->creditaccount[$i] = $entry->creditaccountid ?? '';
            $question->creditamount[$i] = $entry->creditamount;
            $question->weight_debitaccount[$i] = $entry->weight_debitaccount ?? 1;
            $question->weight_debitamount[$i] = $entry->weight_debitamount ?? 1;
            $question->weight_creditaccount[$i] = $entry->weight_creditaccount ?? 1;
            $question->weight_creditamount[$i] = $entry->weight_creditamount ?? 1;
            $i++;
        }
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
        if ((int)($data['accountsindropdown'] ?? 0) < 0) {
            $errors['accountsindropdown'] = get_string('err_accountsindropdown_negative', 'qtype_accounting');
        }
        return (new \qtype_accounting\entry_validator())->validate($data, $errors);
    }

    /**
     * Get the question type name.
     *
     * @return string The question type name.
     */
    public function qtype(): string {
        return 'accounting';
    }
}
