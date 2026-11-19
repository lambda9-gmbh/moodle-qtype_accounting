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
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Regression tests for question identity during activity duplication and course restore.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \restore_qtype_accounting_plugin
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_qtype_accounting_plugin::class)]
final class restore_identity_test extends \core_backup_backup_restore_base_testcase {
    /**
     * Repeated quiz duplication must reuse the existing question bank entries and versions.
     */
    public function test_duplicate_quiz_reuses_questions(): void {
        global $DB;
        [$course, $category, $accounting, $control] = $this->create_questions();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        quiz_add_quiz_question($accounting->id, $quiz);
        quiz_add_quiz_question($control->id, $quiz);
        $cm = get_fast_modinfo($course)->get_cm($quiz->cmid);
        $expectedentries = [$accounting->questionbankentryid, $control->questionbankentryid];
        sort($expectedentries);

        for ($i = 0; $i < 3; $i++) {
            $copy = duplicate_module($course, $cm);
            $this->assertNotNull($copy);
            $this->assertEquals(2, $DB->count_records('question_bank_entries', ['questioncategoryid' => $category->id]));
            $this->assertEquals(2, $DB->count_records('question_versions', [
                'questionbankentryid' => $accounting->questionbankentryid,
            ]));
            $references = $DB->get_records('question_references', [
                'usingcontextid' => \context_module::instance($copy->id)->id,
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
            ]);
            $actualentries = array_column($references, 'questionbankentryid');
            sort($actualentries);
            $this->assertEquals($expectedentries, $actualentries);
        }
    }

    /**
     * Repeated restore must not duplicate questions within an existing question bank.
     *
     * Moodle 5.1 restores another bank module on each course import, so check
     * identity reuse within each bank and parity with the core control question.
     */
    public function test_repeated_course_restore_reuses_questions(): void {
        global $DB;
        [$source, , $accounting] = $this->create_questions();
        $sourcecontext = \context_course::instance($source->id);
        $sourcechartid = $DB->get_field('qtype_accounting_options', 'chartofaccountsid', ['questionid' => $accounting->id]);
        $target = $this->getDataGenerator()->create_course();
        $targetcontext = \context_course::instance($target->id);
        $childcontext = $DB->sql_like('ctx.path', ':contextpath');
        $contextparams = ['context' => $targetcontext->id, 'contextpath' => $targetcontext->path . '/%'];
        $previousbanks = [];

        for ($i = 0; $i < 2; $i++) {
            get_file_storage()->delete_area_files($sourcecontext->id, 'backup', 'course');
            $this->perform_restore($this->perform_backup($source), $target);
            $entries = $DB->get_records_sql(
                "SELECT DISTINCT b.id, c.contextid, q.qtype FROM {question_bank_entries} b
                   JOIN {question_categories} c ON c.id = b.questioncategoryid
                   JOIN {question_versions} v ON v.questionbankentryid = b.id
                   JOIN {question} q ON q.id = v.questionid
                   JOIN {context} ctx ON ctx.id = c.contextid
                  WHERE (ctx.id = :context OR {$childcontext}) ORDER BY b.id",
                $contextparams
            );
            $banks = [];
            foreach ($entries as $entry) {
                $banks[$entry->contextid][$entry->id] = $entry->qtype;
            }
            $this->assertNotEmpty($banks);
            foreach ($banks as $contextid => $bankentries) {
                $types = array_values($bankentries);
                sort($types);
                $this->assertSame(['accounting', 'truefalse'], $types);
                if (isset($previousbanks[$contextid])) {
                    $this->assertEquals($previousbanks[$contextid], $bankentries);
                }
            }
            foreach (array_keys($previousbanks) as $contextid) {
                $this->assertArrayHasKey($contextid, $banks);
            }
            $previousbanks = $banks;
        }

        $options = $DB->get_records_sql(
            "SELECT o.* FROM {qtype_accounting_options} o
               JOIN {question_versions} v ON v.questionid = o.questionid
               JOIN {question_bank_entries} b ON b.id = v.questionbankentryid
               JOIN {question_categories} c ON c.id = b.questioncategoryid
               JOIN {context} ctx ON ctx.id = c.contextid
              WHERE (ctx.id = :context OR {$childcontext})",
            $contextparams
        );
        // The source fixture has two versions of its accounting question.
        $this->assertCount(2 * count($previousbanks), $options);
        foreach ($options as $option) {
            $this->assertNotEquals($sourcechartid, $option->chartofaccountsid);
            $question = \question_bank::load_question($option->questionid);
            $this->assertSame('1200 Bank', $question->accountsmap[$question->entries[0]['debitaccountid']]);
            $this->assertSame('8400 Sales', $question->accountsmap[$question->entries[1]['creditaccountid']]);
        }
    }

    /**
     * Portable identity must still distinguish changes to answer accounts, amounts, and weights.
     */
    public function test_identity_preserves_answer_differences(): void {
        global $DB;
        [, , $accounting] = $this->create_questions();
        $original = $DB->get_record('question', ['id' => $accounting->id], '*', MUST_EXIST);
        get_question_options($original);
        $originalhash = \restore_questions_parser_processor::generate_question_identity_hash($original);
        $entries = array_values($original->options->entries);
        $changes = [
            'debitaccountid' => $entries[1]->creditaccountid,
            'debitamount' => 200,
            'weight_debitaccount' => 3,
        ];
        foreach ($changes as $field => $value) {
            $changed = unserialize(serialize($original));
            $entry = reset($changed->options->entries);
            $entry->$field = $value;
            $this->assertNotSame(
                $originalhash,
                \restore_questions_parser_processor::generate_question_identity_hash($changed),
                'Changed answer field: ' . $field
            );
        }
        $this->assertEquals($entries[0]->debitaccountid, reset($original->options->entries)->debitaccountid);
    }

    /**
     * Create two versioned questions with a shared chart and compound accounting entries.
     *
     * @return array Course, category, accounting question, and true/false control.
     */
    private function create_questions(): array {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $chartid = chart_manager::create_chart('Restore identity chart', $context->id);
        $bankid = account_manager::add($chartid, '1200 Bank', 0);
        $salesid = account_manager::add($chartid, '8400 Sales', 1);
        account_manager::add($chartid, '1000 Cash', 2);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $overrides = [
            'category' => $category->id,
            'chartofaccountsid' => $chartid,
            'debitaccount' => [$bankid, 0],
            'creditaccount' => [0, $salesid],
            'debitamount' => [100, 0],
            'creditamount' => [0, 100],
            'weight_debitaccount' => [2, 1],
            'weight_creditaccount' => [1, 3],
        ];
        $accounting = $generator->create_question('accounting', null, $overrides);
        $accounting = $generator->update_question($accounting, null, $overrides);
        $control = $generator->create_question('truefalse', null, ['category' => $category->id]);
        $control = $generator->update_question($control, null, ['category' => $category->id]);
        return [$course, $category, $accounting, $control];
    }
}
