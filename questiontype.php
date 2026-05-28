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
 * Question type class for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * The Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_accounting extends question_type {
    /**
     * Whether this question type can be used for random questions.
     *
     * @return bool
     */
    public function is_usable_by_random(): bool {
        return false;
    }

    /**
     * Move files from the old area to the new.
     *
     * @param int $questionid The question ID.
     * @param int $oldcontextid The old context ID.
     * @param int $newcontextid The new context ID.
     */
    public function move_files($questionid, $oldcontextid, $newcontextid): void {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Delete the files used by this question.
     *
     * @param int $questionid The question ID.
     * @param int $contextid The context ID.
     */
    protected function delete_files($questionid, $contextid): void {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    /**
     * Save the question-type specific options.
     *
     * @param object $question The question being saved.
     * @return bool True on success.
     */
    public function save_question_options($question) {
        global $DB;

        $DB->delete_records('qtype_accounting_options', ['questionid' => $question->id]);
        $DB->delete_records('qtype_accounting_entries', ['questionid' => $question->id]);

        $DB->insert_record('qtype_accounting_options', $this->build_options_record($question));

        $arrays = $this->normalize_entry_arrays($question);
        $this->save_entries($question->id, $arrays);

        $this->save_hints($question);

        return true;
    }

    /**
     * Build the qtype_accounting_options DB record from the submitted question form data.
     *
     * @param object $question The question being saved.
     * @return \stdClass Record ready for $DB->insert_record.
     */
    protected function build_options_record($question): \stdClass {
        $options = new stdClass();
        $options->questionid = $question->id;
        $options->chartofaccountsid = $question->chartofaccountsid ?? 0;
        $options->accountsindropdown = $question->accountsindropdown ?? 0;
        $options->numberformat = $question->numberformat ?? 'de';
        $options->extraentrydeduction = $question->extraentrydeduction ?? null;
        $options->allornothinggrading = $question->allornothinggrading ?? 0;
        $options->allowmultipleentries = $question->allowmultipleentries ?? 1;
        $options->maxentries = $question->maxentries ?? 5;
        return $options;
    }

    /**
     * Normalise the 8 entry-form arrays (account/amount + weights) into actual arrays.
     *
     * Behat submissions sometimes send scalar values instead of arrays; this coerces those
     * back so the indexed lookups below work uniformly.
     *
     * @param object $question The question being saved.
     * @return array Keyed by field name (debitaccount, debitamount, ..., weight_creditamount).
     */
    protected function normalize_entry_arrays($question): array {
        $fields = [
            'debitaccount', 'debitamount', 'creditaccount', 'creditamount',
            'weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount',
        ];
        $arrays = [];
        foreach ($fields as $field) {
            $value = $question->$field ?? [];
            $arrays[$field] = is_array($value) ? $value : [$value];
        }
        return $arrays;
    }

    /**
     * Save the correct-answer entry rows for a question.
     *
     * Iterates the union of indices across debitaccount and creditaccount, skipping rows where
     * both account fields are empty, and inserts one record per remaining row.
     *
     * @param int $questionid The question ID.
     * @param array $arrays Normalised entry-field arrays (output of normalize_entry_arrays()).
     */
    protected function save_entries(int $questionid, array $arrays): void {
        global $DB;
        $allindices = array_unique(array_merge(array_keys($arrays['debitaccount']), array_keys($arrays['creditaccount'])));
        sort($allindices);
        $sortorder = 0;
        foreach ($allindices as $i) {
            $debitaccountid = intval($arrays['debitaccount'][$i] ?? 0);
            $creditaccountid = intval($arrays['creditaccount'][$i] ?? 0);
            if (empty($debitaccountid) && empty($creditaccountid)) {
                continue;
            }
            $record = new stdClass();
            $record->questionid = $questionid;
            $record->sortorder = $sortorder++;
            $record->debitaccountid = $debitaccountid ?: null;
            $record->debitamount = floatval($arrays['debitamount'][$i] ?? 0);
            $record->creditaccountid = $creditaccountid ?: null;
            $record->creditamount = floatval($arrays['creditamount'][$i] ?? 0);
            $record->weight_debitaccount = intval($arrays['weight_debitaccount'][$i] ?? 1);
            $record->weight_debitamount = intval($arrays['weight_debitamount'][$i] ?? 1);
            $record->weight_creditaccount = intval($arrays['weight_creditaccount'][$i] ?? 1);
            $record->weight_creditamount = intval($arrays['weight_creditamount'][$i] ?? 1);
            $record->explanation = '';
            $DB->insert_record('qtype_accounting_entries', $record);
        }
    }

    /**
     * Get the question options.
     *
     * @param object $question The question object to populate.
     * @return bool True on success.
     */
    public function get_question_options($question): bool {
        global $DB;

        $question->options = $DB->get_record(
            'qtype_accounting_options',
            ['questionid' => $question->id]
        );

        if ($question->options === false) {
            $question->options = new stdClass();
            $question->options->questionid = $question->id;
            $question->options->chartofaccountsid = 0;
            $question->options->accountsindropdown = 0;
            $question->options->numberformat = 'de';
            $question->options->extraentrydeduction = null;
            $question->options->allornothinggrading = 0;
            $question->options->allowmultipleentries = 1;
            $question->options->maxentries = 5;
        }

        $question->options->entries = $DB->get_records(
            'qtype_accounting_entries',
            ['questionid' => $question->id],
            'sortorder ASC'
        );

        parent::get_question_options($question);
        return true;
    }

    /**
     * Initialise the common question_definition fields.
     *
     * @param question_definition $question The question definition to initialise.
     * @param object $questiondata The question data from the database.
     */
    protected function initialise_question_instance(question_definition $question, $questiondata): void {
        global $DB;
        parent::initialise_question_instance($question, $questiondata);

        $question->chartofaccountsid = $questiondata->options->chartofaccountsid ?? 0;
        $question->accountsindropdown = $questiondata->options->accountsindropdown ?? 0;
        $question->numberformat = $questiondata->options->numberformat ?? 'de';
        $question->extraentrydeduction = $questiondata->options->extraentrydeduction ?? null;
        $question->allornothinggrading = $questiondata->options->allornothinggrading ?? 0;
        $question->allowmultipleentries = $questiondata->options->allowmultipleentries ?? 1;
        $question->maxentries = $questiondata->options->maxentries ?? 5;
        $question->entries = [];

        if (!empty($questiondata->options->entries)) {
            foreach ($questiondata->options->entries as $entry) {
                $question->entries[] = [
                    'debitaccountid' => $entry->debitaccountid ? (int) $entry->debitaccountid : null,
                    'debitamount' => $entry->debitamount,
                    'creditaccountid' => $entry->creditaccountid ? (int) $entry->creditaccountid : null,
                    'creditamount' => $entry->creditamount,
                    'weight_debitaccount' => $entry->weight_debitaccount ?? 1,
                    'weight_debitamount' => $entry->weight_debitamount ?? 1,
                    'weight_creditaccount' => $entry->weight_creditaccount ?? 1,
                    'weight_creditamount' => $entry->weight_creditamount ?? 1,
                    'explanation' => $entry->explanation ?? '',
                ];
            }
        }

        // Load account lookup map for display (ID => name).
        $question->accountsmap = [];
        if ($question->chartofaccountsid > 0) {
            $accounts = $DB->get_records(
                'qtype_accounting_accounts',
                ['chartid' => $question->chartofaccountsid]
            );
            foreach ($accounts as $acc) {
                $question->accountsmap[$acc->id] = $acc->accountname;
            }
        }
    }

    /**
     * Delete a question from the database.
     *
     * @param int $questionid The question ID.
     * @param int $contextid The context ID.
     */
    public function delete_question($questionid, $contextid): void {
        global $DB;

        $DB->delete_records('qtype_accounting_options', ['questionid' => $questionid]);
        $DB->delete_records('qtype_accounting_entries', ['questionid' => $questionid]);

        parent::delete_question($questionid, $contextid);
    }

    /**
     * Get the random guess score.
     *
     * @param object $questiondata The question data.
     * @return float|null The random guess score.
     */
    public function get_random_guess_score($questiondata): ?float {
        return 0;
    }

    /**
     * Get possible responses for reporting.
     *
     * @param object $questiondata The question data.
     * @return array The possible responses.
     */
    public function get_possible_responses($questiondata): array {
        return [];
    }

    /**
     * Define extra columns needed in the question bank.
     *
     * @return array The extra question fields.
     */
    public function extra_question_fields(): array {
        return ['qtype_accounting_options', 'chartofaccountsid', 'accountsindropdown', 'numberformat',
            'extraentrydeduction', 'allornothinggrading',
            'allowmultipleentries', 'maxentries'];
    }

    /**
     * Export this question to the Moodle XML format.
     *
     * Appends correct answer entries and full chart of accounts data
     * to the auto-exported options fields from extra_question_fields().
     *
     * @param object $question The question data to export.
     * @param qformat_xml $format The XML format helper.
     * @param mixed $extra Any additional format specific data.
     * @return string The XML fragment for this question type.
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $expout = parent::export_to_xml($question, $format, $extra);
        $handler = new \qtype_accounting\xml_handler();
        if (!empty($question->options->entries)) {
            $expout .= $handler->export_entries($question->options->entries, $format);
        }
        $chartid = (int)($question->options->chartofaccountsid ?? 0);
        if ($chartid > 0) {
            $expout .= $handler->export_chart($chartid, $format);
        }
        return $expout;
    }

    /**
     * Import a question from the Moodle XML format.
     *
     * @param array $data The XML data for this question.
     * @param object $question The default question object.
     * @param qformat_xml $format The XML format helper.
     * @param mixed $extra Any additional format specific data.
     * @return object|false The question object, or false if not this type.
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        $qtype = $data['@']['type'];
        if ($qtype != $this->name()) {
            return false;
        }
        $handler = new \qtype_accounting\xml_handler();
        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields); // Remove table name.
        $qo = $handler->import_options($data, $format, $qtype, $extraquestionfields);
        $handler->import_entries($data, $format, $qo);

        $contextid = $handler->resolve_contextid($format);
        $chartdata = $format->getpath($data, ['#', 'chartofaccounts', 0], null);
        if ($chartdata !== null && $contextid > 0) {
            $handler->resolve_chart($chartdata, $format, $contextid, $qo);
        }
        return $qo;
    }
}
