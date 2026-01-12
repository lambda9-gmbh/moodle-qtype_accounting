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
 * Question type class for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * The Buchungssatz question type.
 */
class qtype_buchungssatz extends question_type {

    /** @var int Maximum number of entry fields supported */
    const MAX_ENTRY_FIELDS = 50;

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
     * @param int $questionid
     * @param int $oldcontextid
     * @param int $newcontextid
     */
    public function move_files($questionid, $oldcontextid, $newcontextid): void {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Delete the files used by this question.
     *
     * @param int $questionid
     * @param int $contextid
     */
    protected function delete_files($questionid, $contextid): void {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    /**
     * Save the question-type specific options.
     *
     * @param object $question The question being saved.
     * @return object
     */
    public function save_question_options($question) {
        global $DB;

        $context = $question->context;
        $result = new stdClass();

        // Delete old options.
        $DB->delete_records('qtype_buchungssatz_options', ['questionid' => $question->id]);
        $DB->delete_records('qtype_buchungssatz_entries', ['questionid' => $question->id]);

        // Save options.
        $options = new stdClass();
        $options->questionid = $question->id;
        $options->chartofaccountsid = $question->chartofaccountsid ?? 0;
        $options->allowmultipleentries = $question->allowmultipleentries ?? 1;
        $options->maxentries = $question->maxentries ?? 5;
        $DB->insert_record('qtype_buchungssatz_options', $options);

        // Save correct answer entries (Soll and Haben).
        // Form fields are named sollkonto_0, sollbetrag_0, etc.
        for ($i = 0; $i < self::MAX_ENTRY_FIELDS; $i++) {
            $sollkonto = trim($question->{'sollkonto_' . $i} ?? '');
            $habenkonto = trim($question->{'habenkonto_' . $i} ?? '');

            if (empty($sollkonto) && empty($habenkonto)) {
                continue;
            }

            $record = new stdClass();
            $record->questionid = $question->id;
            $record->sortorder = $i;
            $record->sollkonto = $sollkonto;
            $record->sollbetrag = floatval($question->{'sollbetrag_' . $i} ?? 0);
            $record->habenkonto = $habenkonto;
            $record->habenbetrag = floatval($question->{'habenbetrag_' . $i} ?? 0);
            $record->fraction = floatval($question->{'fraction_' . $i} ?? 1.0);
            $DB->insert_record('qtype_buchungssatz_entries', $record);
        }

        $this->save_hints($question);

        return true;
    }

    /**
     * Get the question options.
     *
     * @param object $question
     * @return bool
     */
    public function get_question_options($question): bool {
        global $DB;

        $question->options = $DB->get_record('qtype_buchungssatz_options',
            ['questionid' => $question->id]);

        if ($question->options === false) {
            $question->options = new stdClass();
            $question->options->questionid = $question->id;
            $question->options->chartofaccountsid = 0;
            $question->options->allowmultipleentries = 1;
            $question->options->maxentries = 5;
        }

        $question->options->entries = $DB->get_records('qtype_buchungssatz_entries',
            ['questionid' => $question->id], 'sortorder ASC');

        parent::get_question_options($question);
        return true;
    }

    /**
     * Initialise the common question_definition fields.
     *
     * @param question_definition $question
     * @param object $questiondata
     */
    protected function initialise_question_instance(question_definition $question, $questiondata): void {
        parent::initialise_question_instance($question, $questiondata);

        $question->chartofaccountsid = $questiondata->options->chartofaccountsid ?? 0;
        $question->allowmultipleentries = $questiondata->options->allowmultipleentries ?? 1;
        $question->maxentries = $questiondata->options->maxentries ?? 5;
        $question->entries = [];

        if (!empty($questiondata->options->entries)) {
            foreach ($questiondata->options->entries as $entry) {
                $question->entries[] = [
                    'sollkonto' => $entry->sollkonto,
                    'sollbetrag' => $entry->sollbetrag,
                    'habenkonto' => $entry->habenkonto,
                    'habenbetrag' => $entry->habenbetrag,
                    'fraction' => $entry->fraction,
                ];
            }
        }
    }

    /**
     * Delete a question from the database.
     *
     * @param int $questionid
     * @param int $contextid
     */
    public function delete_question($questionid, $contextid): void {
        global $DB;

        $DB->delete_records('qtype_buchungssatz_options', ['questionid' => $questionid]);
        $DB->delete_records('qtype_buchungssatz_entries', ['questionid' => $questionid]);

        parent::delete_question($questionid, $contextid);
    }

    /**
     * Get the random guess score.
     *
     * @param object $questiondata
     * @return float|null
     */
    public function get_random_guess_score($questiondata): ?float {
        return 0;
    }

    /**
     * Get possible responses for reporting.
     *
     * @param object $questiondata
     * @return array
     */
    public function get_possible_responses($questiondata): array {
        return [];
    }

    /**
     * Define extra columns needed in the question bank.
     *
     * @return array
     */
    public function extra_question_fields(): array {
        return ['qtype_buchungssatz_options', 'chartofaccountsid', 'allowmultipleentries', 'maxentries'];
    }
}
