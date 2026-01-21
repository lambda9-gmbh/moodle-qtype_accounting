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

namespace qtype_buchungssatz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/buchungssatz/questiontype.php');

/**
 * Unit tests for qtype_buchungssatz (question type class).
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_buchungssatz
 */
class questiontype_test extends \advanced_testcase {

    /** @var \qtype_buchungssatz The question type instance. */
    protected $qtype;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->qtype = new \qtype_buchungssatz();
    }

    /**
     * Test the name method.
     */
    public function test_name(): void {
        $this->assertEquals('buchungssatz', $this->qtype->name());
    }

    /**
     * Test is_usable_by_random returns false.
     */
    public function test_is_usable_by_random(): void {
        $this->assertFalse($this->qtype->is_usable_by_random());
    }

    /**
     * Test get_random_guess_score returns 0.
     */
    public function test_get_random_guess_score(): void {
        $questiondata = new \stdClass();
        $this->assertEquals(0, $this->qtype->get_random_guess_score($questiondata));
    }

    /**
     * Test extra_question_fields returns correct fields.
     */
    public function test_extra_question_fields(): void {
        $fields = $this->qtype->extra_question_fields();

        $this->assertContains('qtype_buchungssatz_options', $fields);
        $this->assertContains('chartofaccountsid', $fields);
        $this->assertContains('allowmultipleentries', $fields);
        $this->assertContains('maxentries', $fields);
    }

    /**
     * Test saving and loading question options.
     */
    public function test_save_and_get_question_options(): void {
        $this->resetAfterTest(true);

        // Create a question.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'Test Buchungssatz',
            'questiontext' => ['text' => 'Test question text', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => 123,
            'allowmultipleentries' => 1,
            'maxentries' => 10,
            'sollkonto' => ['1200', '1000'],
            'sollbetrag' => [1000.00, 500.00],
            'habenkonto' => ['8400', '8400'],
            'habenbetrag' => [1000.00, 500.00],
            'grade' => [60, 40],
            'explanation' => ['First entry', 'Second entry'],
        ];

        // Save the question.
        $question = $generator->create_question('buchungssatz', null, $questiondata);

        // Reload the question.
        $loadedquestion = \question_bank::load_question($question->id);

        $this->assertEquals('buchungssatz', $loadedquestion->qtype->name());
        $this->assertEquals(123, $loadedquestion->chartofaccountsid);
        $this->assertEquals(1, $loadedquestion->allowmultipleentries);
        $this->assertEquals(10, $loadedquestion->maxentries);
        $this->assertCount(2, $loadedquestion->entries);

        // Check first entry.
        $this->assertEquals('1200', $loadedquestion->entries[0]['sollkonto']);
        $this->assertEquals(1000.00, $loadedquestion->entries[0]['sollbetrag']);
        $this->assertEquals('8400', $loadedquestion->entries[0]['habenkonto']);
        $this->assertEquals(1000.00, $loadedquestion->entries[0]['habenbetrag']);
        $this->assertEquals(0.6, $loadedquestion->entries[0]['fraction']);

        // Check second entry.
        $this->assertEquals('1000', $loadedquestion->entries[1]['sollkonto']);
        $this->assertEquals(500.00, $loadedquestion->entries[1]['sollbetrag']);
        $this->assertEquals(0.4, $loadedquestion->entries[1]['fraction']);
    }

    /**
     * Test deleting a question removes all related data.
     */
    public function test_delete_question(): void {
        global $DB;

        $this->resetAfterTest(true);

        // Create a question.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'Test Buchungssatz',
            'questiontext' => ['text' => 'Test question text', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 0,
            'maxentries' => 1,
            'sollkonto' => ['1200'],
            'sollbetrag' => [1000.00],
            'habenkonto' => ['8400'],
            'habenbetrag' => [1000.00],
            'grade' => [100],
            'explanation' => [''],
        ];

        $question = $generator->create_question('buchungssatz', null, $questiondata);

        // Verify records exist.
        $this->assertTrue($DB->record_exists('qtype_buchungssatz_options', ['questionid' => $question->id]));
        $this->assertTrue($DB->record_exists('qtype_buchungssatz_entries', ['questionid' => $question->id]));

        // Delete the question.
        question_delete_question($question->id);

        // Verify records are deleted.
        $this->assertFalse($DB->record_exists('qtype_buchungssatz_options', ['questionid' => $question->id]));
        $this->assertFalse($DB->record_exists('qtype_buchungssatz_entries', ['questionid' => $question->id]));
    }

    /**
     * Test saving question with empty credit account skips entry.
     */
    public function test_save_question_skips_empty_entries(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'Test Buchungssatz',
            'questiontext' => ['text' => 'Test question text', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 1,
            'maxentries' => 5,
            'sollkonto' => ['1200', '', '1000'],
            'sollbetrag' => [1000.00, 0, 500.00],
            'habenkonto' => ['8400', '', '8400'],  // Second entry is empty.
            'habenbetrag' => [1000.00, 0, 500.00],
            'grade' => [60, 0, 40],
            'explanation' => ['', '', ''],
        ];

        $question = $generator->create_question('buchungssatz', null, $questiondata);

        // Should only have 2 entries (skipping the empty one).
        $entries = $DB->get_records('qtype_buchungssatz_entries', ['questionid' => $question->id]);
        $this->assertCount(2, $entries);
    }

    /**
     * Test get_possible_responses returns empty array.
     */
    public function test_get_possible_responses(): void {
        $questiondata = new \stdClass();
        $responses = $this->qtype->get_possible_responses($questiondata);

        $this->assertIsArray($responses);
        $this->assertEmpty($responses);
    }
}
