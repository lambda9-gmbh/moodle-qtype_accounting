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

/**
 * Aggregated weighted scoring for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Computes the fraction of correctness for a student response.
 *
 * Extracted from {@see \qtype_accounting_question} so the question class can stay
 * focused on Moodle question API hooks. The scorer is stateless aside from the
 * extra-entry deduction and all-or-nothing toggles passed in at construction time.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer {
    /** @var string Weighted grading: account and amount weights yield a percentage. */
    public const GRADINGMODE_PERCENT = 'percent';
    /** @var string Point grading: one point per account-amount pair. */
    public const GRADINGMODE_POINTS = 'points';

    /** @var float Deduction per extra account: a fraction in percent mode, points in points mode (0 = none). */
    protected $extraentrydeduction;
    /** @var bool If true, anything less than full credit is zeroed. */
    protected $allornothinggrading;
    /** @var string Number format ('de' or 'us') used to parse student amounts. */
    protected $numberformat;
    /** @var string Grading mode, one of the GRADINGMODE_* constants. */
    protected $gradingmode;
    /** @var bool Points mode: a pair only earns its point if account and amount are both right. */
    protected $strictpairs;
    /** @var bool Points mode: a response with exactly the correct accounts but no amounts earns one point. */
    protected $accountsonlyexception;

    /**
     * Construct a scorer with the question's grading-behaviour toggles.
     *
     * @param float|null $extraentrydeduction Per-extra-account penalty: a fraction, or points in points mode (null/0 disables).
     * @param bool $allornothinggrading True to apply the all-or-nothing toggle.
     * @param string $numberformat 'de' or 'us' for parsing student amount inputs.
     * @param string $gradingmode One of the GRADINGMODE_* constants.
     * @param bool $strictpairs Points mode: no partial point for a pair with a wrong account or amount.
     * @param bool $accountsonlyexception Points mode: award one point for exactly the correct accounts without amounts.
     */
    public function __construct(
        $extraentrydeduction,
        bool $allornothinggrading,
        string $numberformat,
        string $gradingmode = self::GRADINGMODE_PERCENT,
        bool $strictpairs = true,
        bool $accountsonlyexception = false
    ) {
        $this->extraentrydeduction = $extraentrydeduction;
        $this->allornothinggrading = $allornothinggrading;
        $this->numberformat = $numberformat;
        $this->gradingmode = self::normalise_grading_mode($gradingmode);
        $this->strictpairs = $strictpairs;
        $this->accountsonlyexception = $accountsonlyexception;
    }

    /**
     * Map any stored or submitted value to a valid grading mode.
     *
     * @param string|null $gradingmode The raw value.
     * @return string One of the GRADINGMODE_* constants; percent for unknown values.
     */
    public static function normalise_grading_mode(?string $gradingmode): string {
        return $gradingmode === self::GRADINGMODE_POINTS ? self::GRADINGMODE_POINTS : self::GRADINGMODE_PERCENT;
    }

    /**
     * The number of points a fully correct answer earns in points mode.
     *
     * One point per account-amount pair (accounts aggregated per side).
     *
     * @param array $correctentries The teacher's correct entries.
     * @return int The maximum points.
     */
    public function max_points(array $correctentries): int {
        $aggregated = $this->aggregate_entries($correctentries, false);
        return count($aggregated['debit']) + count($aggregated['credit']);
    }

    /**
     * Calculate the fraction of correctness for a response.
     *
     * Aggregates correct + student entries by account on each side, then earns weight per
     * correct account ID + per matching amount; finally applies the extra-entry deduction
     * and all-or-nothing toggle.
     *
     * @param array $correctentries The teacher's correct entries.
     * @param array $response The student response (flat debitaccount_i/debitamount_i/... keys).
     * @return float Fraction in [0, 1].
     */
    public function calculate_fraction(array $correctentries, array $response): float {
        if (empty($correctentries)) {
            return 0;
        }
        $studententries = $this->parse_response($response);
        $correctaggregated = $this->aggregate_entries($correctentries, true);
        $studentaggregated = $this->aggregate_entries($studententries, false);

        if ($this->gradingmode === self::GRADINGMODE_POINTS) {
            $debit = $this->score_side_points($correctaggregated['debit'], $studentaggregated['debit']);
            $credit = $this->score_side_points($correctaggregated['credit'], $studentaggregated['credit']);
            $accountsonly = !$this->has_amounts($studententries)
                && $this->accounts_match_exactly($correctaggregated, $studentaggregated);
            if ($this->accountsonlyexception && $accountsonly) {
                // Exactly the correct accounts without any amounts earn at least one point.
                $debit['earned'] = max($debit['earned'] + $credit['earned'], 1);
                $credit['earned'] = 0;
            }
        } else {
            $debit = $this->score_side($correctaggregated['debit'], $studentaggregated['debit']);
            $credit = $this->score_side($correctaggregated['credit'], $studentaggregated['credit']);
        }

        $totalweight = $debit['total'] + $credit['total'];
        if ($totalweight == 0) {
            return 0;
        }
        $fraction = ($debit['earned'] + $credit['earned']) / $totalweight;
        $fraction = $this->apply_extra_entry_deduction($fraction, $totalweight, $correctaggregated, $studentaggregated);
        return $this->apply_all_or_nothing($fraction);
    }

    /**
     * Score one side (debit or credit) of the booking entries.
     *
     * @param array $correctside Aggregated correct entries for this side, keyed by account ID.
     * @param array $studentside Aggregated student entries for this side, keyed by account ID.
     * @return array ['earned' => float, 'total' => float]
     */
    protected function score_side(array $correctside, array $studentside): array {
        $earned = 0;
        $total = 0;
        foreach ($correctside as $accountid => $correctdata) {
            $total += $correctdata['weight_account'] + $correctdata['weight_amount'];
            if (!isset($studentside[$accountid])) {
                continue;
            }
            $earned += $correctdata['weight_account'];
            if ($this->amounts_equal($studentside[$accountid]['amount'], $correctdata['amount'])) {
                $earned += $correctdata['weight_amount'];
            }
        }
        return ['earned' => $earned, 'total' => $total];
    }

    /**
     * Score one side in points mode: one point per account-amount pair.
     *
     * With strict pairs, a pair only earns its point if account and amount are both correct.
     * Otherwise the point is split between account and amount according to their weights.
     *
     * @param array $correctside Aggregated correct entries for this side, keyed by account ID.
     * @param array $studentside Aggregated student entries for this side, keyed by account ID.
     * @return array ['earned' => float, 'total' => float]
     */
    protected function score_side_points(array $correctside, array $studentside): array {
        $earned = 0;
        $total = 0;
        foreach ($correctside as $accountid => $correctdata) {
            $total++;
            if (!isset($studentside[$accountid])) {
                continue;
            }
            $amountcorrect = $this->amounts_equal($studentside[$accountid]['amount'], $correctdata['amount']);
            if ($this->strictpairs) {
                $earned += $amountcorrect ? 1 : 0;
                continue;
            }
            $weightsum = $correctdata['weight_account'] + $correctdata['weight_amount'];
            $earned += $correctdata['weight_account'] / $weightsum;
            if ($amountcorrect) {
                $earned += $correctdata['weight_amount'] / $weightsum;
            }
        }
        return ['earned' => $earned, 'total' => $total];
    }

    /**
     * Whether any parsed student entry contains a non-zero amount.
     *
     * @param array $studententries Entries from parse_response().
     * @return bool
     */
    protected function has_amounts(array $studententries): bool {
        foreach ($studententries as $entry) {
            if (!$this->amounts_equal($entry['debitamount'], 0) || !$this->amounts_equal($entry['creditamount'], 0)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the student named exactly the correct accounts on both sides.
     *
     * @param array $correctaggregated Aggregated correct entries, keyed by side.
     * @param array $studentaggregated Aggregated student entries, keyed by side.
     * @return bool
     */
    protected function accounts_match_exactly(array $correctaggregated, array $studentaggregated): bool {
        foreach (['debit', 'credit'] as $side) {
            $correct = array_keys($correctaggregated[$side]);
            $student = array_keys($studentaggregated[$side]);
            sort($correct);
            sort($student);
            if ($correct !== $student) {
                return false;
            }
        }
        return true;
    }

    /**
     * Apply the extra-entry-per-account deduction if configured.
     *
     * In points mode the deduction is a number of points, subtracted from the earned points.
     *
     * @param float $fraction Fraction earned so far.
     * @param float $total Total weight, or the maximum points in points mode.
     * @param array $correctaggregated Aggregated correct entries, keyed by side.
     * @param array $studentaggregated Aggregated student entries, keyed by side.
     * @return float The (possibly reduced) fraction, never below 0.
     */
    protected function apply_extra_entry_deduction(
        float $fraction,
        float $total,
        array $correctaggregated,
        array $studentaggregated
    ): float {
        if (empty($this->extraentrydeduction)) {
            return $fraction;
        }
        $extracount = 0;
        foreach (['debit', 'credit'] as $side) {
            foreach (array_keys($studentaggregated[$side]) as $accountid) {
                if (!isset($correctaggregated[$side][$accountid])) {
                    $extracount++;
                }
            }
        }
        $deduction = (float)$this->extraentrydeduction;
        if ($this->gradingmode === self::GRADINGMODE_POINTS) {
            $deduction /= $total;
        }
        return max(0, $fraction - $extracount * $deduction);
    }

    /**
     * Apply the all-or-nothing grading toggle to a fraction.
     *
     * @param float $fraction The pre-toggle fraction.
     * @return float Either the input value, or 0 if all-or-nothing is enabled and the fraction is below 1.
     */
    protected function apply_all_or_nothing(float $fraction): float {
        if (!empty($this->allornothinggrading) && $fraction < 1) {
            return 0;
        }
        return $fraction;
    }

    /**
     * Aggregate entries by account ID on each side (Debit/Credit).
     *
     * For each account on each side, sums the amounts. For correct entries, also sums the weights.
     *
     * @param array $entries The entries to aggregate.
     * @param bool $includeweights Whether to include and aggregate weights (for correct entries).
     * @return array Aggregated data with 'debit' and 'credit' keys.
     */
    public function aggregate_entries(array $entries, bool $includeweights): array {
        $aggregated = ['debit' => [], 'credit' => []];
        foreach ($entries as $entry) {
            $this->add_to_side(
                $aggregated,
                'debit',
                (int)($entry['debitaccountid'] ?? 0),
                (float)($entry['debitamount'] ?? 0),
                $includeweights,
                (int)($entry['weight_debitaccount'] ?? 1),
                (int)($entry['weight_debitamount'] ?? 1)
            );
            $this->add_to_side(
                $aggregated,
                'credit',
                (int)($entry['creditaccountid'] ?? 0),
                (float)($entry['creditamount'] ?? 0),
                $includeweights,
                (int)($entry['weight_creditaccount'] ?? 1),
                (int)($entry['weight_creditamount'] ?? 1)
            );
        }
        return $aggregated;
    }

    /**
     * Add one entry's amount (and optionally weights) to the aggregation for one side.
     *
     * @param array $aggregated Aggregation accumulator (modified in place).
     * @param string $side 'debit' or 'credit'.
     * @param int $accountid Account ID; if 0, nothing is added.
     * @param float $amount Amount to add.
     * @param bool $includeweights Whether to also sum the weights.
     * @param int $weightaccount Weight contribution for the account match.
     * @param int $weightamount Weight contribution for the amount match.
     */
    protected function add_to_side(
        array &$aggregated,
        string $side,
        int $accountid,
        float $amount,
        bool $includeweights,
        int $weightaccount,
        int $weightamount
    ): void {
        if ($accountid <= 0) {
            return;
        }
        if (!isset($aggregated[$side][$accountid])) {
            $aggregated[$side][$accountid] = ['amount' => 0, 'weight_account' => 0, 'weight_amount' => 0];
        }
        $aggregated[$side][$accountid]['amount'] += $amount;
        if ($includeweights) {
            $aggregated[$side][$accountid]['weight_account'] += $weightaccount;
            $aggregated[$side][$accountid]['weight_amount'] += $weightamount;
        }
    }

    /**
     * Compare two amounts with floating-point tolerance.
     *
     * @param float $amount1 First amount.
     * @param float $amount2 Second amount.
     * @param float $tolerance The tolerance for comparison (default 0.01).
     * @return bool True if amounts are equal within tolerance.
     */
    public function amounts_equal(float $amount1, float $amount2, float $tolerance = 0.01): bool {
        return abs($amount1 - $amount2) < $tolerance;
    }

    /**
     * Parse a Moodle response array into structured entry rows.
     *
     * @param array $response The response data.
     * @return array Entry rows in {debitaccountid, debitamount, creditaccountid, creditamount} shape.
     */
    public function parse_response(array $response): array {
        $entries = [];
        $max = $this->max_response_index($response);
        for ($i = 0; $i <= $max; $i++) {
            $debitaccountid = (int)($response["debitaccount_{$i}"] ?? 0);
            $creditaccountid = (int)($response["creditaccount_{$i}"] ?? 0);
            if ($debitaccountid <= 0 && $creditaccountid <= 0) {
                continue;
            }
            $entries[] = [
                'debitaccountid' => $debitaccountid,
                'debitamount' => amount_helper::parse_amount(
                    $response["debitamount_{$i}"] ?? '',
                    $this->numberformat
                ),
                'creditaccountid' => $creditaccountid,
                'creditamount' => amount_helper::parse_amount(
                    $response["creditamount_{$i}"] ?? '',
                    $this->numberformat
                ),
            ];
        }
        return $entries;
    }

    /**
     * Find the highest entry index N present in the response (debitaccount_N / creditaccount_N).
     *
     * @param array $response The response data.
     * @return int Highest index, or -1 if none.
     */
    protected function max_response_index(array $response): int {
        $maxindex = -1;
        foreach (array_keys($response) as $key) {
            if (preg_match('/^(?:debitaccount|creditaccount)_(\d+)$/', $key, $matches)) {
                $maxindex = max($maxindex, (int)$matches[1]);
            }
        }
        return $maxindex;
    }
}
