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

        $this->assertEquals('1200 Bank', $response['sollkonto_0']);
        $this->assertEquals(1000.00, $response['sollbetrag_0']);
        $this->assertEquals('8400 Erlöse 19% USt', $response['habenkonto_0']);
        $this->assertEquals(1000.00, $response['habenbetrag_0']);
    }

    /**
     * Test is_complete_response with complete response.
     */
    public function test_is_complete_response_complete(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);

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
     * Test is_complete_response with only debit account filled.
     * A response is considered complete if any account field is filled.
     */
    public function test_is_complete_response_only_debit(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '', 0);

        $this->assertTrue($question->is_complete_response($response));
    }

    /**
     * Test is_complete_response with only credit account filled.
     * A response is considered complete if any account field is filled.
     */
    public function test_is_complete_response_only_credit(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('', 0, '8400 Erlöse 19% USt', 1000);

        $this->assertTrue($question->is_complete_response($response));
    }

    /**
     * Test is_same_response with identical responses.
     */
    public function test_is_same_response_identical(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response1 = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
        $response2 = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);

        $this->assertTrue($question->is_same_response($response1, $response2));
    }

    /**
     * Test is_same_response with different responses.
     */
    public function test_is_same_response_different(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response1 = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
        $response2 = qtype_buchungssatz_test_helper::make_response('1200 Bank', 500, '8400 Erlöse 19% USt', 500);

        $this->assertFalse($question->is_same_response($response1, $response2));
    }

    /**
     * Test grading a correct response.
     */
    public function test_grade_response_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
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
     *
     * With aggregation-based grading, correct accounts earn partial credit
     * even when amounts are wrong. 2 accounts correct + 0 amounts correct = 50%.
     */
    public function test_grade_response_wrong_amounts(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        // Correct accounts but wrong amounts.
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 500, '8400 Erlöse 19% USt', 500);
        [$fraction, $state] = $question->grade_response($response);

        // Accounts correct (weight 1+1=2), amounts wrong (0).
        // Total weight = 4, earned = 2, fraction = 0.5.
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
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
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
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
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000.005, '8400 Erlöse 19% USt', 1000.005);
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
            ['sollkonto' => '1000 Kasse', 'sollbetrag' => 500, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 500, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
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
            ['sollkonto' => '1000 Kasse', 'sollbetrag' => 500, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
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
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 500, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
            ['sollkonto' => '1000 Kasse', 'sollbetrag' => 500, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
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

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
        $summary = $question->summarise_response($response);

        $this->assertStringContainsString('1200 Bank', $summary);
        $this->assertStringContainsString('8400 Erlöse 19% USt', $summary);
        $this->assertStringContainsString('1000.00', $summary);
    }

    /**
     * Test grading with empty entries array.
     */
    public function test_grade_response_no_correct_entries(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');
        $question->entries = [];

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
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

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
        $error = $question->get_validation_error($response);

        $this->assertEmpty($error);
    }

    // ========================================
    // Aggregation-based grading tests
    // ========================================

    /**
     * Test that amounts are aggregated by account - split amounts.
     *
     * Correct: Bank 600 / Revenue 600
     * Student enters: Bank 300 + Bank 300 / Revenue 300 + Revenue 300
     * Should be 100% correct because aggregated totals match.
     */
    public function test_aggregation_split_amounts_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'split_amounts');

        // Student splits the amount into two entries.
        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 300, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 300],
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 300, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 300],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test aggregation with unequal splits.
     *
     * Correct: Bank 600 / Revenue 600
     * Student enters: Bank 400 + Bank 200 / Revenue 100 + Revenue 500
     * Should be 100% correct because aggregated totals match.
     */
    public function test_aggregation_unequal_splits_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'split_amounts');

        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 400, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 100],
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 200, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test aggregation with wrong total amount.
     *
     * Correct: Bank 600 / Revenue 600
     * Student enters: Bank 300 + Bank 200 = 500 (wrong total)
     */
    public function test_aggregation_wrong_total(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'split_amounts');

        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 300, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 300],
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 200, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 200],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // Correct accounts but wrong amounts = 50% (2 accounts correct, 2 amounts wrong).
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test multiple entries to same account are aggregated.
     *
     * Correct: Bank 300 + Bank 200 = Bank 500 total / Revenue 500 total
     * Student enters: Bank 500 / Revenue 500 (as single entry)
     * Should be 100% correct.
     */
    public function test_aggregation_multiple_same_account(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'multiple_same_account');

        // Student enters single entry with total.
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 500, '8400 Erlöse 19% USt', 500);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    // ========================================
    // All-or-nothing grading tests
    // ========================================

    /**
     * Test all-or-nothing grading with correct answer.
     */
    public function test_all_or_nothing_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'all_or_nothing');

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test all-or-nothing grading with partially correct answer.
     * Should get 0 because all-or-nothing is enabled.
     */
    public function test_all_or_nothing_partial_gets_zero(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'all_or_nothing');

        // Correct accounts but wrong amounts.
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 500, '8400 Erlöse 19% USt', 500);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test all-or-nothing grading with one correct account.
     * Should get 0 because all-or-nothing is enabled.
     */
    public function test_all_or_nothing_one_account_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'all_or_nothing');

        // Only debit correct.
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '9999', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test that partial credit works when all-or-nothing is disabled.
     */
    public function test_partial_credit_when_not_all_or_nothing(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');
        $question->allornothinggrading = 0;

        // Correct accounts but wrong amounts = 50% (accounts correct, amounts wrong).
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 500, '8400 Erlöse 19% USt', 500);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    // ========================================
    // Weighted scoring tests
    // ========================================

    /**
     * Test weighted scoring with correct answer.
     */
    public function test_weighted_scoring_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'weighted_entries');

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test weighted scoring - accounts correct, amounts wrong.
     *
     * Weights: sollkonto=2, sollbetrag=1, habenkonto=2, habenbetrag=1 (total=6)
     * Correct accounts = 2+2 = 4 points
     * Wrong amounts = 0 points
     * Fraction = 4/6 = 0.6667
     */
    public function test_weighted_scoring_accounts_correct_amounts_wrong(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'weighted_entries');

        // Correct accounts but wrong amounts.
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 500, '8400 Erlöse 19% USt', 500);
        [$fraction, $state] = $question->grade_response($response);

        // Accounts have weight 2 each, amounts weight 1 each.
        // Total weight = 2+1+2+1 = 6
        // Earned = 2+0+2+0 = 4
        // Fraction = 4/6 = 0.6667
        $this->assertEqualsWithDelta(0.6667, $fraction, 0.001);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test weighted scoring - only debit side correct.
     *
     * Weights: sollkonto=2, sollbetrag=1, habenkonto=2, habenbetrag=1 (total=6)
     * Debit correct = 2+1 = 3 points
     * Credit wrong = 0 points
     * Fraction = 3/6 = 0.5
     */
    public function test_weighted_scoring_debit_only_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'weighted_entries');

        // Only debit side correct.
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '9999', 9999);
        [$fraction, $state] = $question->grade_response($response);

        // Debit = 2+1 = 3, Credit = 0
        // Fraction = 3/6 = 0.5
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test weighted scoring - only credit side correct.
     */
    public function test_weighted_scoring_credit_only_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'weighted_entries');

        // Only credit side correct.
        $response = qtype_buchungssatz_test_helper::make_response('9999', 9999, '8400 Erlöse 19% USt', 1000);
        [$fraction, $state] = $question->grade_response($response);

        // Debit = 0, Credit = 2+1 = 3
        // Fraction = 3/6 = 0.5
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    // ========================================
    // Order independence tests
    // ========================================

    /**
     * Test that entry order doesn't matter.
     */
    public function test_order_independence(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'multiple_entries');

        // Reverse order of entries.
        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 500, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
            ['sollkonto' => '1000 Kasse', 'sollbetrag' => 500, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test mixed order with aggregation.
     *
     * Student enters entries in random order with splits.
     */
    public function test_order_independence_with_splits(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'multiple_same_account');

        // Random order and splits.
        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 150, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 250],
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 200, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 100],
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 150, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 150],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // Total: Bank 500 / Revenue 500 - correct!
        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    // ========================================
    // Partial correctness tests
    // ========================================

    /**
     * Test correct accounts but wrong amounts gives partial credit.
     */
    public function test_partial_credit_correct_accounts_wrong_amounts(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 500, '8400 Erlöse 19% USt', 500);
        [$fraction, $state] = $question->grade_response($response);

        // All 4 weights = 1, total = 4
        // Accounts correct = 2, amounts wrong = 0
        // Fraction = 2/4 = 0.5
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test only one side correct.
     */
    public function test_partial_credit_one_side_correct(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        // Debit side correct, credit side completely wrong.
        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '9999', 9999);
        [$fraction, $state] = $question->grade_response($response);

        // Debit correct = 2, Credit = 0
        // Fraction = 2/4 = 0.5
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test missing account on one side.
     */
    public function test_partial_credit_missing_account(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        // Missing debit account entirely - using different account.
        $response = qtype_buchungssatz_test_helper::make_response('9999', 1000, '8400 Erlöse 19% USt', 1000);
        [$fraction, $state] = $question->grade_response($response);

        // Credit side correct = 2, Debit wrong = 0
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    // ========================================
    // Edge cases
    // ========================================

    /**
     * Test empty student response.
     */
    public function test_grade_empty_response(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        $response = qtype_buchungssatz_test_helper::make_response('', 0, '', 0);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test student provides extra entries beyond correct answer.
     */
    public function test_extra_entries_ignored(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');

        // Correct answer plus extra wrong entry.
        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 1000, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 1000],
            ['sollkonto' => '9999', 'sollbetrag' => 500, 'habenkonto' => '9998', 'habenbetrag' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // Extra entries don't affect correct answer matching.
        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test debit-only entry (no credit account in correct answer).
     */
    public function test_debit_only_entry(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'simple_debit_credit');
        // Modify to have debit-only entry.
        $question->entries = [
            [
                'sollkonto' => '1200 Bank',
                'sollbetrag' => 1000.00,
                'habenkonto' => '',
                'habenbetrag' => 0,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => '',
            ],
        ];

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '', 0);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test credit-only entry (no debit account in correct answer).
     */
    public function test_credit_only_entry(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'debit_only_optional');

        $response = qtype_buchungssatz_test_helper::make_response('', 0, '4400 Verbindlichkeiten', 250);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    // ========================================
    // Extra entry deduction tests
    // ========================================

    /**
     * Test extra entry deduction with no extra accounts.
     *
     * Correct answer only, no extras. Fraction should be 1.0.
     */
    public function test_extra_entry_deduction_no_extras(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'extra_entry_deduction');

        $response = qtype_buchungssatz_test_helper::make_response('1200 Bank', 1000, '8400 Erlöse 19% USt', 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test extra entry deduction with extra accounts.
     *
     * Correct answer plus one extra debit account and one extra credit account.
     * Deduction = 2 * 5% = 10%. Fraction = 1.0 - 0.10 = 0.90.
     */
    public function test_extra_entry_deduction_with_extras(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'extra_entry_deduction');

        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 1000, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 1000],
            ['sollkonto' => '1000 Kasse', 'sollbetrag' => 100, 'habenkonto' => '4400 Verbindlichkeiten', 'habenbetrag' => 100],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // 2 extra accounts (1 debit + 1 credit) * 5% = 10% deduction.
        $this->assertEqualsWithDelta(0.9, $fraction, 0.001);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test extra entry deduction caps at zero.
     *
     * Many extra accounts so deduction exceeds earned fraction.
     * Fraction should be 0, not negative.
     */
    public function test_extra_entry_deduction_caps_at_zero(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'extra_entry_deduction');
        // Set high deduction so it exceeds possible score.
        $question->extraentrydeduction = 50;

        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 1000, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 1000],
            ['sollkonto' => '1000 Kasse', 'sollbetrag' => 100, 'habenkonto' => '4400 Verbindlichkeiten', 'habenbetrag' => 100],
            ['sollkonto' => '2000 Forderungen', 'sollbetrag' => 50, 'habenkonto' => '3000 Rückstellungen', 'habenbetrag' => 50],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // 4 extra accounts * 50% = 200% deduction, but capped at 0.
        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test that extra entry deduction is disabled when set to 0.
     *
     * This verifies backward compatibility: extraentrydeduction = 0 means
     * extra entries are ignored as before.
     */
    public function test_extra_entry_deduction_disabled(): void {
        $question = \test_question_maker::make_question('buchungssatz', 'extra_entry_deduction');
        $question->extraentrydeduction = 0;

        $response = qtype_buchungssatz_test_helper::make_multi_response([
            ['sollkonto' => '1200 Bank', 'sollbetrag' => 1000, 'habenkonto' => '8400 Erlöse 19% USt', 'habenbetrag' => 1000],
            ['sollkonto' => '1000 Kasse', 'sollbetrag' => 100, 'habenkonto' => '4400 Verbindlichkeiten', 'habenbetrag' => 100],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // No deduction when extraentrydeduction is 0.
        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }
}
