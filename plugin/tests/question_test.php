<?php
// This file is part of MoFT BuSa - Moodle Question Type Buchungssatz.
//
// MoFT BuSa is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// MoFT BuSa is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with MoFT BuSa.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_buchungssatz;

use qtype_buchungssatz_question;
use qtype_buchungssatz_test_helper;
use question_state;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/buchungssatz/tests/helper.php');
require_once($CFG->dirroot . '/question/type/buchungssatz/question.php');

/**
 * Unit tests for qtype_buchungssatz_question.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_buchungssatz_question
 */
class question_test extends \advanced_testcase {

    /**
     * Test getting expected data keys.
     */
    public function test_get_expected_data(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $expected = $question->get_expected_data();

        $this->assertArrayHasKey('sollkonto_0', $expected);
        $this->assertArrayHasKey('sollbetrag_0', $expected);
        $this->assertArrayHasKey('habenkonto_0', $expected);
        $this->assertArrayHasKey('habenbetrag_0', $expected);
        $this->assertEquals(PARAM_TEXT, $expected['sollkonto_0']);
        $this->assertEquals(PARAM_RAW, $expected['sollbetrag_0']);
    }

    /**
     * Test getting the correct response.
     */
    public function test_get_correct_response(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = $question->get_correct_response();

        $this->assertEquals('1200', $response['sollkonto_0']);
        $this->assertEquals(1000.00, $response['sollbetrag_0']);
        $this->assertEquals('8400', $response['habenkonto_0']);
        $this->assertEquals(1000.00, $response['habenbetrag_0']);
    }

    /**
     * Test is_complete_response with complete response.
     */
    public function test_is_complete_response_complete(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);

        $this->assertTrue($question->is_complete_response($response));
    }

    /**
     * Test is_complete_response with empty response.
     */
    public function test_is_complete_response_empty(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = [];

        $this->assertFalse($question->is_complete_response($response));
    }

    /**
     * Test is_complete_response with only debit account.
     */
    public function test_is_complete_response_only_debit(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200', 1000, '', 0);

        $this->assertFalse($question->is_complete_response($response));
    }

    /**
     * Test is_same_response with identical responses.
     */
    public function test_is_same_response_identical(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response1 = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);
        $response2 = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);

        $this->assertTrue($question->is_same_response($response1, $response2));
    }

    /**
     * Test is_same_response with different responses.
     */
    public function test_is_same_response_different(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response1 = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);
        $response2 = qtype_buchungssatz_test_helper::make_response('1200', 500, '8400', 500);

        $this->assertFalse($question->is_same_response($response1, $response2));
    }

    /**
     * Test grading a correct response.
     */
    public function test_grade_response_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test grading a completely wrong response.
     */
    public function test_grade_response_wrong(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('9999', 1000, '9998', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test grading with wrong amounts.
     */
    public function test_grade_response_wrong_amounts(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        // Correct accounts but wrong amounts.
        $response = qtype_buchungssatz_test_helper::make_response('1200', 500, '8400', 500);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test grading with case-insensitive account matching.
     */
    public function test_grade_response_case_insensitive(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');
        // Change entries to use text account names.
        $question->entries = [
            [
                'sollkonto' => 'Bank',
                'sollbetrag' => 1000.00,
                'habenkonto' => 'Revenue',
                'habenbetrag' => 1000.00,
                'fraction' => 1.0,
                'explanation' => '',
            ],
        ];

        // Response with different case.
        $response = qtype_buchungssatz_test_helper::make_response('BANK', 1000, 'revenue', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test grading with floating point tolerance.
     */
    public function test_grade_response_float_tolerance(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        // Response with tiny floating point difference.
        $response = qtype_buchungssatz_test_helper::make_response('1200', 1000.005, '8400', 1000.005);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test grading with multiple entries - all correct.
     */
    public function test_grade_response_multiple_entries_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'multiple_entries');

        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1000', 'sollbetrag' => 500, 'habenkonto' => '8400', 'habenbetrag' => 500],
            ['sollkonto' => '1200', 'sollbetrag' => 500, 'habenkonto' => '8400', 'habenbetrag' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test grading with multiple entries - partial credit.
     */
    public function test_grade_response_multiple_entries_partial(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'multiple_entries');

        // Only one of two entries is correct.
        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1000', 'sollbetrag' => 500, 'habenkonto' => '8400', 'habenbetrag' => 500],
            ['sollkonto' => '9999', 'sollbetrag' => 500, 'habenkonto' => '9998', 'habenbetrag' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test grading with multiple entries in different order.
     */
    public function test_grade_response_multiple_entries_different_order(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'multiple_entries');

        // Same entries but in reverse order - should still be correct.
        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200', 'sollbetrag' => 500, 'habenkonto' => '8400', 'habenbetrag' => 500],
            ['sollkonto' => '1000', 'sollbetrag' => 500, 'habenkonto' => '8400', 'habenbetrag' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test summarise_response.
     */
    public function test_summarise_response(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);
        $summary = $question->summarise_response($response);

        $this->assertStringContainsString('1200', $summary);
        $this->assertStringContainsString('8400', $summary);
        $this->assertStringContainsString('1000.00', $summary);
    }

    /**
     * Test grading with empty entries array.
     */
    public function test_grade_response_no_correct_entries(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');
        $question->entries = [];

        $response = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
    }

    /**
     * Test validation error for incomplete response.
     */
    public function test_get_validation_error_incomplete(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = [];
        $error = $question->get_validation_error($response);

        $this->assertNotEmpty($error);
    }

    /**
     * Test validation error for complete response.
     */
    public function test_get_validation_error_complete(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200', 1000, '8400', 1000);
        $error = $question->get_validation_error($response);

        $this->assertEmpty($error);
    }
}
