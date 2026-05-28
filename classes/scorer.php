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
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_buchungssatz;

/**
 * Computes the fraction of correctness for a student response.
 *
 * Extracted from {@see \qtype_buchungssatz_question} so the question class can stay
 * focused on Moodle question API hooks. The scorer is stateless aside from the
 * extra-entry deduction and all-or-nothing toggles passed in at construction time.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer {
    /** @var float Points deducted per extra account (0 = no deduction). */
    protected $extraentrydeduction;
    /** @var bool If true, anything less than full credit is zeroed. */
    protected $allornothinggrading;
    /** @var string Number format ('de' or 'us') used to parse student amounts. */
    protected $numberformat;

    /**
     * Construct a scorer with the question's grading-behaviour toggles.
     *
     * @param float|null $extraentrydeduction Per-extra-account penalty (null/0 disables).
     * @param bool $allornothinggrading True to apply the all-or-nothing toggle.
     * @param string $numberformat 'de' or 'us' for parsing student amount inputs.
     */
    public function __construct($extraentrydeduction, bool $allornothinggrading, string $numberformat) {
        $this->extraentrydeduction = $extraentrydeduction;
        $this->allornothinggrading = $allornothinggrading;
        $this->numberformat = $numberformat;
    }

    /**
     * Calculate the fraction of correctness for a response.
     *
     * Aggregates correct + student entries by account on each side, then earns weight per
     * correct account ID + per matching amount; finally applies the extra-entry deduction
     * and all-or-nothing toggle.
     *
     * @param array $correctentries The teacher's correct entries.
     * @param array $response The student response (flat sollkonto_i/sollbetrag_i/... keys).
     * @return float Fraction in [0, 1].
     */
    public function calculate_fraction(array $correctentries, array $response): float {
        if (empty($correctentries)) {
            return 0;
        }
        $correctaggregated = $this->aggregate_entries($correctentries, true);
        $studentaggregated = $this->aggregate_entries($this->parse_response($response), false);

        $debit = $this->score_side($correctaggregated['debit'], $studentaggregated['debit']);
        $credit = $this->score_side($correctaggregated['credit'], $studentaggregated['credit']);

        $totalweight = $debit['total'] + $credit['total'];
        if ($totalweight == 0) {
            return 0;
        }
        $fraction = ($debit['earned'] + $credit['earned']) / $totalweight;
        $fraction = $this->apply_extra_entry_deduction($fraction, $correctaggregated, $studentaggregated);
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
     * Apply the extra-entry-per-account deduction if configured.
     *
     * @param float $fraction Fraction earned so far.
     * @param array $correctaggregated Aggregated correct entries, keyed by side.
     * @param array $studentaggregated Aggregated student entries, keyed by side.
     * @return float The (possibly reduced) fraction, never below 0.
     */
    protected function apply_extra_entry_deduction(
        float $fraction,
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
        return max(0, $fraction - $extracount * (float)$this->extraentrydeduction);
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
                (int)($entry['sollkontoid'] ?? 0),
                (float)($entry['sollbetrag'] ?? 0),
                $includeweights,
                (int)($entry['weight_sollkonto'] ?? 1),
                (int)($entry['weight_sollbetrag'] ?? 1)
            );
            $this->add_to_side(
                $aggregated,
                'credit',
                (int)($entry['habenkontoid'] ?? 0),
                (float)($entry['habenbetrag'] ?? 0),
                $includeweights,
                (int)($entry['weight_habenkonto'] ?? 1),
                (int)($entry['weight_habenbetrag'] ?? 1)
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
     * @return array Entry rows in {sollkontoid, sollbetrag, habenkontoid, habenbetrag} shape.
     */
    public function parse_response(array $response): array {
        $entries = [];
        $max = $this->max_response_index($response);
        for ($i = 0; $i <= $max; $i++) {
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);
            if ($sollkontoid <= 0 && $habenkontoid <= 0) {
                continue;
            }
            $entries[] = [
                'sollkontoid' => $sollkontoid,
                'sollbetrag' => amount_helper::parse_amount(
                    $response["sollbetrag_{$i}"] ?? '',
                    $this->numberformat
                ),
                'habenkontoid' => $habenkontoid,
                'habenbetrag' => amount_helper::parse_amount(
                    $response["habenbetrag_{$i}"] ?? '',
                    $this->numberformat
                ),
            ];
        }
        return $entries;
    }

    /**
     * Find the highest entry index N present in the response (sollkonto_N / habenkonto_N).
     *
     * @param array $response The response data.
     * @return int Highest index, or -1 if none.
     */
    protected function max_response_index(array $response): int {
        $maxindex = -1;
        foreach (array_keys($response) as $key) {
            if (preg_match('/^(?:sollkonto|habenkonto)_(\d+)$/', $key, $matches)) {
                $maxindex = max($maxindex, (int)$matches[1]);
            }
        }
        return $maxindex;
    }
}
