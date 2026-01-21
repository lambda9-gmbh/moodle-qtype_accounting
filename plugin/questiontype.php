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
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz extends question_type {

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

        // Save correct answer entries. Credit (Haben) account is required, Debit (Soll) is optional.
        // Form fields are arrays: sollkonto[], sollbetrag[], etc.
        // Note: Array indices may not be sequential if entries were deleted, so we iterate over
        // the actual keys in the habenkonto array rather than using a numeric counter.
        $sortorder = 0;

        $habenkontoarray = $question->habenkonto ?? [];
        foreach ($habenkontoarray as $i => $habenkonto) {
            $sollkonto = trim($question->sollkonto[$i] ?? '');
            $habenkonto = trim($habenkonto ?? '');

            // Only save entries that have a Credit (Haben) account.
            if (empty($habenkonto)) {
                continue;
            }

            // Convert grade percentage (0-100) to fraction (0-1).
            $grade = floatval($question->grade[$i] ?? 0);
            $fraction = $grade / 100;

            $record = new stdClass();
            $record->questionid = $question->id;
            $record->sortorder = $sortorder++;
            $record->sollkonto = $sollkonto;
            $record->sollbetrag = floatval($question->sollbetrag[$i] ?? 0);
            $record->habenkonto = $habenkonto;
            $record->habenbetrag = floatval($question->habenbetrag[$i] ?? 0);
            $record->fraction = $fraction;
            $record->explanation = $question->explanation[$i] ?? '';
            $DB->insert_record('qtype_buchungssatz_entries', $record);
        }

        $this->save_hints($question);

        return true;
    }

    /**
     * Get the question options.
     *
     * @param object $question The question object to populate.
     * @return bool True on success.
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
     * @param question_definition $question The question definition to initialise.
     * @param object $questiondata The question data from the database.
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
                    'explanation' => $entry->explanation ?? '',
                ];
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

        $DB->delete_records('qtype_buchungssatz_options', ['questionid' => $questionid]);
        $DB->delete_records('qtype_buchungssatz_entries', ['questionid' => $questionid]);

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
        return ['qtype_buchungssatz_options', 'chartofaccountsid', 'allowmultipleentries', 'maxentries'];
    }
}
