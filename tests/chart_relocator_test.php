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
require_once($CFG->libdir . '/questionlib.php');

/**
 * Tests that charts follow their questions into other question banks.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\chart_relocator
 * @covers     \qtype_accounting\chart_manager
 */
final class chart_relocator_test extends \advanced_testcase {
    /**
     * Create a question bank category in a new course.
     *
     * @return \stdClass The category, with its context in ->bankcontext.
     */
    private function create_bank_category(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $bankcontext = chart_manager::get_chart_context(\context_course::instance($course->id));
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $bankcontext->id]);
        $category->bankcontext = $bankcontext;
        return $category;
    }

    /**
     * Create an accounting question using a chart with the accounts Bank and Sales.
     *
     * @param \stdClass $category The question category.
     * @param int $chartid The chart ID.
     * @return \stdClass The question.
     */
    private function create_question(\stdClass $category, int $chartid): \stdClass {
        $accounts = array_flip(array_map(fn($a) => $a->accountname, account_manager::get_for_chart($chartid)));
        return $this->getDataGenerator()->get_plugin_generator('core_question')->create_question('accounting', null, [
            'category' => $category->id, 'chartofaccountsid' => $chartid,
            'debitaccount' => [$accounts['Bank']], 'creditaccount' => [$accounts['Sales']],
            'debitamount' => [100], 'creditamount' => [100],
        ]);
    }

    /**
     * Create a chart with the accounts Bank and Sales.
     *
     * @param int $contextid The context ID.
     * @return int The chart ID.
     */
    private function create_chart(int $contextid): int {
        $chartid = chart_manager::create_chart('Main', $contextid);
        account_manager::add($chartid, 'Bank', 1);
        account_manager::add($chartid, 'Sales', 2);
        return $chartid;
    }

    /**
     * Save an attempt that answers a question with its correct accounts.
     *
     * @param \stdClass $question The question.
     * @return int The question usage ID.
     */
    private function save_attempt(\stdClass $question): int {
        $quba = \question_engine::make_questions_usage_by_activity('core_question_preview', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id));
        $quba->start_all_questions();
        $correct = $quba->get_question($slot)->get_correct_response();
        $quba->process_action($slot, $correct);
        \question_engine::save_questions_usage_by_activity($quba);
        return $quba->get_id();
    }

    /**
     * Get the account IDs saved in a question usage.
     *
     * @param int $qubaid The question usage ID.
     * @return int[] The saved account IDs.
     */
    private function saved_account_ids(int $qubaid): array {
        global $DB;
        $values = $DB->get_fieldset_sql(
            "SELECT d.value
               FROM {question_attempt_step_data} d
               JOIN {question_attempt_steps} s ON s.id = d.attemptstepid
               JOIN {question_attempts} qa ON qa.id = s.questionattemptid
              WHERE qa.questionusageid = ? AND " . $DB->sql_like('d.name', '?'),
            [$qubaid, '%account\_%']
        );
        sort($values);
        return array_map('intval', $values);
    }

    /**
     * Moving a question copies its chart into the new bank, once, and switches its attempts.
     */
    public function test_moving_questions_copies_the_chart(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->create_bank_category();
        $target = $this->create_bank_category();
        $chartid = $this->create_chart($source->bankcontext->id);
        $first = $this->create_question($source, $chartid);
        $second = $this->create_question($source, $chartid);
        $qubaid = $this->save_attempt($first);
        $oldids = array_map('intval', array_keys(account_manager::get_for_chart($chartid)));
        sort($oldids);
        $this->assertSame($oldids, $this->saved_account_ids($qubaid));

        question_move_questions_to_category([$first->id, $second->id], $target->id);

        $newchartid = (int)$DB->get_field('qtype_accounting_options', 'chartofaccountsid', ['questionid' => $first->id]);
        $this->assertNotSame($chartid, $newchartid);
        $this->assertSame($newchartid, (int)$DB->get_field(
            'qtype_accounting_options',
            'chartofaccountsid',
            ['questionid' => $second->id]
        ));
        $this->assertEquals($target->bankcontext->id, chart_manager::get_chart($newchartid)->contextid);
        $this->assertEquals($source->bankcontext->id, chart_manager::get_chart($chartid)->contextid);

        $newids = array_map('intval', array_keys(account_manager::get_for_chart($newchartid)));
        sort($newids);
        $entry = $DB->get_record('qtype_accounting_entries', ['questionid' => $first->id]);
        $this->assertContains((int)$entry->debitaccountid, $newids);
        $this->assertContains((int)$entry->creditaccountid, $newids);
        $this->assertSame($newids, $this->saved_account_ids($qubaid));

        // The moved question still grades its saved attempt as correct.
        $quba = \question_engine::load_questions_usage_by_activity($qubaid);
        $quba->finish_all_questions();
        $this->assertEquals(1, $quba->get_question_fraction(1));
    }

    /**
     * Saving a question into another question bank gives the new version a chart there.
     */
    public function test_saving_into_another_bank_copies_the_chart(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->create_bank_category();
        $target = $this->create_bank_category();
        $chartid = $this->create_chart($source->bankcontext->id);
        $question = $this->create_question($source, $chartid);
        $accounts = array_flip(array_map(fn($a) => $a->accountname, account_manager::get_for_chart($chartid)));

        $saved = $this->getDataGenerator()->get_plugin_generator('core_question')->update_question($question, null, [
            'category' => $target->id, 'chartofaccountsid' => $chartid,
            'debitaccount' => [$accounts['Bank']], 'creditaccount' => [$accounts['Sales']],
            'debitamount' => [100], 'creditamount' => [100],
        ]);

        $newchartid = (int)$DB->get_field('qtype_accounting_options', 'chartofaccountsid', ['questionid' => $saved->id]);
        $this->assertEquals($target->bankcontext->id, chart_manager::get_chart($newchartid)->contextid);
        $entry = $DB->get_record('qtype_accounting_entries', ['questionid' => $saved->id]);
        $this->assertSame('Bank', $DB->get_field('qtype_accounting_accounts', 'accountname', [
            'id' => $entry->debitaccountid, 'chartid' => $newchartid,
        ]));
    }

    /**
     * Site charts are shared by all question banks, so they never move.
     */
    public function test_site_charts_stay(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $source = $this->create_bank_category();
        $target = $this->create_bank_category();
        $chartid = $this->create_chart(\context_system::instance()->id);
        $question = $this->create_question($source, $chartid);

        question_move_questions_to_category([$question->id], $target->id);

        $options = $DB->get_record('qtype_accounting_options', ['questionid' => $question->id]);
        $this->assertEquals($chartid, $options->chartofaccountsid);
        $this->assertSame(1, $DB->count_records('qtype_accounting_charts'));
    }

    /**
     * The upgrade moves charts out of deleted contexts into the bank of their questions.
     */
    public function test_relocate_all_moves_unreachable_charts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->create_bank_category();
        $deletedcontextid = (int)$DB->get_field_sql('SELECT MAX(id) FROM {context}') + 1000;
        $used = $this->create_chart($deletedcontextid);
        $this->create_question($category, $used);
        $unused = $this->create_chart($deletedcontextid);

        chart_relocator::relocate_all();

        $this->assertEquals($category->bankcontext->id, chart_manager::get_chart($used)->contextid);
        $this->assertFalse(chart_manager::get_chart($unused));
    }

    /**
     * Course contexts hold questions only up to Moodle 4.5; from 5.0 they map to the course's question bank.
     */
    public function test_chart_context_of_a_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $chartcontext = chart_manager::get_chart_context($coursecontext);
        if (chart_manager::questions_in_modules_only()) {
            $this->assertEquals(CONTEXT_MODULE, $chartcontext->contextlevel);
            $this->assertEquals($coursecontext->id, $chartcontext->get_course_context()->id);
        } else {
            $this->assertEquals($coursecontext->id, $chartcontext->id);
        }
        $this->assertEquals(\context_system::instance()->id, chart_manager::get_chart_context(\context_system::instance())->id);
    }
}
