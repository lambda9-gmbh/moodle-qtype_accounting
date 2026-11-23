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

use qtype_accounting_question;
use qtype_accounting_test_helper;
use question_state;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/accounting/tests/helper.php');
require_once($CFG->dirroot . '/question/type/accounting/question.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting_question::class)]
/**
 * Unit tests for qtype_accounting_question.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting_question
 */
final class question_test extends \advanced_testcase {
    /**
     * Hint files follow interactive hint progression and feedback visibility.
     */
    public function test_hint_file_access_follows_applicable_hint(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $question->hints = [
            new \question_hint_with_parts(17, 'First hint', FORMAT_HTML, true, true),
            new \question_hint_with_parts(18, 'Second hint', FORMAT_HTML, false, false),
        ];
        $usage = \question_engine::make_questions_usage_by_activity('core_question_preview', \context_system::instance());
        $usage->set_preferred_behaviour('interactive');
        $slot = $usage->add_question($question);
        $usage->start_all_questions();
        $qa = $usage->get_question_attempt($slot);
        $options = new \question_display_options();
        $options->feedback = \question_display_options::VISIBLE;
        $this->assertFalse($question->check_file_access($qa, $options, 'question', 'hint', [17, 'hint.png'], false));

        $response = $question->get_correct_response();
        $response['debitamount_0'] = '12,50';
        $usage->process_action($slot, $response + ['-submit' => 1]);
        $this->assertEquals(17, $qa->get_applicable_hint()->id);
        $this->assertTrue($question->check_file_access($qa, $options, 'question', 'hint', [17, 'hint.png'], false));
        $this->assertFalse($question->check_file_access($qa, $options, 'question', 'hint', [18, 'hint.png'], false));
        $options->feedback = \question_display_options::HIDDEN;
        $this->assertFalse($question->check_file_access($qa, $options, 'question', 'hint', [17, 'hint.png'], false));

        $options->feedback = \question_display_options::VISIBLE;
        $usage->process_action($slot, ['-tryagain' => 1]);
        $this->assertFalse($question->check_file_access($qa, $options, 'question', 'hint', [17, 'hint.png'], false));
        $response['debitamount_0'] = '25,00';
        $usage->process_action($slot, $response + ['-submit' => 1]);
        $this->assertEquals(18, $qa->get_applicable_hint()->id);
        $this->assertTrue($question->check_file_access($qa, $options, 'question', 'hint', [18, 'hint.png'], false));
        $this->assertFalse($question->check_file_access($qa, $options, 'question', 'hint', [17, 'hint.png'], false));

        // Other file areas still use the standard question access checks.
        $this->assertTrue($question->check_file_access($qa, $options, 'question', 'questiontext', [$question->id], false));
        $this->assertFalse($question->check_file_access($qa, $options, 'question', 'questiontext', [$question->id + 1], false));
        $this->assertFalse($question->check_file_access($qa, $options, 'other', 'hint', [18], false));
        $options->generalfeedback = \question_display_options::HIDDEN;
        $this->assertFalse($question->check_file_access($qa, $options, 'question', 'generalfeedback', [$question->id], false));
    }

    /**
     * Test getting expected data keys.
     */
    public function test_get_expected_data(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $expected = $question->get_expected_data();

        $this->assertArrayHasKey('debitaccount_0', $expected);
        $this->assertArrayHasKey('debitamount_0', $expected);
        $this->assertArrayHasKey('creditaccount_0', $expected);
        $this->assertArrayHasKey('creditamount_0', $expected);
        $this->assertEquals(PARAM_RAW, $expected['debitaccount_0']);
        $this->assertEquals(PARAM_RAW, $expected['debitamount_0']);
    }

    /**
     * Started attempts keep the site grading rules of points mode they started with, also on regrade.
     */
    public function test_attempts_keep_their_grading_rules(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('strictpairs', 0, 'qtype_accounting');
        $course = $this->getDataGenerator()->create_course();
        $context = \qtype_accounting_test_helper::question_bank_context($course);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        [$bank, $sales] = [1, 2];
        $saved = $generator->create_question('accounting', null, [
            'category' => $category->id, 'accounts' => ['Bank', 'Sales'], 'accountkeys' => [], 'gradingmode' => 'points',
            'debitaccount' => [$bank], 'creditaccount' => [$sales],
            'debitamount' => [100], 'creditamount' => [100],
        ]);

        $quba = \question_engine::make_questions_usage_by_activity('core_question_preview', \context_system::instance());
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($saved->id));
        $quba->start_all_questions();
        // Both accounts are right, the debit amount is wrong: half a point for that pair without strict pairs.
        $quba->process_action($slot, [
            'debitaccount_0' => $bank, 'debitamount_0' => '50,00',
            'creditaccount_0' => $sales, 'creditamount_0' => '100,00',
        ]);
        $quba->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);
        $fraction = $quba->get_question_fraction($slot);
        $this->assertEqualsWithDelta(0.75, $fraction, 0.0001);

        set_config('strictpairs', 1, 'qtype_accounting');
        \question_bank::notify_question_edited($saved->id);
        $quba = \question_engine::load_questions_usage_by_activity($quba->get_id());
        $quba->regrade_all_questions();
        $this->assertEqualsWithDelta($fraction, $quba->get_question_fraction($slot), 0.0001);

        // New attempts use the current setting.
        $new = \question_engine::make_questions_usage_by_activity('core_question_preview', \context_system::instance());
        $new->set_preferred_behaviour('deferredfeedback');
        $newslot = $new->add_question(\question_bank::load_question($saved->id));
        $new->start_all_questions();
        $new->process_action($newslot, [
            'debitaccount_0' => $bank, 'debitamount_0' => '50,00',
            'creditaccount_0' => $sales, 'creditamount_0' => '100,00',
        ]);
        $new->finish_all_questions();
        $this->assertEqualsWithDelta(0.5, $new->get_question_fraction($newslot), 0.0001);
    }

    /**
     * Test getting the correct response.
     */
    public function test_get_correct_response(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = $question->get_correct_response();

        $this->assertEquals(101, $response['debitaccount_0']);
        $this->assertSame('1.000,00', $response['debitamount_0']);
        $this->assertEquals(201, $response['creditaccount_0']);
        $this->assertSame('1.000,00', $response['creditamount_0']);
    }

    /**
     * Database decimal strings must round-trip through the normal response grader.
     */
    public function test_database_correct_response_round_trip(): void {
        foreach (['de', 'us'] as $format) {
            $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
            $question->numberformat = $format;
            $question->entries[0]['debitamount'] = '1000.50';
            $question->entries[0]['creditamount'] = '1000.50';
            $response = $question->get_correct_response();
            $this->assertEquals(1.0, $question->grade_response($response)[0]);
            $this->assertStringContainsString('1000.50', $question->summarise_response($response));
        }
    }

    /**
     * Interactive hints retain correct accounts and clear only incorrect amounts.
     */
    public function test_interactive_parts_and_clearing(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $response = $question->get_correct_response();
        $response['debitamount_0'] = '12,50';
        $this->assertSame([3, 4], $question->get_num_parts_right($response));
        $cleared = $question->clear_wrong_from_response($response);
        $this->assertEquals(101, $cleared['debitaccount_0']);
        $this->assertSame('', $cleared['debitamount_0']);
        $this->assertSame($response['creditamount_0'], $cleared['creditamount_0']);
        $response['debitaccount_0'] = 999;
        $this->assertSame('', $question->clear_wrong_from_response($response)['debitaccount_0']);
    }

    /**
     * Splitting an amount over several rows must not change interactive hint correctness.
     */
    public function test_interactive_split_amounts(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $response = $question->get_correct_response();
        $response['debitamount_0'] = '400,00';
        $response['debitaccount_1'] = 101;
        $response['debitamount_1'] = '600,00';
        $this->assertSame([4, 4], $question->get_num_parts_right($response));
        $this->assertSame($response, $question->clear_wrong_from_response($response));
    }

    /**
     * Grading, summary feedback, cell feedback and hints agree on split decimal amounts.
     */
    public function test_split_cents_across_grading_feedback_and_hints(): void {
        foreach (['de', 'us'] as $format) {
            $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
            $question->numberformat = $format;
            $question->entries[0]['debitamount'] = '0.30';
            $question->entries[0]['creditamount'] = '0.30';
            $response = $question->get_correct_response();
            $response['debitamount_0'] = amount_helper::format_cents(10, $format);
            $response['debitaccount_1'] = 101;
            $response['debitamount_1'] = amount_helper::format_cents(20, $format);
            $feedback = new feedback_calculator();

            $this->assertSame(1.0, $question->grade_response($response)[0]);
            $this->assertTrue($feedback->calculate_aggregated_feedback($question, $response)['all_correct']);
            $cells = $feedback->build_cell_feedback_map($question, $response);
            $this->assertSame('qtype_accounting-correct', $cells[0]['debitamount']);
            $this->assertSame([4, 4], $question->get_num_parts_right($response));
            $this->assertSame($response, $question->clear_wrong_from_response($response));

            // A difference of one cent is incorrect on both sides of the expected total.
            foreach ([19, 21] as $cents) {
                $response['debitamount_1'] = amount_helper::format_cents($cents, $format);
                $this->assertSame(0.75, $question->grade_response($response)[0]);
                $this->assertFalse($feedback->calculate_aggregated_feedback($question, $response)['all_correct']);
                $cells = $feedback->build_cell_feedback_map($question, $response);
                $this->assertSame('qtype_accounting-incorrect', $cells[1]['debitamount']);
                $this->assertSame([3, 4], $question->get_num_parts_right($response));
                $this->assertSame('', $question->clear_wrong_from_response($response)['debitamount_0']);
            }
        }
    }

    /**
     * Correct answers split into several rows also aggregate as cents.
     */
    public function test_correct_answer_split_cents(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $first = $question->entries[0];
        $first['debitamount'] = '0.10';
        $first['creditamount'] = '0.10';
        $second = $first;
        $second['debitamount'] = '0.20';
        $second['creditamount'] = '0.20';
        $question->entries = [$first, $second];
        $response = ['debitaccount_0' => 101, 'debitamount_0' => '0,30',
            'creditaccount_0' => 201, 'creditamount_0' => '0,30'];
        $this->assertSame(1.0, $question->grade_response($response)[0]);
        $this->assertTrue((new feedback_calculator())->calculate_aggregated_feedback($question, $response)['all_correct']);
    }

    /**
     * Quiz response analysis receives observed text and a meaningful classification.
     */
    public function test_response_analysis_classification(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $response = $question->get_correct_response();
        $full = $question->classify_response($response)[$question->id];
        $this->assertEquals(1, $full->responseclassid);
        $this->assertEquals(1, $full->fraction);
        $this->assertStringContainsString('1000.00', $full->response);
        $response['debitamount_0'] = '50,00';
        $partial = $question->classify_response($response)[$question->id];
        $this->assertEquals(0, $partial->responseclassid);
        $this->assertGreaterThan(0, $partial->fraction);
        $this->assertLessThan(1, $partial->fraction);
        $this->assertNull($question->classify_response([])[$question->id]->responseclassid);
    }

    /**
     * The declared response fields include all rows permitted by both interfaces.
     */
    public function test_response_row_limit_matches_interface(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $expected = $question->get_expected_data();
        $this->assertCount(4 * \qtype_accounting_question::MAX_ENTRIES, $expected);
        $this->assertArrayHasKey('creditamount_99', $expected);
    }

    /**
     * Test is_complete_response with complete response.
     */
    public function test_is_complete_response_complete(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);

        $this->assertTrue($question->is_complete_response($response));
    }

    /**
     * Test is_complete_response with empty response.
     */
    public function test_is_complete_response_empty(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = [];

        $this->assertFalse($question->is_complete_response($response));
    }

    /**
     * Test is_complete_response with only debit account filled.
     * A response is considered complete if any account field is filled.
     */
    public function test_is_complete_response_only_debit(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 0, 0);

        $this->assertTrue($question->is_complete_response($response));
    }

    /**
     * Test is_complete_response with only credit account filled.
     * A response is considered complete if any account field is filled.
     */
    public function test_is_complete_response_only_credit(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(0, 0, 201, 1000);

        $this->assertTrue($question->is_complete_response($response));
    }

    /**
     * Test is_same_response with identical responses.
     */
    public function test_is_same_response_identical(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response1 = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        $response2 = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);

        $this->assertTrue($question->is_same_response($response1, $response2));
    }

    /**
     * Test is_same_response with different responses.
     */
    public function test_is_same_response_different(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response1 = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        $response2 = qtype_accounting_test_helper::make_response(101, 500, 201, 500);

        $this->assertFalse($question->is_same_response($response1, $response2));
    }

    /**
     * Test grading a correct response.
     */
    public function test_grade_response_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test grading a completely wrong response.
     */
    public function test_grade_response_wrong(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        // Add wrong account IDs to the accounts map so they are recognised as valid accounts.
        $question->accountsmap[901] = '9999 Wrong Debit';
        $question->accountsmap[902] = '9998 Wrong Credit';

        $response = qtype_accounting_test_helper::make_response(901, 1000, 902, 1000);
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
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        // Correct accounts but wrong amounts.
        $response = qtype_accounting_test_helper::make_response(101, 500, 201, 500);
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
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        // Change entries to use text account names (legacy-style test for case-insensitive matching).
        $question->entries = [
            [
                'debitaccountid' => 101,
                'debitamount' => 1000.00,
                'creditaccountid' => 201,
                'creditamount' => 1000.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
                'explanation' => '',
            ],
        ];

        // Response with the correct account IDs (ID-based matching is exact).
        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Extra decimal places round to cents before grading.
     */
    public function test_grade_response_cent_rounding(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        // Use US number format so canonical decimal input parses correctly.
        // In German format, dots are thousand separators and would be stripped.
        $question->numberformat = 'us';

        $response = qtype_accounting_test_helper::make_response(101, 1000.004, 201, 1000.004);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test grading with multiple entries - all correct.
     */
    public function test_grade_response_multiple_entries_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'multiple_entries');

        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 102, 'debitamount' => 500, 'creditaccountid' => 201, 'creditamount' => 500],
            ['debitaccountid' => 101, 'debitamount' => 500, 'creditaccountid' => 201, 'creditamount' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test grading with multiple entries - partial credit.
     */
    public function test_grade_response_multiple_entries_partial(): void {
        $question = \test_question_maker::make_question('accounting', 'multiple_entries');

        // Add wrong account IDs to the accounts map.
        $question->accountsmap[901] = '9999 Wrong Debit';
        $question->accountsmap[902] = '9998 Wrong Credit';

        // Only one of two entries is correct.
        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 102, 'debitamount' => 500, 'creditaccountid' => 201, 'creditamount' => 500],
            ['debitaccountid' => 901, 'debitamount' => 500, 'creditaccountid' => 902, 'creditamount' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test grading with multiple entries in different order.
     */
    public function test_grade_response_multiple_entries_different_order(): void {
        $question = \test_question_maker::make_question('accounting', 'multiple_entries');

        // Same entries but in reverse order - should still be correct.
        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 500, 'creditaccountid' => 201, 'creditamount' => 500],
            ['debitaccountid' => 102, 'debitamount' => 500, 'creditaccountid' => 201, 'creditamount' => 500],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test summarise_response.
     */
    public function test_summarise_response(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        $summary = $question->summarise_response($response);

        $this->assertStringContainsString('1200 Bank', $summary);
        $this->assertStringContainsString('8400 Erloese 19% USt', $summary);
        $this->assertStringContainsString('1000.00', $summary);
    }

    /**
     * Test grading with empty entries array.
     */
    public function test_grade_response_no_correct_entries(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $question->entries = [];

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        [$fraction] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
    }

    /**
     * Test validation error for incomplete response.
     */
    public function test_get_validation_error_incomplete(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = [];
        $error = $question->get_validation_error($response);

        $this->assertNotEmpty($error);
    }

    /**
     * Test validation error for complete response.
     */
    public function test_get_validation_error_complete(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        $error = $question->get_validation_error($response);

        $this->assertEmpty($error);
    }

    // Aggregation-based grading tests.

    /**
     * Test that amounts are aggregated by account - split amounts.
     *
     * Correct: Bank 600 / Revenue 600
     * Student enters: Bank 300 + Bank 300 / Revenue 300 + Revenue 300
     * Should be 100% correct because aggregated totals match.
     */
    public function test_aggregation_split_amounts_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'split_amounts');

        // Student splits the amount into two entries.
        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 300, 'creditaccountid' => 201, 'creditamount' => 300],
            ['debitaccountid' => 101, 'debitamount' => 300, 'creditaccountid' => 201, 'creditamount' => 300],
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
        $question = \test_question_maker::make_question('accounting', 'split_amounts');

        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 400, 'creditaccountid' => 201, 'creditamount' => 100],
            ['debitaccountid' => 101, 'debitamount' => 200, 'creditaccountid' => 201, 'creditamount' => 500],
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
        $question = \test_question_maker::make_question('accounting', 'split_amounts');

        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 300, 'creditaccountid' => 201, 'creditamount' => 300],
            ['debitaccountid' => 101, 'debitamount' => 200, 'creditaccountid' => 201, 'creditamount' => 200],
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
        $question = \test_question_maker::make_question('accounting', 'multiple_same_account');

        // Student enters single entry with total.
        $response = qtype_accounting_test_helper::make_response(101, 500, 201, 500);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    // All-or-nothing grading tests.

    /**
     * Test all-or-nothing grading with correct answer.
     */
    public function test_all_or_nothing_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'all_or_nothing');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test all-or-nothing grading with partially correct answer.
     * Should get 0 because all-or-nothing is enabled.
     */
    public function test_all_or_nothing_partial_gets_zero(): void {
        $question = \test_question_maker::make_question('accounting', 'all_or_nothing');

        // Correct accounts but wrong amounts.
        $response = qtype_accounting_test_helper::make_response(101, 500, 201, 500);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test all-or-nothing grading with one correct account.
     * Should get 0 because all-or-nothing is enabled.
     */
    public function test_all_or_nothing_one_account_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'all_or_nothing');

        // Add wrong account ID to the accounts map.
        $question->accountsmap[901] = '9999 Wrong';

        // Only debit correct.
        $response = qtype_accounting_test_helper::make_response(101, 1000, 901, 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test that partial credit works when all-or-nothing is disabled.
     */
    public function test_partial_credit_when_not_all_or_nothing(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $question->allornothinggrading = 0;

        // Correct accounts but wrong amounts = 50% (accounts correct, amounts wrong).
        $response = qtype_accounting_test_helper::make_response(101, 500, 201, 500);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    // Weighted scoring tests.

    /**
     * Test weighted scoring with correct answer.
     */
    public function test_weighted_scoring_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'weighted_entries');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test weighted scoring - accounts correct, amounts wrong.
     *
     * Weights: debitaccount=2, debitamount=1, creditaccount=2, creditamount=1 (total=6)
     * Correct accounts = 2+2 = 4 points
     * Wrong amounts = 0 points
     * Fraction = 4/6 = 0.6667
     */
    public function test_weighted_scoring_accounts_correct_amounts_wrong(): void {
        $question = \test_question_maker::make_question('accounting', 'weighted_entries');

        // Correct accounts but wrong amounts.
        $response = qtype_accounting_test_helper::make_response(101, 500, 201, 500);
        [$fraction, $state] = $question->grade_response($response);

        // Accounts have weight 2 each, amounts weight 1 each.
        // Total weight = 2+1+2+1 = 6.
        // Earned = 2+0+2+0 = 4.
        // Fraction = 4/6 = 0.6667.
        $this->assertEqualsWithDelta(0.6667, $fraction, 0.001);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test weighted scoring - only debit side correct.
     *
     * Weights: debitaccount=2, debitamount=1, creditaccount=2, creditamount=1 (total=6)
     * Debit correct = 2+1 = 3 points
     * Credit wrong = 0 points
     * Fraction = 3/6 = 0.5
     */
    public function test_weighted_scoring_debit_only_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'weighted_entries');

        // Add wrong account ID to the accounts map.
        $question->accountsmap[901] = '9999 Wrong';

        // Only debit side correct.
        $response = qtype_accounting_test_helper::make_response(101, 1000, 901, 9999);
        [$fraction, $state] = $question->grade_response($response);

        // Debit = 2+1 = 3, Credit = 0.
        // Fraction = 3/6 = 0.5.
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test weighted scoring - only credit side correct.
     */
    public function test_weighted_scoring_credit_only_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'weighted_entries');

        // Add wrong account ID to the accounts map.
        $question->accountsmap[901] = '9999 Wrong';

        // Only credit side correct.
        $response = qtype_accounting_test_helper::make_response(901, 9999, 201, 1000);
        [$fraction, $state] = $question->grade_response($response);

        // Debit = 0, Credit = 2+1 = 3.
        // Fraction = 3/6 = 0.5.
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    // Order independence tests.

    /**
     * Test that entry order doesn't matter.
     */
    public function test_order_independence(): void {
        $question = \test_question_maker::make_question('accounting', 'multiple_entries');

        // Reverse order of entries.
        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 500, 'creditaccountid' => 201, 'creditamount' => 500],
            ['debitaccountid' => 102, 'debitamount' => 500, 'creditaccountid' => 201, 'creditamount' => 500],
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
        $question = \test_question_maker::make_question('accounting', 'multiple_same_account');

        // Random order and splits.
        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 150, 'creditaccountid' => 201, 'creditamount' => 250],
            ['debitaccountid' => 101, 'debitamount' => 200, 'creditaccountid' => 201, 'creditamount' => 100],
            ['debitaccountid' => 101, 'debitamount' => 150, 'creditaccountid' => 201, 'creditamount' => 150],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // Total: Bank 500 / Revenue 500 - correct!
        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    // Partial correctness tests.

    /**
     * Test correct accounts but wrong amounts gives partial credit.
     */
    public function test_partial_credit_correct_accounts_wrong_amounts(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(101, 500, 201, 500);
        [$fraction, $state] = $question->grade_response($response);

        // All 4 weights = 1, total = 4.
        // Accounts correct = 2, amounts wrong = 0.
        // Fraction = 2/4 = 0.5.
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test only one side correct.
     */
    public function test_partial_credit_one_side_correct(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        // Add wrong account ID to the accounts map.
        $question->accountsmap[901] = '9999 Wrong';

        // Debit side correct, credit side completely wrong.
        $response = qtype_accounting_test_helper::make_response(101, 1000, 901, 9999);
        [$fraction, $state] = $question->grade_response($response);

        // Debit correct = 2, Credit = 0.
        // Fraction = 2/4 = 0.5.
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    /**
     * Test missing account on one side.
     */
    public function test_partial_credit_missing_account(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        // Add wrong account ID to the accounts map.
        $question->accountsmap[901] = '9999 Wrong';

        // Missing debit account entirely - using different account.
        $response = qtype_accounting_test_helper::make_response(901, 1000, 201, 1000);
        [$fraction, $state] = $question->grade_response($response);

        // Credit side correct = 2, Debit wrong = 0.
        $this->assertEquals(0.5, $fraction);
        $this->assertEquals(question_state::$gradedpartial, $state);
    }

    // Edge cases.

    /**
     * Test empty student response.
     */
    public function test_grade_empty_response(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        $response = qtype_accounting_test_helper::make_response(0, 0, 0, 0);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(0.0, $fraction);
        $this->assertEquals(question_state::$gradedwrong, $state);
    }

    /**
     * Test student provides extra entries beyond correct answer.
     */
    public function test_extra_entries_ignored(): void {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');

        // Add wrong account IDs to the accounts map.
        $question->accountsmap[901] = '9999 Wrong Debit';
        $question->accountsmap[902] = '9998 Wrong Credit';

        // Correct answer plus extra wrong entry.
        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 1000, 'creditaccountid' => 201, 'creditamount' => 1000],
            ['debitaccountid' => 901, 'debitamount' => 500, 'creditaccountid' => 902, 'creditamount' => 500],
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
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        // Modify to have debit-only entry.
        $question->entries = [
            [
                'debitaccountid' => 101,
                'debitamount' => 1000.00,
                'creditaccountid' => 0,
                'creditamount' => 0,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
                'explanation' => '',
            ],
        ];

        $response = qtype_accounting_test_helper::make_response(101, 1000, 0, 0);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    /**
     * Test credit-only entry (no debit account in correct answer).
     */
    public function test_credit_only_entry(): void {
        $question = \test_question_maker::make_question('accounting', 'debit_only_optional');

        $response = qtype_accounting_test_helper::make_response(0, 0, 301, 250);
        [$fraction, $state] = $question->grade_response($response);

        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }

    // Extra entry deduction tests.

    /**
     * Test extra entry deduction with no extra accounts.
     *
     * Correct answer only, no extras. Fraction should be 1.0.
     */
    public function test_extra_entry_deduction_no_extras(): void {
        $question = \test_question_maker::make_question('accounting', 'extra_entry_deduction');

        $response = qtype_accounting_test_helper::make_response(101, 1000, 201, 1000);
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
        $question = \test_question_maker::make_question('accounting', 'extra_entry_deduction');

        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 1000, 'creditaccountid' => 201, 'creditamount' => 1000],
            ['debitaccountid' => 102, 'debitamount' => 100, 'creditaccountid' => 301, 'creditamount' => 100],
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
        $question = \test_question_maker::make_question('accounting', 'extra_entry_deduction');
        // Set high deduction so it exceeds possible score.
        $question->extraentrydeduction = 0.5;

        // Add extra account IDs to the accounts map.
        $question->accountsmap[401] = '2000 Forderungen';
        $question->accountsmap[501] = '3000 Rueckstellungen';

        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 1000, 'creditaccountid' => 201, 'creditamount' => 1000],
            ['debitaccountid' => 102, 'debitamount' => 100, 'creditaccountid' => 301, 'creditamount' => 100],
            ['debitaccountid' => 401, 'debitamount' => 50, 'creditaccountid' => 501, 'creditamount' => 50],
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
        $question = \test_question_maker::make_question('accounting', 'extra_entry_deduction');
        $question->extraentrydeduction = 0;

        $response = qtype_accounting_test_helper::make_multi_response([
            ['debitaccountid' => 101, 'debitamount' => 1000, 'creditaccountid' => 201, 'creditamount' => 1000],
            ['debitaccountid' => 102, 'debitamount' => 100, 'creditaccountid' => 301, 'creditamount' => 100],
        ]);
        [$fraction, $state] = $question->grade_response($response);

        // No deduction when extraentrydeduction is 0.
        $this->assertEquals(1.0, $fraction);
        $this->assertEquals(question_state::$gradedright, $state);
    }
}
