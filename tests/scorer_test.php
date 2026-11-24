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

/**
 * Unit tests for the grading modes of the scorer.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\scorer
 */
final class scorer_test extends \basic_testcase {
    /** @var int Account ID for Bank. */
    private const BANK = 1;
    /** @var int Account ID for Cash. */
    private const CASH = 2;
    /** @var int Account ID for Sales. */
    private const SALES = 3;

    /**
     * Correct answer: Bank 1000 and Cash 500 to Sales 1500 (three account-amount pairs).
     *
     * @param int $weightaccount Weight of the Cash account.
     * @param int $weightamount Weight of the Cash amount.
     * @return array
     */
    private function compound_entries(int $weightaccount = 1, int $weightamount = 1): array {
        return [
            $this->entry(self::BANK, 1000, self::SALES, 1000),
            $this->entry(self::CASH, 500, self::SALES, 500, $weightaccount, $weightamount),
        ];
    }

    /**
     * Build one correct entry.
     *
     * @param int $debit Debit account ID.
     * @param float $debitamount Debit amount.
     * @param int $credit Credit account ID.
     * @param float $creditamount Credit amount.
     * @param int $weightaccount Debit account weight.
     * @param int $weightamount Debit amount weight.
     * @return array
     */
    private function entry(
        int $debit,
        float $debitamount,
        int $credit,
        float $creditamount,
        int $weightaccount = 1,
        int $weightamount = 1
    ): array {
        return [
            'debitaccountid' => $debit,
            'debitamount' => $debitamount,
            'creditaccountid' => $credit,
            'creditamount' => $creditamount,
            'weight_debitaccount' => $weightaccount,
            'weight_debitamount' => $weightamount,
            'weight_creditaccount' => 1,
            'weight_creditamount' => 1,
        ];
    }

    /**
     * Build a student response in US number format.
     *
     * @param array $rows Rows of [debitaccount, debitamount, creditaccount, creditamount].
     * @return array
     */
    private function response(array $rows): array {
        $response = [];
        foreach ($rows as $i => [$debit, $debitamount, $credit, $creditamount]) {
            $response["debitaccount_{$i}"] = $debit;
            $response["debitamount_{$i}"] = $debitamount;
            $response["creditaccount_{$i}"] = $credit;
            $response["creditamount_{$i}"] = $creditamount;
        }
        return $response;
    }

    /**
     * Create a scorer.
     *
     * @param string $mode Grading mode.
     * @param bool $strict Strict pairs.
     * @param bool $accountsonly Accounts-only exception.
     * @return scorer
     */
    private function scorer(string $mode, bool $strict = true, bool $accountsonly = false): scorer {
        return new scorer(null, false, 'us', $mode, $strict, $accountsonly);
    }

    /**
     * Points mode awards one point per account-amount pair.
     */
    public function test_points_max_points(): void {
        $this->assertSame(3, $this->scorer(scorer::GRADINGMODE_POINTS)->max_points($this->compound_entries()));
    }

    /**
     * A fully correct answer earns all points.
     */
    public function test_points_fully_correct(): void {
        $response = $this->response([[self::BANK, '1000', self::SALES, '1000'], [self::CASH, '500', self::SALES, '500']]);
        $fraction = $this->scorer(scorer::GRADINGMODE_POINTS)->calculate_fraction($this->compound_entries(), $response);
        $this->assertEqualsWithDelta(1.0, $fraction, 0.0001);
    }

    /**
     * Strict pairs: a wrong amount loses the whole pair, regardless of weights.
     */
    public function test_points_strict_wrong_amount_loses_pair(): void {
        $response = $this->response([[self::BANK, '1000', self::SALES, '1500'], [self::CASH, '400', 0, '']]);
        $fraction = $this->scorer(scorer::GRADINGMODE_POINTS)->calculate_fraction($this->compound_entries(3, 1), $response);
        $this->assertEqualsWithDelta(2 / 3, $fraction, 0.0001);
    }

    /**
     * Strict pairs: a wrong account loses the pair even if the amount is right.
     */
    public function test_points_strict_wrong_account_loses_pair(): void {
        $response = $this->response([[self::BANK, '1000', self::SALES, '1500'], [self::SALES, '500', 0, '']]);
        $fraction = $this->scorer(scorer::GRADINGMODE_POINTS)->calculate_fraction($this->compound_entries(), $response);
        $this->assertEqualsWithDelta(2 / 3, $fraction, 0.0001);
    }

    /**
     * Without strict pairs, the pair's point is split according to the weights.
     */
    public function test_points_non_strict_uses_weights(): void {
        $response = $this->response([[self::BANK, '1000', self::SALES, '1500'], [self::CASH, '400', 0, '']]);
        $scorer = $this->scorer(scorer::GRADINGMODE_POINTS, false);
        // Cash account correct (weight 3 of 4), amount wrong: 0.75 of one point.
        $this->assertEqualsWithDelta(2.75 / 3, $scorer->calculate_fraction($this->compound_entries(3, 1), $response), 0.0001);
        // Equal weights: half a point.
        $this->assertEqualsWithDelta(2.5 / 3, $scorer->calculate_fraction($this->compound_entries(), $response), 0.0001);
    }

