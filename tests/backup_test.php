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
     * Same-name charts with different contents must remain distinct, even for subset accounts.
     */
    public function test_restore_preserves_different_same_name_charts(): void {
        global $DB;
        $source = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($source->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        foreach ([['Bank', 'Sales'], ['Bank']] as $names) {
            $chart = chart_manager::create_chart('Same name', $context->id);
            $ids = [];
            foreach ($names as $i => $name) {
                $ids[] = account_manager::add($chart, $name, $i);
            }
            $generator->create_question('accounting', null, [
                'category' => $category->id, 'chartofaccountsid' => $chart,
                'debitaccount' => [$ids[0]], 'creditaccount' => [end($ids)],
                'debitamount' => [100], 'creditamount' => [100],
            ]);
        }
        $target = $this->getDataGenerator()->create_course();
        $this->perform_restore($this->perform_backup($source), $target);
        $charts = $DB->get_records('qtype_accounting_charts', [
            'contextid' => \context_course::instance($target->id)->id, 'name' => 'Same name',
        ]);
        $this->assertCount(2, $charts);
        $sizes = array_map(static fn($chart) => count(account_manager::get_for_chart($chart->id)), $charts);
        sort($sizes);
        $this->assertSame([1, 2], $sizes);
    }

    /**
     * Backups with attempts must remap answers as well as the correct solution.
     */
    public function test_restore_recodes_student_attempts(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);
        $context = \context_course::instance($course->id);
        $chartid = chart_manager::create_chart('Attempt chart', $context->id);
        $bank = account_manager::add($chartid, 'Bank');
        $sales = account_manager::add($chartid, 'Sales');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $question = $generator->create_question('accounting', null, [
            'category' => $category->id, 'chartofaccountsid' => $chartid,
            'debitaccount' => [$bank], 'creditaccount' => [$sales], 'debitamount' => [100.50], 'creditamount' => [100.50],
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
        // Repeating into the same target exercises response mapping for an existing question.
        for ($restore = 0; $restore < 2; $restore++) {
            get_file_storage()->delete_area_files($context->id, 'backup', 'course');
            $backupid = $this->perform_backup($course);
            $this->perform_restore($backupid, $target);
            $quizzes = $DB->get_records('quiz', ['course' => $target->id], 'id DESC');
            $restoredquiz = reset($quizzes);
            $restoredattempt = $DB->get_record('quiz_attempts', ['quiz' => $restoredquiz->id], '*', MUST_EXIST);
            $restoredusage = \question_engine::load_questions_usage_by_activity($restoredattempt->uniqueid);
            $restoredquestion = $restoredusage->get_question(1);
            $response = $restoredusage->get_question_attempt(1)->get_last_qt_data();
            if ((int)$restoredquestion->id !== (int)$question->id) {
                $this->assertNotEquals($bank, $response['debitaccount_0']);
                $this->assertNotEquals($sales, $response['creditaccount_0']);
            } else {
                $this->assertEquals($bank, $response['debitaccount_0']);
                $this->assertEquals($sales, $response['creditaccount_0']);
            }
            $this->assertEquals(1.0, $restoredquestion->grade_response($response)[0]);
            $this->assertEquals($restoredquestion->chartofaccountsid, $DB->get_field('qtype_accounting_accounts', 'chartid', [
                'id' => $response['debitaccount_0'],
            ]));
        }
        $this->assertEquals(1, $DB->count_records('qtype_accounting_charts', [
            'contextid' => \context_course::instance($target->id)->id,
        ]));
    }

    /**
     * A question without a chart must not inherit the preceding question's restore state.
     */
    public function test_restore_question_without_chart(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $chartid = chart_manager::create_chart('Chart before empty question', $context->id);
        $bank = account_manager::add($chartid, 'Bank');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        foreach (['With chart' => $chartid, 'Without chart' => 0] as $name => $id) {
            $generator->create_question('accounting', null, [
                'category' => $category->id, 'name' => $name, 'chartofaccountsid' => $id,
                'debitaccount' => [$id ? $bank : 0], 'creditaccount' => [0],
            ]);
        }
        $target = $this->getDataGenerator()->create_course();
        $this->perform_restore($this->perform_backup($course), $target);
        $targetcontext = \context_course::instance($target->id);
        $childcontext = $DB->sql_like('ctx.path', ':contextpath');
        $options = $DB->get_records_sql(
            "SELECT o.* FROM {qtype_accounting_options} o
               JOIN {question} q ON q.id = o.questionid
               JOIN {question_versions} v ON v.questionid = q.id
               JOIN {question_bank_entries} b ON b.id = v.questionbankentryid
               JOIN {question_categories} c ON c.id = b.questioncategoryid
               JOIN {context} ctx ON ctx.id = c.contextid
              WHERE (ctx.id = :context OR {$childcontext}) AND q.name = :name",
            ['context' => $targetcontext->id, 'contextpath' => $targetcontext->path . '/%', 'name' => 'Without chart']
        );
        $this->assertCount(1, $options);
        $this->assertEquals(0, reset($options)->chartofaccountsid);
    }

    /**
     * Restored entries must reference accounts in the target chart, including when reusing a chart.
     *
     * @dataProvider chart_reuse_provider
     * @param bool $reusechart Whether to create a matching chart before restore.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('chart_reuse_provider')]
    public function test_course_restore_preserves_accounts(bool $reusechart): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $chartid = chart_manager::create_chart('Backup chart', $context->id);
        $bankid = account_manager::add($chartid, '1200 Bank', 0);
        $salesid = account_manager::add($chartid, '8400 Sales', 1);
        account_manager::add($chartid, '1000 Cash', 2);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        // Two questions share the chart; the second contains one-sided compound booking rows.
        foreach (['Sale', 'Compound sale'] as $name) {
            $compound = $name === 'Compound sale';
            $generator->create_question('accounting', null, [
                'category' => $category->id,
                'name' => $name,
                'chartofaccountsid' => $chartid,
                'debitaccount' => $compound ? [$bankid, 0] : [$bankid],
                'creditaccount' => $compound ? [0, $salesid] : [$salesid],
                'debitamount' => $compound ? [123.45, 0] : [123.45],
                'creditamount' => $compound ? [0, 123.45] : [123.45],
                'weight_debitaccount' => [2, 1],
                'weight_creditaccount' => [3, 2],
            ]);
        }

        // Restore twice in one process to ensure chart reuse stays within each target context.
        for ($i = 0; $i < 2; $i++) {
            $target = $this->getDataGenerator()->create_course();
            $targetcontext = \context_course::instance($target->id);
            $existingchartid = null;
            if ($reusechart) {
                $existingchartid = chart_manager::create_chart('Backup chart', $targetcontext->id);
                // Reverse insertion order so source IDs cannot accidentally match the target accounts.
                account_manager::add($existingchartid, '8400 Sales', 1);
                account_manager::add($existingchartid, '1200 Bank', 0);
                account_manager::add($existingchartid, '1000 Cash', 2);
            }

            // The core helper selects the oldest backup-area record, so remove the previous archive first.
            get_file_storage()->delete_area_files($context->id, 'backup', 'course');
            $backupid = $this->perform_backup($course);
            $this->perform_restore($backupid, $target);

            $charts = $DB->get_records('qtype_accounting_charts', ['contextid' => $targetcontext->id]);
            $this->assertCount(1, $charts);
            $restoredchart = reset($charts);
            $this->assertNotEquals($chartid, $restoredchart->id);
            if ($reusechart) {
                $this->assertEquals($existingchartid, $restoredchart->id);
            }
            $accounts = account_manager::get_for_chart($restoredchart->id);
            $this->assertCount(3, $accounts);
            $accountsbyname = [];
            foreach ($accounts as $account) {
                $accountsbyname[$account->accountname] = (int) $account->id;
            }

            $childcontext = $DB->sql_like('ctx.path', ':contextpath');
            $questions = $DB->get_records_sql(
                "SELECT q.*
                   FROM {question} q
                   JOIN {question_versions} v ON v.questionid = q.id
                   JOIN {question_bank_entries} b ON b.id = v.questionbankentryid
                   JOIN {question_categories} c ON c.id = b.questioncategoryid
                   JOIN {context} ctx ON ctx.id = c.contextid
                  WHERE (ctx.id = :context OR {$childcontext}) AND q.qtype = :qtype",
                ['context' => $targetcontext->id, 'contextpath' => $targetcontext->path . '/%', 'qtype' => 'accounting']
            );
            $this->assertCount(2, $questions);
            foreach ($questions as $question) {
                $loaded = \question_bank::load_question($question->id);
                $this->assertEquals($restoredchart->id, $loaded->chartofaccountsid);
                $this->assertSame($accountsbyname['1200 Bank'], $loaded->entries[0]['debitaccountid']);
                $this->assertEquals(123.45, $loaded->entries[0]['debitamount']);
                $this->assertEquals(2, $loaded->entries[0]['weight_debitaccount']);
                if ($question->name === 'Compound sale') {
                    $this->assertCount(2, $loaded->entries);
                    $this->assertNull($loaded->entries[0]['creditaccountid']);
                    $this->assertNull($loaded->entries[1]['debitaccountid']);
                    $creditentry = $loaded->entries[1];
                } else {
                    $this->assertCount(1, $loaded->entries);
                    $creditentry = $loaded->entries[0];
                }
                $this->assertSame($accountsbyname['8400 Sales'], $creditentry['creditaccountid']);
                $this->assertEquals(123.45, $creditentry['creditamount']);
                $this->assertSame('1200 Bank', $loaded->accountsmap[$loaded->entries[0]['debitaccountid']]);
                $this->assertSame('8400 Sales', $loaded->accountsmap[$creditentry['creditaccountid']]);
            }
        }
    }

    /**
     * Cases for creating or reusing the target chart.
     *
     * @return array
     */
    public static function chart_reuse_provider(): array {
        return ['new chart' => [false], 'existing chart' => [true]];
    }
}
