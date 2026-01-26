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
        $mform->setDefault('numberformat', 'de');
        $mform->addHelpButton('numberformat', 'numberformat', 'qtype_buchungssatz');

        $mform->addElement('text', 'currency_symbol',
            get_string('currency_symbol', 'qtype_buchungssatz'), ['size' => 5]);
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

        // All-or-nothing grading checkbox.
        $mform->addElement('advcheckbox', 'allornothinggrading',
            get_string('allornothinggrading', 'qtype_buchungssatz'), null, null, [0, 1]);
        $mform->setDefault('allornothinggrading', 0);
        $mform->addHelpButton('allornothinggrading', 'allornothinggrading', 'qtype_buchungssatz');

        // Chart of accounts selection.
        $charts = $this->get_available_charts();
        $mform->addElement('select', 'chartofaccountsid',
            get_string('chartofaccounts', 'qtype_buchungssatz'), $charts);
        $mform->setType('chartofaccountsid', PARAM_INT);
        $mform->addHelpButton('chartofaccountsid', 'chartofaccounts', 'qtype_buchungssatz');

        // Link to manage charts.
        $manageurl = new moodle_url('/question/type/buchungssatz/manage_charts.php');
        $mform->addElement('static', 'managecharts_link', '',
            '<a href="' . $manageurl->out() . '" target="_blank" id="buchungssatz-manage-charts-link">' .
            get_string('managecharts', 'qtype_buchungssatz') . '</a>');

        // Correct answer entries section.
        $mform->addElement('header', 'answerhdr', get_string('correctanswer', 'qtype_buchungssatz'));
        $mform->setExpanded('answerhdr', true);

        // Get all accounts for dropdowns.
        $allaccounts = $this->get_all_accounts_by_chart();
        $sollaccountoptions = ['' => get_string('noaccountselected', 'qtype_buchungssatz')];
        $habenaccountoptions = ['' => get_string('selectaccount', 'qtype_buchungssatz')];

        // Get current chart and populate account options.
        // Try multiple sources for the chart ID since options might not be loaded yet.
        $currentchartid = 0;
        $existingentries = [];

        if (!empty($this->question->options->chartofaccountsid)) {
            $currentchartid = (int)$this->question->options->chartofaccountsid;
        } else if (!empty($this->question->chartofaccountsid)) {
            $currentchartid = (int)$this->question->chartofaccountsid;
        }

        // Always try to load from database if we have a question ID.
        if (!empty($this->question->id)) {
            $options = $DB->get_record('qtype_buchungssatz_options', ['questionid' => $this->question->id]);
            if ($options) {
                if (!$currentchartid) {
                    $currentchartid = (int)$options->chartofaccountsid;
                }
            }
            // Also load existing entries to pass to JavaScript for value restoration.
            $entries = $DB->get_records('qtype_buchungssatz_entries',
                ['questionid' => $this->question->id], 'sortorder ASC');
            foreach ($entries as $entry) {
                $existingentries[] = [
                    'sollkonto' => $entry->sollkonto,
                    'habenkonto' => $entry->habenkonto,
                ];
            }
        }

        // Include ALL accounts from ALL charts in the select options.
        // This is necessary because Moodle's form validation checks that submitted values
        // exist in the options list. JavaScript will filter the displayed options based on
        // the selected chart, but the underlying select needs all possible values as valid.
        foreach ($allaccounts as $chartaccounts) {
            $sollaccountoptions = $sollaccountoptions + $chartaccounts;
            $habenaccountoptions = $habenaccountoptions + $chartaccounts;
        }

        // Define the repeatable elements for entries.
        $repeatarray = [];
        $repeatarray[] = $mform->createElement('html', '<div class="buchungssatz-entry-group card mb-3 p-3">');
        $repeatheader = [];
        $repeatheader[] = $mform->createElement('submit', 'entry_delete', get_string('deleteentry', 'qtype_buchungssatz'), ['class' => 'delete_entry']);
        $repeatarray[] = $mform->createElement('group', 'entry_header', '<strong style="font-size: 1.25rem;">' . get_string('entry', 'qtype_buchungssatz') . '</strong>', $repeatheader, null, false);
        $repeatarray[] = $mform->createElement('select', 'sollkonto',
            get_string('soll', 'qtype_buchungssatz') . ' ' . get_string('account', 'qtype_buchungssatz'),
            $sollaccountoptions, ['class' => 'buchungssatz-sollkonto']);
        $repeatarray[] = $mform->createElement('text', 'sollbetrag',
            get_string('soll', 'qtype_buchungssatz') . ' ' . get_string('amount', 'qtype_buchungssatz'),
            ['size' => 15, 'placeholder' => '0.00', 'class' => 'buchungssatz-sollbetrag']);
        $repeatarray[] = $mform->createElement('select', 'habenkonto',
            get_string('haben', 'qtype_buchungssatz') . ' ' . get_string('account', 'qtype_buchungssatz'),
            $habenaccountoptions, ['class' => 'buchungssatz-habenkonto']);
        $repeatarray[] = $mform->createElement('text', 'habenbetrag',
            get_string('haben', 'qtype_buchungssatz') . ' ' . get_string('amount', 'qtype_buchungssatz'),
            ['size' => 15, 'placeholder' => '0.00', 'class' => 'buchungssatz-habenbetrag']);
        $gradegroup = [];
        $gradegroup[] = $mform->createElement('text', 'grade', '',
            ['size' => 5, 'class' => 'buchungssatz-grade', 'placeholder' => '0-100']);
        $gradegroup[] = $mform->createElement('html',
            '<button type="button" class="btn btn-outline-secondary btn-sm ml-2 buchungssatz-distribute-grades-btn">' .
            get_string('distributegradesequally', 'qtype_buchungssatz') . '</button>');
        $repeatarray[] = $mform->createElement('group', 'gradegroup',
            get_string('grade', 'qtype_buchungssatz'), $gradegroup, ' ', false);
        $repeatarray[] = $mform->createElement('textarea', 'explanation',
            get_string('explanation', 'qtype_buchungssatz'),
            ['rows' => 2, 'cols' => 50, 'class' => 'buchungssatz-explanation']);
        $repeatarray[] = $mform->createElement('html', '</div>');

        // Set up repeat options.
        $repeatoptions = [];
        $repeatoptions['sollkonto']['type'] = PARAM_TEXT;
        $repeatoptions['sollbetrag']['type'] = PARAM_RAW;
        $repeatoptions['habenkonto']['type'] = PARAM_TEXT;
        $repeatoptions['habenbetrag']['type'] = PARAM_RAW;
        $repeatoptions['grade']['type'] = PARAM_RAW;
        $repeatoptions['explanation']['type'] = PARAM_TEXT;

        // Determine how many entries to show initially.
        $repeatcount = 1;
        if (!empty($this->question->options->entries)) {
            $repeatcount = count($this->question->options->entries);
        }

        // Add the repeating elements.
        $this->repeat_elements(
            $repeatarray,
            $repeatcount,
            $repeatoptions,
            'entry_repeats',
            'entry_add',
            1,
            get_string('addentry', 'qtype_buchungssatz'),
            false,
            'entry_delete',
            true
        );

        // Add JavaScript for dynamic account dropdowns and other functionality.
        $this->add_entry_javascript($mform, $allaccounts, $currentchartid, $existingentries);
    }

    /**
     * Add JavaScript for entry field handling.
     *
     * @param MoodleQuickForm $mform The form being built.
     * @param array $allaccounts All accounts grouped by chart ID.
     * @param int $currentchartid The current chart ID.
     * @param array $existingentries Existing entry values for restoration.
     */
    protected function add_entry_javascript($mform, array $allaccounts, int $currentchartid, array $existingentries): void {
        global $PAGE;

        // Call the AMD module with the required data.
        $PAGE->requires->js_call_amd('qtype_buchungssatz/editform', 'init', [
            $allaccounts,
            $currentchartid,
            $existingentries,
        ]);
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
                ['chartid' => $chartid], 'sortorder, accountnumber');
            $result[$chartid] = [];
            foreach ($accounts as $account) {
                $result[$chartid][$account->accountnumber] = $account->accountnumber . ' - ' . $account->accountname;
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

        // Always include system context charts.
        $systemcontext = context_system::instance();
        $contextids = [$systemcontext->id];

        // Try to get the current context.
        try {
            $currentcontext = $this->context ?? context_system::instance();
            if ($currentcontext->id != $systemcontext->id) {
                $contextids[] = $currentcontext->id;
                // Also include parent contexts.
                $parentcontexts = $currentcontext->get_parent_context_ids();
                $contextids = array_merge($contextids, $parentcontexts);
            }
        } catch (Exception $e) {
            // Just use system context if there's an error.
        }

        // Remove duplicates.
        $contextids = array_unique($contextids);

        list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('qtype_buchungssatz_charts',
            "contextid $insql", $params, 'name ASC');

        foreach ($records as $record) {
            $charts[$record->id] = $record->name;
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
                $question->sollkonto = [];
                $question->sollbetrag = [];
                $question->habenkonto = [];
                $question->habenbetrag = [];
                $question->grade = [];
                $question->explanation = [];

                foreach ($question->options->entries as $entry) {
                    $question->sollkonto[] = $entry->sollkonto;
                    $question->sollbetrag[] = $entry->sollbetrag;
                    $question->habenkonto[] = $entry->habenkonto;
                    $question->habenbetrag[] = $entry->habenbetrag;
                    // Convert fraction (0-1) to grade percentage (0-100).
                    $question->grade[] = $entry->fraction * 100;
                    $question->explanation[] = $entry->explanation ?? '';
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

        // Validate accountsindropdown is not negative.
        $accountsindropdown = (int) ($data['accountsindropdown'] ?? 0);
        if ($accountsindropdown < 0) {
            $errors['accountsindropdown'] = get_string('err_accountsindropdown_negative', 'qtype_buchungssatz');
        }

        $hasentries = false;
        $totalgrade = 0;
        $validindices = [];

        // Iterate over actual array keys rather than using entry_repeats counter,
        // since indices may not be sequential if entries were deleted.
        $habenkontoarray = $data['habenkonto'] ?? [];
        foreach ($habenkontoarray as $i => $habenkonto) {
            $sollkonto = trim($data['sollkonto'][$i] ?? '');
            $habenkonto = trim($habenkonto ?? '');
            $sollbetragraw = trim($data['sollbetrag'][$i] ?? '');
            $habenbetragraw = trim($data['habenbetrag'][$i] ?? '');
            $graderaw = trim($data['grade'][$i] ?? '');
            $sollbetrag = floatval($sollbetragraw);
            $habenbetrag = floatval($habenbetragraw);
            $grade = floatval($graderaw);

            // Check if entry has any data filled in.
            $hasanydata = !empty($sollkonto) || $sollbetragraw !== '' || $habenbetragraw !== '' || $graderaw !== '';

            // Skip completely empty entries.
            if (!$hasanydata && empty($habenkonto)) {
                continue;
            }

            // Entry has some data - validate all required fields.
            $hasentries = true;
            $validindices[] = $i;

            // Credit account is always required.
            if (empty($habenkonto)) {
                $errors["habenkonto[$i]"] = get_string('err_habenrequired', 'qtype_buchungssatz');
            }

            // Credit amount is always required.
            if ($habenbetragraw === '') {
                $errors["habenbetrag[$i]"] = get_string('err_habenamountrequired', 'qtype_buchungssatz');
            } else if ($habenbetrag < 0) {
                $errors["habenbetrag[$i]"] = get_string('err_negativeamount', 'qtype_buchungssatz');
            }

            // Debit amount is required only if debit account is selected.
            if (!empty($sollkonto)) {
                if ($sollbetragraw === '') {
                    $errors["sollbetrag[$i]"] = get_string('err_sollbetragrequired', 'qtype_buchungssatz');
                } else if ($sollbetrag < 0) {
                    $errors["sollbetrag[$i]"] = get_string('err_negativeamount', 'qtype_buchungssatz');
                }
            }

            // Grade is required and must be between 0 and 100.
            if ($graderaw === '') {
                $errors["gradegroup[$i]"] = get_string('err_graderequired', 'qtype_buchungssatz');
            } else if ($grade < 0 || $grade > 100) {
                $errors["gradegroup[$i]"] = get_string('err_gradeinvalid', 'qtype_buchungssatz');
            } else {
                $totalgrade += $grade;
            }
        }

        if (!$hasentries) {
            $errors['habenkonto[0]'] = get_string('err_noentries', 'qtype_buchungssatz');
        } else if (abs($totalgrade - 100) > 0.01) {
            // All grades must sum to exactly 100% - show error on all grade fields.
            $errormsg = get_string('err_gradesumnotcomplete', 'qtype_buchungssatz', round($totalgrade, 2));
            foreach ($validindices as $idx) {
                $errors["gradegroup[$idx]"] = $errormsg;
            }
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
