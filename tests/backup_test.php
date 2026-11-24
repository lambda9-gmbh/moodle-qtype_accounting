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

namespace qtype_accounting;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/tests/backup_restore_base_testcase.php');
require_once($CFG->dirroot . '/backup/moodle2/restore_qtype_plugin.class.php');
require_once($CFG->dirroot . '/question/type/accounting/backup/moodle2/restore_qtype_accounting_plugin.class.php');
require_once($CFG->dirroot . '/question/type/accounting/tests/helper.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\backup_qtype_accounting_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_qtype_accounting_plugin::class)]
/**
 * Regression tests for accounting question backup and restore.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_qtype_accounting_plugin
 * @covers     \restore_qtype_accounting_plugin
 */
final class backup_test extends \core_backup_backup_restore_base_testcase {
    /**
     * Restored questions keep their own accounts, account keys, sort order and entries; no chart is created.
     */
    public function test_restore_keeps_question_accounts(): void {
        global $DB;
        $source = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([
            'contextid' => \qtype_accounting_test_helper::question_bank_context($source)->id,
        ]);
        $generator->create_question('accounting', null, [
            'category' => $category->id, 'name' => 'Keys',
            'accounts' => ['Sales', 'Bank'], 'accountkeys' => ['Bank' => 512, 'Sales' => 77],
            'debitaccount' => [512], 'creditaccount' => [77], 'debitamount' => [100], 'creditamount' => [100],
        ]);
        $generator->create_question('accounting', null, [
            'category' => $category->id, 'name' => 'Same names',
            'accounts' => ['Bank'], 'accountkeys' => [],
            'debitaccount' => [1], 'creditaccount' => [1], 'debitamount' => [100], 'creditamount' => [100],
        ]);
        $target = $this->getDataGenerator()->create_course();
        $this->perform_restore($this->perform_backup($source), $target);

        $questions = $this->get_restored_questions($target);
        $this->assertSame(['Keys', 'Same names'], array_keys($questions));
        $keys = \question_bank::load_question($questions['Keys']);
        $this->assertSame([512 => 'Bank', 77 => 'Sales'], $keys->accountsmap);
        $this->assertSame(['Sales', 'Bank'], array_values(array_map(
            fn($account) => $account->accountname,
            question_accounts::get((int)$questions['Keys'])
        )));
        $this->assertSame([512, 77], [$keys->entries[0]['debitaccountid'], $keys->entries[0]['creditaccountid']]);
        $this->assertSame([1 => 'Bank'], \question_bank::load_question($questions['Same names'])->accountsmap);
        $this->assertSame(0, $DB->count_records('qtype_accounting_charts'));
    }

    /**
     * Responses in restored attempts keep their account keys and grade as before, also for a reused question.
     */
    public function test_restore_keeps_attempt_responses(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $context = \context_course::instance($course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category([
            'contextid' => \qtype_accounting_test_helper::question_bank_context($course)->id,
        ]);
        $question = $generator->create_question('accounting', null, [
            'category' => $category->id, 'accounts' => ['Bank', 'Sales'], 'accountkeys' => ['Bank' => 40, 'Sales' => 41],
            'debitaccount' => [40], 'creditaccount' => [41], 'debitamount' => [100.50], 'creditamount' => [100.50],
        ]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        quiz_add_quiz_question($question->id, $quiz);
        $quiz->sumgrades = 1;
        $DB->set_field('quiz', 'sumgrades', 1, ['id' => $quiz->id]);
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);
        $attempt = quiz_create_attempt($quizobj, 1, false, time(), false, $student->id);
        $usage = \question_engine::make_questions_usage_by_activity('mod_quiz', \context_module::instance($quiz->cmid));
        $usage->set_preferred_behaviour('deferredfeedback');
        $loaded = \question_bank::load_question($question->id);
        $usage->add_question($loaded);
        $usage->start_all_questions();
        $usage->process_action(1, $loaded->get_correct_response());
        $usage->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($usage);
        $attempt->uniqueid = $usage->get_id();
        $attempt->state = \mod_quiz\quiz_attempt::FINISHED;
        $attempt->timefinish = time();
        $DB->insert_record('quiz_attempts', $attempt);

        $target = $this->getDataGenerator()->create_course();
        // Repeating into the same target also covers responses of a reused question.
        for ($restore = 0; $restore < 2; $restore++) {
            get_file_storage()->delete_area_files($context->id, 'backup', 'course');
            $this->perform_restore($this->perform_backup($course), $target);
            $quizzes = $DB->get_records('quiz', ['course' => $target->id], 'id DESC');
            $restoredattempt = $DB->get_record('quiz_attempts', ['quiz' => reset($quizzes)->id], '*', MUST_EXIST);
            $restoredusage = \question_engine::load_questions_usage_by_activity($restoredattempt->uniqueid);
            $restoredquestion = $restoredusage->get_question(1);
            $response = $restoredusage->get_question_attempt(1)->get_last_qt_data();
            $this->assertEquals([40, 41], [$response['debitaccount_0'], $response['creditaccount_0']]);
            $this->assertSame([40 => 'Bank', 41 => 'Sales'], $restoredquestion->accountsmap);
            $this->assertEquals(1.0, $restoredquestion->grade_response($response)[0]);
        }
    }

    /**
     * A question without accounts must not inherit the accounts of the preceding question.
     */
    public function test_restore_question_without_accounts(): void {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        foreach (['With accounts' => ['Bank'], 'Without accounts' => []] as $name => $accounts) {
            $generator->create_question('accounting', null, [
                'category' => $category->id, 'name' => $name, 'accounts' => $accounts, 'accountkeys' => [],
                'debitaccount' => [$accounts ? 1 : 0], 'creditaccount' => [0],
            ]);
        }
        $target = $this->getDataGenerator()->create_course();
        $this->perform_restore($this->perform_backup($course), $target);
        $questions = $this->get_restored_questions($target);
        $this->assertSame([1 => 'Bank'], \question_bank::load_question($questions['With accounts'])->accountsmap);
        $this->assertSame([], \question_bank::load_question($questions['Without accounts'])->accountsmap);
    }

    /**
     * Backups of earlier versions carry the question's chart: its account IDs become the account keys,
     * which their entries and responses already store.
     */
    public function test_backup_of_earlier_version_is_converted(): void {
        $questiondata = \restore_qtype_accounting_plugin::convert_backup_to_questiondata([
            'name' => 'Old', 'qtype' => 'accounting', 'questiontext' => '', 'generalfeedback' => '',
            'plugin_qtype_accounting_question' => [
                'accounting_chart' => [['name' => 'Course chart', 'chart_accounts' => ['chart_account' => [
                    ['id' => 900, 'accountname' => 'Bank', 'sortorder' => 0],
                    ['id' => 905, 'accountname' => 'Sales', 'sortorder' => 1],
                ]]]],
                'accounting_options' => [['chartofaccountsid' => 12, 'numberformat' => 'de']],
                'accounting_entries' => ['entry' => [
                    ['id' => 1, 'debitaccountid' => 900, 'creditaccountid' => 905, 'debitamount' => 5, 'creditamount' => 5],
                    // Very old backups reference accounts by name.
                    ['id' => 2, 'debitaccount' => 'Sales', 'creditaccount' => 'Bank', 'debitamount' => 5, 'creditamount' => 5],
                ]],
            ],
        ]);
        $this->assertFalse(property_exists($questiondata->options, 'chartofaccountsid'));
        $this->assertSame([900, 905], array_keys($questiondata->options->accounts));
        $this->assertSame('Sales', $questiondata->options->accounts[905]->accountname);
        $entries = array_values($questiondata->options->entries);
        $this->assertEquals([900, 905], [$entries[0]->debitaccountid, $entries[0]->creditaccountid]);
        $this->assertEquals([905, 900], [$entries[1]->debitaccountid, $entries[1]->creditaccountid]);
        $this->assertEquals(1, $entries[1]->weight_debitaccount);
    }

    /**
     * Get the restored accounting questions of a course and its question banks.
     *
     * @param \stdClass $course The course.
     * @return array Question IDs keyed by question name.
     */
    private function get_restored_questions(\stdClass $course): array {
        global $DB;
        $context = \context_course::instance($course->id);
        $childcontext = $DB->sql_like('ctx.path', ':contextpath');
        return $DB->get_records_sql_menu(
            "SELECT q.name, q.id
               FROM {question} q
               JOIN {question_versions} v ON v.questionid = q.id
               JOIN {question_bank_entries} b ON b.id = v.questionbankentryid
               JOIN {question_categories} c ON c.id = b.questioncategoryid
               JOIN {context} ctx ON ctx.id = c.contextid
              WHERE (ctx.id = :context OR {$childcontext}) AND q.qtype = :qtype
           ORDER BY q.name",
            ['context' => $context->id, 'contextpath' => $context->path . '/%', 'qtype' => 'accounting']
        );
    }
}
