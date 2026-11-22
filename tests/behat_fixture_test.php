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
 * Test that question fixtures put charts into the question bank of their category.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \behat_qtype_accounting::accounting_questions_exist
 */
final class behat_fixture_test extends \advanced_testcase {
    /**
     * A module-owned question uses a chart of its own question bank, not of the course.
     */
    public function test_module_question_chart_belongs_to_question_bank(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $modulecontext = \context_module::instance($quiz->cmid);
        $coursecontext = \context_course::instance($course->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => $modulecontext->id]);
        $steps = new \behat_qtype_accounting();
        $steps->accounting_questions_exist(new \Behat\Gherkin\Node\TableNode([
            ['questioncategory', 'name', 'debitaccount', 'creditaccount'],
            [$category->name, 'Module bank accounting question', '1200', '8400'],
        ]));

        $chart = chart_manager::get_chart_by_name('Accounting test chart', $modulecontext->id);
        $this->assertNotFalse($chart);
        $this->assertFalse(chart_manager::get_chart_by_name('Accounting test chart', $coursecontext->id));
        $questionid = $DB->get_field('question', 'id', ['name' => 'Module bank accounting question'], MUST_EXIST);
        $this->assertEquals(
            $chart->id,
            $DB->get_field('qtype_accounting_options', 'chartofaccountsid', ['questionid' => $questionid], MUST_EXIST)
        );
    }
}
