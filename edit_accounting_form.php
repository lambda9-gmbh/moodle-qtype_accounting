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
                get_string($strict ? 'gradingmode_points_info_strict' : 'gradingmode_points_info', 'qtype_accounting')
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
     * Get the account list the form is built with: the submitted list, the list copied with
     * the copy button, or the edited version's accounts.
     *
     * @return string|null The account list, or null for the edited version's accounts.
     */
    protected function get_submitted_account_list(): ?string {
        // The submission is only processed after the definition, so read the submitted list directly.
        $list = $this->optional_param('accounts', null, PARAM_TEXT);
        if ($list === null) {
            return null;
        }
        $source = (string)$this->optional_param('copyaccountsfrom', '', PARAM_ALPHANUMEXT);
        if ((string)$this->optional_param('copyaccounts', '', PARAM_RAW) !== '' && $source !== '') {
            $names = \qtype_accounting\question_accounts::get_source_names($source, $this->get_bank_context());
            if ($names) {
                $list = implode("\n", $names);
            }
        }
        return $list;
    }

    /**
     * Add the correct-answer-entries section: one repeated group of debit and credit fields per entry.
     *
     * An entry may leave one side empty. Amounts are entered in the question's number format.
     *
     * @param \MoodleQuickForm $mform The form being built.
     * @param string $effectiveformat The number format of the saved amounts ('de' or 'us').
     */
    protected function add_entries_section($mform, string $effectiveformat): void {
        global $PAGE;

        $mform->addElement('header', 'answerhdr', get_string('correctanswer', 'qtype_accounting'));
        $mform->setExpanded('answerhdr', true);
        $mform->addElement('static', 'balancevalidation', '', '');

        $list = $this->get_submitted_account_list();
        $accounts = ['' => get_string('selectaccount', 'qtype_accounting')] + $this->get_current_accounts($list);
        $placeholder = $effectiveformat === 'us' ? '0.00' : '0,00';
        $hideper = (string)get_config('qtype_accounting', 'showpercolumn') === '0';
        $weights = [1 => 1, 2 => 2, 3 => 3];

        $entry = [];
        foreach (['debit', 'credit'] as $side) {
            // The site setting showpercolumn replaces the booking labels "Dr." and "Per" with the side names.
            $label = $hideper ? $side : ($side === 'debit' ? 'per' : 'an');
            $entry[] = $mform->createElement('static', $side . 'label', '', html_writer::span(
                get_string($label, 'qtype_accounting'),
                'qtype_accounting-edit-label'
            ));
            $entry[] = $mform->createElement(
                'select',
                $side . 'account',
                get_string($side . 'account', 'qtype_accounting'),
                $accounts,
                ['data-accounting-field' => $side . 'account']
            );
            $entry[] = $mform->createElement('text', $side . 'amount', get_string($side . 'amount', 'qtype_accounting'), [
                'size' => 12,
                'placeholder' => $placeholder,
                'inputmode' => 'decimal',
                'data-accounting-field' => $side . 'amount',
            ]);
        }
        // Function repeat_elements() only marks delete buttons outside groups to skip the client-side validation.
        $entry[] = $mform->createElement('submit', 'deleteentry', get_string('deleteentry', 'qtype_accounting'), [
            'data-skip-validation' => 1,
            'data-no-submit' => 1,
            'onclick' => 'skipClientValidation = true;',
        ], false);

        $weightelements = [];
        foreach (['debitaccount', 'debitamount', 'creditaccount', 'creditamount'] as $field) {
            $weightelements[] = $mform->createElement(
                'select',
                'weight_' . $field,
                get_string('weight', 'qtype_accounting') . ': ' . get_string($field, 'qtype_accounting'),
                $weights,
                ['data-accounting-field' => 'weight_' . $field]
            );
        }

        $repeated = [
            $mform->createElement('group', 'entry', get_string('entryno', 'qtype_accounting'), $entry, ' ', false),
            $mform->createElement('group', 'weights', get_string('weight', 'qtype_accounting'), $weightelements, ' ', false),
        ];
        $options = [
            'debitaccount' => ['type' => PARAM_INT],
            'creditaccount' => ['type' => PARAM_INT],
            'debitamount' => ['type' => PARAM_RAW_TRIMMED, 'disabledif' => ['debitaccount', 'eq', '']],
            'creditamount' => ['type' => PARAM_RAW_TRIMMED, 'disabledif' => ['creditaccount', 'eq', '']],
            'weights' => ['hideif' => ['allornothinggrading', 'checked']],
        ];
        foreach (['debit', 'credit'] as $side) {
            foreach (['account', 'amount'] as $field) {
                // No default: the first option is weight 1, and a default would hide the saved weights.
                $options['weight_' . $side . $field] = [
                    'type' => PARAM_INT,
                    'disabledif' => [$side . 'account', 'eq', ''],
                ];
            }
        }

        $existing = count($this->question->options->entries ?? []);
        $repeats = $this->repeat_elements(
            $repeated,
            max(2, $existing),
            $options,
            'noentries',
            'addentries',
            1,
            get_string('addentry', 'qtype_accounting'),
            true,
            'deleteentry'
        );
        $strict = (bool)get_config('qtype_accounting', 'strictpairs');
        for ($i = 0; $i < $repeats; $i++) {
            // Deleted entries have no elements.
            if (!$mform->elementExists("weights[$i]")) {
                continue;
            }
            $mform->addHelpButton("weights[$i]", 'weight', 'qtype_accounting');
            if ($strict) {
                // Strict pairs grade every account-amount pair alike in points mode, so the weights do not apply.
                $mform->hideIf("weights[$i]", 'gradingmode', 'eq', \qtype_accounting\scorer::GRADINGMODE_POINTS);
            }
        }

        $PAGE->requires->js_call_amd('qtype_accounting/editform', 'init');
    }

    /**
     * Show the copied account list after the copy button was pressed.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        if ($mform->isSubmitted() && (string)$this->optional_param('copyaccounts', '', PARAM_RAW) !== '') {
            $list = $this->get_submitted_account_list();
            if ($list !== null) {
                $mform->setConstant('accounts', $list);
            }
        }
    }

    /**
     * Convert the entered amounts from the question's number format to plain decimal numbers.
     *
     * @param array $data Submitted form values.
     * @return array The values with decimal amounts; empty amounts stay empty.
     */
    protected function normalise_amounts(array $data): array {
        $format = ($data['numberformat'] ?? 'de') === 'us' ? 'us' : 'de';
        foreach (['debitamount', 'creditamount'] as $field) {
            foreach ((array)($data[$field] ?? []) as $i => $value) {
                $value = trim((string)$value);
                $data[$field][$i] = $value === '' ? '' :
                    \qtype_accounting\amount_helper::decimal_amount(\qtype_accounting\amount_helper::parse_cents($value, $format));
            }
        }
        return $data;
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
        if (empty($question->options->entries)) {
            return;
        }
        $format = $question->options->numberformat ?? 'de';
        $i = 0;
        foreach ($question->options->entries as $entry) {
            foreach (['debit', 'credit'] as $side) {
                $accountid = $entry->{$side . 'accountid'} ?? '';
                $question->{$side . 'account'}[$i] = $accountid;
                // Saved one-sided entries store zero for the absent side, which stays empty.
                $question->{$side . 'amount'}[$i] = $accountid ?
                    \qtype_accounting\amount_helper::format_amount($entry->{$side . 'amount'}, $format) : '';
            }
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
        $isall = strcasecmp($accountcount, get_string('all')) === 0;
        if ($accountcount !== '' && !$isall && !preg_match('/^-?\d+$/D', $accountcount)) {
            $errors['accountsindropdown'] = get_string('err_accountsindropdown', 'qtype_accounting');
        } else if ((int)$accountcount < 0) {
            $errors['accountsindropdown'] = get_string('err_accountsindropdown_negative', 'qtype_accounting');
        }
        // Selections of accounts that are no longer in the list are not among the select options, so they
        // are missing from the validated data; the raw submission still holds them.
        $selected = array_intersect_key($this->_form->getSubmitValues(), ['debitaccount' => 1, 'creditaccount' => 1]);
        $errors = $this->validate_available_accounts($selected + $data, $errors);
        $entryerrors = (new \qtype_accounting\entry_validator())->validate($this->normalise_amounts($data), []);
        foreach ($entryerrors as $field => $message) {
            // Errors of fields within an entry are shown at the entry's group.
            if (preg_match('/^(?:debit|credit)amount\[(\d+)\]$/', $field, $matches)) {
                $field = 'entry[' . $matches[1] . ']';
            }
            $errors[$field] = isset($errors[$field]) ? $errors[$field] . ' ' . $message : $message;
        }
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
            $data = (object)$this->normalise_amounts((array)$data);
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
            (array)($data['debitaccount'] ?? []),
            (array)($data['creditaccount'] ?? [])
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
