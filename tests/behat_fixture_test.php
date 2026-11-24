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
require_once($CFG->dirroot . '/question/type/accounting/tests/behat/behat_qtype_accounting.php');

#[\PHPUnit\Framework\Attributes\CoversMethod(\behat_qtype_accounting::class, 'accounting_questions_exist')]
/**
 * Test that question fixtures give each question its own accounts.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \behat_qtype_accounting::accounting_questions_exist
 */
final class behat_fixture_test extends \advanced_testcase {
    /**
     * The question offers the standard accounts and the accounts of its answer, which the entries reference.
     */
    public function test_question_gets_own_accounts(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $steps = new \behat_qtype_accounting();
        $steps->accounting_questions_exist(new \Behat\Gherkin\Node\TableNode([
            ['questioncategory', 'name', 'debitaccount', 'creditaccount'],
            [$category->name, 'Module bank accounting question', '0010 Bank', '8400'],
        ]));

        $questionid = $DB->get_field('question', 'id', ['name' => 'Module bank accounting question'], MUST_EXIST);
        $accounts = array_map(fn($account) => $account->accountname, question_accounts::get((int)$questionid));
        $this->assertSame([1 => '1200', 2 => '8400', 3 => '9998', 4 => '9999', 5 => '0010 Bank'], $accounts);
        $entry = $DB->get_record('qtype_accounting_entries', ['questionid' => $questionid], '*', MUST_EXIST);
        $this->assertEquals([5, 2], [$entry->debitaccountid, $entry->creditaccountid]);
        $this->assertSame(0, $DB->count_records('qtype_accounting_charts'));
    }
}