    /**
     * Points mode deducts points per extra account from the earned points, never below zero.
     */
    public function test_points_deduction_per_extra_account(): void {
        $correct = [[self::BANK, '1000', self::SALES, '1500'], [self::CASH, '500', 0, '']];
        // One extra account on the credit side.
        $oneextra = $this->response(array_merge($correct, [[0, '', self::BANK, '0']]));
        $scorer = new scorer(0.5, false, 'us', scorer::GRADINGMODE_POINTS);
        $this->assertEqualsWithDelta(2.5 / 3, $scorer->calculate_fraction($this->compound_entries(), $oneextra), 0.0001);

        // Two extra accounts at 2 points each would go below zero.
        $twoextra = $this->response(array_merge($correct, [[0, '', self::BANK, '0'], [self::SALES, '0', 0, '']]));
        $scorer = new scorer(2.0, false, 'us', scorer::GRADINGMODE_POINTS);
        $this->assertEqualsWithDelta(0.0, $scorer->calculate_fraction($this->compound_entries(), $twoextra), 0.0001);

        // In percent mode the same value is a fraction of the whole question.
        $scorer = new scorer(0.5, false, 'us', scorer::GRADINGMODE_PERCENT);
        $this->assertEqualsWithDelta(0.5, $scorer->calculate_fraction($this->compound_entries(), $oneextra), 0.0001);
    }

    /**
     * The percentage mode keeps the weighted calculation over all fields.
     */
    public function test_percent_mode_unchanged(): void {
        $response = $this->response([[self::BANK, '1000', self::SALES, '1500'], [self::CASH, '400', 0, '']]);
        $fraction = $this->scorer(scorer::GRADINGMODE_PERCENT)->calculate_fraction($this->compound_entries(3, 1), $response);
        // Weights: Bank 1+1, Cash 3+1, Sales (aggregated over two entries) 2+2 = 10; only the Cash amount (1) is missed.
        $this->assertEqualsWithDelta(9 / 10, $fraction, 0.0001);
    }

    /**
     * Unknown grading modes fall back to the percentage mode.
     */
    public function test_normalise_grading_mode(): void {
        $this->assertSame(scorer::GRADINGMODE_POINTS, scorer::normalise_grading_mode('points'));
        $this->assertSame(scorer::GRADINGMODE_PERCENT, scorer::normalise_grading_mode(''));
        $this->assertSame(scorer::GRADINGMODE_PERCENT, scorer::normalise_grading_mode(null));
        $this->assertSame(scorer::GRADINGMODE_PERCENT, scorer::normalise_grading_mode('foo'));
    }

    /**
     * Provide responses for the exception (correct answer: Bank 1000 and Cash 500 to Sales 1500).
     *
     * @return array
     */
    public static function accounts_without_amounts_provider(): array {
        $correct = [[self::BANK, '', self::SALES, ''], [self::CASH, '', 0, '']];
        return [
            'correct accounts, no amounts' => [$correct, true, true, 1 / 3],
            'zero amounts count as no amounts' => [
                [[self::BANK, '0', self::SALES, '0.00'], [self::CASH, '', 0, '']],
                true,
                true,
                1 / 3,
            ],
            'exception disabled' => [$correct, true, false, 0.0],
            'missing account' => [[[self::BANK, '', self::SALES, '']], true, true, 0.0],
            'additional account' => [[[self::BANK, '', self::SALES, ''], [self::CASH, '', self::BANK, '']], true, true, 0.0],
            'wrong side' => [[[self::SALES, '', self::BANK, ''], [self::CASH, '', 0, '']], true, true, 0.0],
            'weighted split gives more than one point' => [$correct, false, true, 1.5 / 3],
        ];
    }

    /**
     * Points mode: exactly the correct accounts without amounts earn one point if the site allows it.
     *
     * @dataProvider accounts_without_amounts_provider
     * @param array $rows Response rows.
     * @param bool $strict Strict pairs.
     * @param bool $exception Accounts-only exception.
     * @param float $expected Expected fraction.
     */
    public function test_correct_accounts_without_amounts(array $rows, bool $strict, bool $exception, float $expected): void {
        $scorer = $this->scorer(scorer::GRADINGMODE_POINTS, $strict, $exception);
        $this->assertSame(3, $scorer->max_points($this->compound_entries()));
        $fraction = $scorer->calculate_fraction($this->compound_entries(), $this->response($rows));
        $this->assertEqualsWithDelta($expected, $fraction, 0.0001);
    }

    /**
     * The exception does not apply if any amount is entered, nor in percentage mode.
     */
    public function test_exception_requires_no_amounts_and_points_mode(): void {
        $withamount = $this->response([[self::BANK, '999', self::SALES, ''], [self::CASH, '', 0, '']]);
        $scorer = $this->scorer(scorer::GRADINGMODE_POINTS, true, true);
        $this->assertEqualsWithDelta(0.0, $scorer->calculate_fraction($this->compound_entries(), $withamount), 0.0001);

        $noamounts = $this->response([[self::BANK, '', self::SALES, ''], [self::CASH, '', 0, '']]);
        $percent = $this->scorer(scorer::GRADINGMODE_PERCENT, true, true);
        // Percentage mode keeps the account weights: 3 of 6.
        $this->assertEqualsWithDelta(0.5, $percent->calculate_fraction($this->compound_entries(), $noamounts), 0.0001);
    }
}
