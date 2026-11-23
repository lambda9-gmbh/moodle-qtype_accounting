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
    /** @var string[] Options for the points deduction per extra account in points mode. */
    protected const DEDUCTION_POINTS = ['0.0', '0.25', '0.5', '0.75', '1.0', '1.5', '2.0', '3.0'];

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
        $this->add_accounts_section($mform);
        $this->add_entries_section($mform, $effectiveformat);

        // Add the standard "Multiple tries" section (penalty and hints).
        $this->add_interactive_settings(true, true);
    }

    /**
     * Add the display-options section: accountsindropdown + numberformat + currency.
     *
     * @param \MoodleQuickForm $mform The form being built.
     * @return string The default number format ('de' or 'us'), so callers can derive the effective format.
     */
    protected function add_display_options_section($mform): string {
        if ((string)get_config('qtype_accounting', 'showaccountsindropdown') === '0') {
            // Hidden by the site setting; a saved value is kept, new questions show all accounts.
            $mform->addElement('hidden', 'accountsindropdown', get_string('all'));
        } else {
            $mform->addElement(
                'text',
                'accountsindropdown',
                get_string('accountsindropdown', 'qtype_accounting'),
                ['size' => 5, 'placeholder' => get_string('all')]
            );
            $mform->setDefault('accountsindropdown', get_string('all'));
            $mform->addHelpButton('accountsindropdown', 'accountsindropdown', 'qtype_accounting');
        }
        $mform->setType('accountsindropdown', PARAM_RAW_TRIMMED);

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

        $mform->addElement(
            'select',
            'currency',
            get_string('currency', 'qtype_accounting'),
            \qtype_accounting\currency_helper::get_options()
        );
        $mform->setDefault('currency', \qtype_accounting\currency_helper::get_default());
        $mform->addHelpButton('currency', 'currency', 'qtype_accounting');
        return $defaultformat;
    }

    /**
     * Add the grading-behaviour section: extra-entry deduction + all-or-nothing toggle.
     *
     * @param \MoodleQuickForm $mform The form being built.
     */
    protected function add_grading_section($mform): void {
        $this->add_grading_mode($mform);

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
        $mform->hideIf('extraentrydeduction', 'gradingmode', 'eq', \qtype_accounting\scorer::GRADINGMODE_POINTS);

        // In points mode the deduction is a number of points per extra account.
        $pointsoptions = [];
        foreach (self::DEDUCTION_POINTS as $key) {
            $pointsoptions[$key] = (float)$key == 0 ? get_string('none') :
                get_string('extraentrydeductionpoints_option', 'qtype_accounting', format_float((float)$key, -1));
        }
        $mform->addElement(
            'select',
            'extraentrydeductionpoints',
            get_string('extraentrydeductionpoints', 'qtype_accounting'),
            $pointsoptions
        );
        $mform->setDefault('extraentrydeductionpoints', '0.0');
        $mform->addHelpButton('extraentrydeductionpoints', 'extraentrydeductionpoints', 'qtype_accounting');
        $mform->hideIf('extraentrydeductionpoints', 'gradingmode', 'neq', \qtype_accounting\scorer::GRADINGMODE_POINTS);

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
     * Add the grading-mode selector (percent or points), preselected from the site default.
     *
     * In points mode the default mark is calculated from the correct answer when saving,
     * so the default mark field is replaced by an explanation.
     *
     * @param \MoodleQuickForm $mform The form being built.
     */
    protected function add_grading_mode($mform): void {
        $mform->addElement('select', 'gradingmode', get_string('gradingmode', 'qtype_accounting'), [
            \qtype_accounting\scorer::GRADINGMODE_PERCENT => get_string('gradingmode_percent', 'qtype_accounting'),
            \qtype_accounting\scorer::GRADINGMODE_POINTS => get_string('gradingmode_points', 'qtype_accounting'),
        ]);
        $mform->setDefault(
            'gradingmode',
            \qtype_accounting\scorer::normalise_grading_mode(get_config('qtype_accounting', 'defaultgradingmode') ?: null)
        );
        $mform->addHelpButton('gradingmode', 'gradingmode', 'qtype_accounting');

        $strict = (bool)get_config('qtype_accounting', 'strictpairs');
        $mform->addElement(
            'static',
            'gradingmode_pointsinfo',
            '',
            html_writer::div(
                get_string($strict ? 'gradingmode_points_info_strict' : 'gradingmode_points_info', 'qtype_accounting'),
                // The marker class lets styles.css hide the then irrelevant weight selectors.
                $strict ? 'qtype_accounting-strictpairs' : ''
            )
        );
        $mform->hideIf('gradingmode_pointsinfo', 'gradingmode', 'neq', \qtype_accounting\scorer::GRADINGMODE_POINTS);
        // The default mark is hidden by styles.css in points mode: hideIf would also disable it,
        // so it would not be submitted and fail its required rule.
    }

    /**
     * Add the question's account list and the controls to copy accounts from a template or another question.
     *
     * @param \MoodleQuickForm $mform The form being built.
     */
    protected function add_accounts_section($mform): void {
        $mform->addElement('textarea', 'accounts', get_string('accountlist', 'qtype_accounting'), [
            'rows' => 10,
            'cols' => 50,
            'id' => 'id_accounts',
            'class' => 'qtype_accounting-accounts',
        ]);
        $mform->setType('accounts', PARAM_TEXT);
        $mform->addHelpButton('accounts', 'accountlist', 'qtype_accounting');

        // Keys of the edited version, so unchanged accounts keep their key in the new version.
        $mform->addElement('hidden', 'accountkeys', json_encode($this->get_known_keys()));
        $mform->setType('accountkeys', PARAM_RAW);

        $sources = \qtype_accounting\question_accounts::get_copy_sources(
            $this->get_bank_context(),
            (int)($this->question->id ?? 0)
        );
        if (!$sources) {
            return;
        }
        // Registered before the button is created, so that it is marked to skip client-side validation.
        $mform->registerNoSubmitButton('copyaccounts');
        $mform->addGroup([
            $mform->createElement(
                'selectgroups',
                'copyaccountsfrom',
                get_string('copyaccountsfrom', 'qtype_accounting'),
                $sources,
                ['aria-label' => get_string('copyaccountsfrom', 'qtype_accounting')],
                true
            ),
            $mform->createElement('submit', 'copyaccounts', get_string('copyaccounts', 'qtype_accounting')),
        ], 'copyaccountsgroup', get_string('copyaccountsfrom', 'qtype_accounting'), ' ', false);
        $mform->setType('copyaccountsfrom', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('copyaccountsgroup', 'copyaccountsfrom', 'qtype_accounting');
    }

    /**
     * Get the account keys of the edited version keyed by account name.
     *
     * @return array Account keys keyed by name; empty for new questions.
     */
    protected function get_known_keys(): array {
        return \qtype_accounting\question_accounts::keys_by_name($this->question->options->accounts ?? []);
    }

    /**
     * Get the context of the question bank the question belongs to.
     *
     * @return \context|null The question category context, or null if unknown.
     */
    protected function get_bank_context(): ?\context {
        if (!empty($this->question->contextid)) {
            return \context::instance_by_id($this->question->contextid, IGNORE_MISSING) ?: null;
        }
        return $this->context ?? null;
    }

    /**
     * Get the accounts currently in the account list, as offered by the entry selects.
     *
     * @param string|null $list The submitted account list, or null to use the edited version's accounts.
     * @return array Account names keyed by account key, sorted by name.
     */
    protected function get_current_accounts(?string $list = null): array {
        if ($list === null) {
            $names = array_column($this->question->options->accounts ?? [], 'accountname');
        } else {
            $names = \qtype_accounting\question_accounts::parse_names($list);
        }
        $accounts = array_flip(\qtype_accounting\question_accounts::assign_keys($names, $this->get_known_keys()));
        asort($accounts, SORT_STRING);
        return $accounts;
    }

    /**
     * Add the correct-answer-entries section: the entries table, hidden form fields, and JS hookup.
     *
     * @param \MoodleQuickForm $mform The form being built.
     * @param string $effectiveformat The number format to render existing entries with ('de' or 'us').
     */
    protected function add_entries_section($mform, string $effectiveformat): void {
        global $PAGE;

        $mform->addElement('header', 'answerhdr', get_string('correctanswer', 'qtype_accounting'));
        $mform->setExpanded('answerhdr', true);
        $mform->addElement('static', 'balancevalidation', '', '');

        $accounts = $this->get_current_accounts();
        $accountoptions = ['' => get_string('selectaccount', 'qtype_accounting')] + $accounts;

        $existingentries = !empty($this->question->options->entries)
            ? array_values($this->question->options->entries)
            : [];
        // Default to 2 entries for new questions, otherwise use the number of existing entries.
        $entrycount = count($existingentries) > 0 ? count($existingentries) : 2;
        $requestedrows = optional_param('accounting_rowcount', $entrycount, PARAM_INT);
        $entrycount = max($entrycount, min(\qtype_accounting\entry_helper::MAX_ENTRIES, max(1, $requestedrows)));
        $mform->addElement('hidden', 'accounting_js', 0);
        $mform->setType('accounting_js', PARAM_BOOL);
        $mform->addElement('hidden', 'accounting_displayformat', $effectiveformat);
        $mform->setType('accounting_displayformat', PARAM_ALPHA);
        $mform->registerNoSubmitButton('refreshentries');
        $mform->addGroup([
            $mform->createElement('text', 'accounting_rowcount', get_string('entryrows', 'qtype_accounting'), [
                'aria-label' => get_string('entryrows', 'qtype_accounting'), 'size' => 4,
            ]),
            $mform->createElement('submit', 'refreshentries', get_string('refreshentries', 'qtype_accounting')),
        ], 'accountingservercontrols', get_string('entryrows', 'qtype_accounting'), ' ', false);
        $mform->setType('accounting_rowcount', PARAM_INT);
        $mform->setDefault('accounting_rowcount', $entrycount);

        $table = $mform->createElement('html', $this->build_entries_table(
            $accountoptions,
            $accountoptions,
            $entrycount,
            $existingentries,
            $effectiveformat
        ));
        $table->setName('entrytable');
        $mform->addElement($table);

        // Register every submitted index, including sparse indices after repeated add/delete operations.
        $indices = range(0, $entrycount - 1);
        foreach (['debitaccount', 'creditaccount', 'debitaccount_display', 'creditaccount_display'] as $field) {
            $indices = array_merge($indices, array_keys(optional_param_array($field, [], PARAM_INT)));
        }
        foreach (array_unique($indices) as $i) {
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
            $fields = ['debitaccount', 'debitamount', 'creditaccount', 'creditamount',
                'weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount'];
            foreach ($fields as $field) {
                // Without JavaScript, submit display controls only; the fallback normalises them.
                $mform->getElement($field . '[' . $i . ']')->updateAttributes(['disabled' => 'disabled']);
            }
        }

        $jsdata = [
            'accountKeys' => (object)$this->get_known_keys(),
            'entries' => $existingentries,
            'numberFormat' => $effectiveformat,
            'maxEntries' => max(\qtype_accounting\entry_helper::MAX_ENTRIES, $entrycount),
        ];
        $config = $mform->createElement('html', '<script type="application/json" id="qtype_accounting-editform-data">' .
            json_encode($jsdata, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>');
        $config->setName('entryconfig');
        $mform->addElement($config);

        $PAGE->requires->string_for_js('err_balancemismatch', 'qtype_accounting');
        $PAGE->requires->string_for_js('err_debitaccountrequired', 'qtype_accounting');
        $PAGE->requires->string_for_js('err_creditaccountrequired', 'qtype_accounting');
        // Preloaded so that core/str resolves them without a request; validation runs synchronously on submit.
        $PAGE->requires->strings_for_js([
            'err_accountunavailable', 'err_debitaccountrequired', 'err_creditaccountrequired',
            'err_debitamountrequired', 'err_creditamountrequired', 'err_balancemismatch',
        ], 'qtype_accounting');
        $PAGE->requires->js_call_amd('qtype_accounting/editform', 'init', []);
    }

    /**
     * Restore the custom controls after QuickForm has processed a submission.
     *
     * Unlike registered form elements, raw HTML does not retain submitted values.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        if (!$mform->isSubmitted()) {
            return;
        }
        $data = $mform->getSubmitValues();
        if (!empty($data['copyaccounts']) && !empty($data['copyaccountsfrom'])) {
            $names = \qtype_accounting\question_accounts::get_source_names(
                (string)$data['copyaccountsfrom'],
                $this->get_bank_context()
            );
            if ($names) {
                $data['accounts'] = implode("\n", $names);
                // The copied list replaces the submitted one in the redisplayed form.
                $mform->setConstant('accounts', $data['accounts']);
            }
        }
        $this->update_submitted_entries($data);
    }

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore -- Moodle lifecycle API.
    /**
     * Support ordinary HTML submissions when JavaScript is disabled or fails to load.
     *
     * @param string $method Form submission method.
     */
    public function _process_submission($method) {
        parent::_process_submission($method);
        $data = $this->_form->getSubmitValues();
        if (!$data) {
            return;
        }
        $accountcount = trim((string)($data['accountsindropdown'] ?? ''));
        if (
            $accountcount === '' || preg_match('/^-?0+$/D', $accountcount) ||
            strcasecmp($accountcount, get_string('all')) === 0
        ) {
            $data['accountsindropdown'] = get_string('all');
            $this->_form->updateSubmission($data, $_FILES);
        }
        if (!empty($data['accounting_js']) || !isset($data['debitaccount_display'])) {
            return;
        }
        $format = $data['accounting_displayformat'] ?? $data['numberformat'] ?? 'de';
        foreach (['debit', 'credit'] as $side) {
            $data[$side . 'account'] = $data[$side . 'account_display'] ?? [];
            $data[$side . 'amount'] = array_map(static function ($value) use ($format) {
                return trim($value) === '' ? '' : \qtype_accounting\amount_helper::decimal_amount(
                    \qtype_accounting\amount_helper::parse_cents($value, $format)
                );
            }, $data[$side . 'amount_display'] ?? []);
            foreach (['account', 'amount'] as $field) {
                $name = 'weight_' . $side . $field;
                $data[$name] = $data[$name . '_display'] ?? [];
            }
        }
        $data['accounting_displayformat'] = $data['numberformat'] ?? 'de';
        $this->_form->updateSubmission($data, $_FILES);
    }
    // phpcs:enable PSR2.Methods.MethodDeclaration.Underscore

    /**
     * Render submitted controls with field errors using Moodle's normal error styling.
     *
     * @param array $data Submitted values.
     * @param array $errors Validation errors keyed by field name.
     */
    protected function update_submitted_entries(array $data, array $errors = []): void {
        $mform = $this->_form;
        $entries = (new \qtype_accounting\entries_table_builder())->get_submitted_entries($data, $errors);
        $format = $data['numberformat'] ?? 'de';
        $accounts = $this->get_current_accounts((string)($data['accounts'] ?? ''));
        $mform->getElement('entrytable')->setValue($this->build_entries_table(
            ['' => get_string('selectaccount', 'qtype_accounting')] + $accounts,
            ['' => get_string('selectaccount', 'qtype_accounting')] + $accounts,
            max(1, count($entries), min(\qtype_accounting\entry_helper::MAX_ENTRIES, (int)($data['accounting_rowcount'] ?? 0))),
            $entries,
            $format
        ));
        $config = [
            'accountKeys' => (object)$this->get_known_keys(),
            'entries' => $entries,
            'numberFormat' => $format,
            'maxEntries' => max(\qtype_accounting\entry_helper::MAX_ENTRIES, count($entries)),
        ];
        $mform->getElement('entryconfig')->setValue(
            '<script type="application/json" id="qtype_accounting-editform-data">' .
            json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'
        );
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
     * Preprocess the question data for the form.
     *
     * @param object $question The question data.
     * @return object The preprocessed question data.
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_hints($question, true, true);
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
        $question->accounts = implode("\n", array_column($question->options->accounts ?? [], 'accountname'));
        $question->accountsindropdown = $question->options->accountsindropdown ?? 0;
        if ((int)$question->accountsindropdown === 0) {
            $question->accountsindropdown = get_string('all');
        }
        $question->numberformat = $question->options->numberformat ?? 'de';
        $question->currency = \qtype_accounting\currency_helper::normalise($question->options->currency ?? null);
        $deduction = $question->options->extraentrydeduction ?? '0.0';
        $points = ($question->options->gradingmode ?? '') === \qtype_accounting\scorer::GRADINGMODE_POINTS;
        // The stored deduction is a fraction in percent mode and a number of points in points mode.
        $question->extraentrydeduction = $points ? '0.0' : $this->closest_deduction_key($deduction);
        $question->extraentrydeductionpoints = $points ? $this->closest_key($deduction, self::DEDUCTION_POINTS) : '0.0';
        $question->allornothinggrading = $question->options->allornothinggrading ?? 0;
        if (!empty($question->options->gradingmode)) {
            $question->gradingmode = $question->options->gradingmode;
        }
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
        return $this->closest_key($value, $deductionkeys);
    }

    /**
     * Find the select-option key whose float value is closest to a given value.
     *
     * @param string|float $value The DB value to match.
     * @param string[] $deductionkeys The select-option keys.
     * @return string The closest matching key.
     */
    protected function closest_key($value, array $deductionkeys): string {
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
        // Saved entries are initial defaults only. On submission, absent indices
        // represent deleted rows and must not be restored by QuickForm defaults.
        if ($this->_form->isSubmitted() || empty($question->options->entries)) {
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
        $accountcount = trim((string)($data['accountsindropdown'] ?? ''));
        if ($accountcount !== '' && $accountcount !== get_string('all') && !preg_match('/^-?\d+$/D', $accountcount)) {
            $errors['accountsindropdown'] = get_string('err_accountsindropdown', 'qtype_accounting');
        } else if ((int)$accountcount < 0) {
            $errors['accountsindropdown'] = get_string('err_accountsindropdown_negative', 'qtype_accounting');
        }
        $errors = $this->validate_available_accounts($data, $errors);
        $errors = (new \qtype_accounting\entry_validator())->validate($data, $errors);
        $this->update_submitted_entries($data, $errors);
        return $errors;
    }

    /**
     * Convert the translated all-accounts label back to the stored numeric value.
     *
     * @return object|null|false Validated question data, or no data when unavailable.
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->accountsindropdown = (int)$data->accountsindropdown;
        }
        return $data;
    }

    /**
     * Check the account list and reject entries whose account is no longer in it.
     *
     * @param array $data Submitted question data.
     * @param array $errors Existing validation errors.
     * @return array Validation errors.
     */
    protected function validate_available_accounts(array $data, array $errors): array {
        $list = (string)($data['accounts'] ?? '');
        $listerror = \qtype_accounting\question_accounts::validate_list($list);
        if ($listerror !== null) {
            $errors['accounts'] = $listerror;
            return $errors;
        }
        $keys = array_filter(array_map('intval', array_merge(
            $data['debitaccount'] ?? [],
            $data['creditaccount'] ?? []
        )));
        if ($keys && array_diff($keys, array_keys($this->get_current_accounts($list)))) {
            $errors['balancevalidation'] = get_string('err_accountunavailable', 'qtype_accounting');
        }
        return $errors;
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
