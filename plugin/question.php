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

/**
 * Buchungssatz question definition class.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a Buchungssatz question.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_question extends question_graded_automatically {

    /** @var int Chart of accounts ID. */
    public $chartofaccountsid;

    /** @var int Number of accounts to show in dropdown (0 = all). */
    public $accountsindropdown;

    /** @var string Number format: 'de' or 'us'. */
    public $numberformat;

    /** @var string Currency symbol to display. */
    public $currency_symbol;

    /** @var int Number of decimal places for amounts. */
    public $decimalplaces;

    /** @var float|null Points deducted per extra entry. */
    public $extraentrydeduction;

    /** @var bool Whether multiple booking entries are allowed. */
    public $allowmultipleentries;

    /** @var int Maximum number of entries. */
    public $maxentries;

    /** @var array The correct answer entries. */
    public $entries = [];

    /** @var bool If true, only full or zero marks (no partial credit). */
    public $allornothinggrading;

    /**
     * Find the highest entry index present across one or more response arrays.
     *
     * Scans array keys for patterns like sollkonto_N or habenkonto_N and returns
     * the highest N found, or -1 if no matching keys exist.
     *
     * @param array ...$responses One or more response arrays to scan.
     * @return int The highest entry index found, or -1 if none.
     */
    protected static function get_max_entry_index(array ...$responses): int {
        $maxindex = -1;
        foreach ($responses as $response) {
            foreach (array_keys($response) as $key) {
                if (preg_match('/^(?:sollkonto|habenkonto)_(\d+)$/', $key, $matches)) {
                    $maxindex = max($maxindex, (int)$matches[1]);
                }
            }
        }
        return $maxindex;
    }

    /**
     * Get the expected data keys for the question.
     *
     * Scans $_POST to discover the highest entry index submitted, then declares
     * fields for indices 0 through that maximum. This removes the former hard cap.
     *
     * @return array The expected data keys and their types.
     */
    public function get_expected_data(): array {
        $expected = [];

        // Scan POST data to find the highest entry index submitted.
        // Use PARAM_RAW for amounts to preserve empty strings (PARAM_FLOAT converts them to 0).
        $maxindex = max(count($this->entries), 1) + 9;
        foreach (array_keys($_POST) as $key) {
            if (preg_match('/(?:sollkonto|habenkonto)_(\d+)$/', $key, $matches)) {
                $maxindex = max($maxindex, (int)$matches[1]);
            }
        }
        for ($i = 0; $i <= $maxindex; $i++) {
            $expected["sollkonto_{$i}"] = PARAM_TEXT;
            $expected["sollbetrag_{$i}"] = PARAM_RAW;
            $expected["habenkonto_{$i}"] = PARAM_TEXT;
            $expected["habenbetrag_{$i}"] = PARAM_RAW;
        }

        return $expected;
    }

    /**
     * Get the correct response.
     *
     * @return array The correct response data.
     */
    public function get_correct_response(): array {
        $response = [];

        foreach ($this->entries as $i => $entry) {
            $response["sollkonto_{$i}"] = $entry['sollkonto'];
            $response["sollbetrag_{$i}"] = $entry['sollbetrag'];
            $response["habenkonto_{$i}"] = $entry['habenkonto'];
            $response["habenbetrag_{$i}"] = $entry['habenbetrag'];
        }

        return $response;
    }

    /**
     * Summarise the response for display.
     *
     * @param array $response The response data.
     * @return string|null The summary string.
     */
    public function summarise_response(array $response): ?string {
        $parts = [];
        $max = self::get_max_entry_index($response);

        for ($i = 0; $i <= $max; $i++) {
            $sollkonto = $response["sollkonto_{$i}"] ?? '';
            $sollbetrag = $response["sollbetrag_{$i}"] ?? '';
            $habenkonto = $response["habenkonto_{$i}"] ?? '';
            $habenbetrag = $response["habenbetrag_{$i}"] ?? '';

            if (!empty($sollkonto) || !empty($habenkonto)) {
                $parts[] = sprintf(
                    "%s %.2f / %s %.2f",
                    $sollkonto,
                    (float)$sollbetrag,
                    $habenkonto,
                    (float)$habenbetrag
                );
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Check if a response is complete.
     *
     * @param array $response The response data.
     * @return bool True if the response is complete.
     */
    public function is_complete_response(array $response): bool {
        // Response is complete if any entry has an account filled in.
        $max = self::get_max_entry_index($response);
        for ($i = 0; $i <= $max; $i++) {
            $sollkonto = trim($response["sollkonto_{$i}"] ?? '');
            $habenkonto = trim($response["habenkonto_{$i}"] ?? '');

            if (!empty($sollkonto) || !empty($habenkonto)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a response is gradable.
     *
     * @param array $response The response data.
     * @return bool True if the response is gradable.
     */
    public function is_gradable_response(array $response): bool {
        return $this->is_complete_response($response);
    }

    /**
     * Get validation error for a response.
     *
     * @param array $response The response data.
     * @return string The validation error message.
     */
    public function get_validation_error(array $response): string {
        if (!$this->is_complete_response($response)) {
            return get_string('pleaseenteranswer', 'qtype_buchungssatz');
        }
        return '';
    }

    /**
     * Check if two responses are the same.
     *
     * @param array $prevresponse The previous response.
     * @param array $newresponse The new response.
     * @return bool True if the responses are the same.
     */
    public function is_same_response(array $prevresponse, array $newresponse): bool {
        $max = self::get_max_entry_index($prevresponse, $newresponse);
        for ($i = 0; $i <= $max; $i++) {
            $fields = ["sollkonto_{$i}", "sollbetrag_{$i}", "habenkonto_{$i}", "habenbetrag_{$i}"];
            foreach ($fields as $field) {
                $prev = $prevresponse[$field] ?? '';
                $new = $newresponse[$field] ?? '';
                if ($prev != $new) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Grade the response.
     *
     * @param array $response The response data.
     * @return array The grade as [fraction, state].
     */
    public function grade_response(array $response): array {
        $fraction = $this->calculate_fraction($response);

        if ($fraction >= 1) {
            return [$fraction, question_state::$gradedright];
        } else if ($fraction > 0) {
            return [$fraction, question_state::$gradedpartial];
        } else {
            return [0, question_state::$gradedwrong];
        }
    }

    /**
     * Calculate the fraction of correctness using aggregation-based weighted scoring.
     *
     * The algorithm aggregates entries by account on each side (Debit/Credit):
     * 1. Aggregate correct entries: sum amounts and weights for same account on same side
     * 2. Aggregate student entries: sum amounts for same account on same side
     * 3. Compare aggregated answers: for each account in correct answer, check if student
     *    has it with the correct amount
     * 4. Calculate earned weight based on account and amount correctness
     *
     * @param array $response The response data.
     * @return float The fraction of correctness (0 to 1).
     */
    protected function calculate_fraction(array $response): float {
        if (empty($this->entries)) {
            return 0;
        }

        // Aggregate correct entries by account on each side.
        $correctaggregated = $this->aggregate_entries($this->entries, true);

        // Get and aggregate student entries.
        $studententries = $this->parse_response($response);
        $studentaggregated = $this->aggregate_entries($studententries, false);

        // Calculate total possible weight from aggregated correct answer.
        $totalweight = 0;
        foreach ($correctaggregated['debit'] as $accountdata) {
            $totalweight += $accountdata['weight_account'];
            $totalweight += $accountdata['weight_amount'];
        }
        foreach ($correctaggregated['credit'] as $accountdata) {
            $totalweight += $accountdata['weight_account'];
            $totalweight += $accountdata['weight_amount'];
        }

        if ($totalweight == 0) {
            return 0;
        }

        // Calculate earned weight by comparing student's aggregated answer to correct answer.
        $earnedweight = 0;

        // Check Debit (Soll) side.
        foreach ($correctaggregated['debit'] as $account => $correctdata) {
            if (isset($studentaggregated['debit'][$account])) {
                // Student has this account on debit side - earn account weight.
                $earnedweight += $correctdata['weight_account'];

                // Check if amount matches (with floating-point tolerance).
                if ($this->amounts_equal($studentaggregated['debit'][$account]['amount'], $correctdata['amount'])) {
                    $earnedweight += $correctdata['weight_amount'];
                }
            }
            // If student doesn't have the account, they earn 0 for both account and amount.
        }

        // Check Credit (Haben) side.
        foreach ($correctaggregated['credit'] as $account => $correctdata) {
            if (isset($studentaggregated['credit'][$account])) {
                // Student has this account on credit side - earn account weight.
                $earnedweight += $correctdata['weight_account'];

                // Check if amount matches (with floating-point tolerance).
                if ($this->amounts_equal($studentaggregated['credit'][$account]['amount'], $correctdata['amount'])) {
                    $earnedweight += $correctdata['weight_amount'];
                }
            }
            // If student doesn't have the account, they earn 0 for both account and amount.
        }

        $fraction = $earnedweight / $totalweight;

        // Count extra accounts (student accounts not in the correct answer).
        if (!empty($this->extraentrydeduction)) {
            $extracount = 0;
            foreach (array_keys($studentaggregated['debit']) as $account) {
                if (!isset($correctaggregated['debit'][$account])) {
                    $extracount++;
                }
            }
            foreach (array_keys($studentaggregated['credit']) as $account) {
                if (!isset($correctaggregated['credit'][$account])) {
                    $extracount++;
                }
            }
            $fraction -= $extracount * ($this->extraentrydeduction / 100);
            $fraction = max(0, $fraction);
        }

        // Apply all-or-nothing grading if enabled.
        if (!empty($this->allornothinggrading) && $fraction < 1) {
            return 0;
        }

        return $fraction;
    }

    /**
     * Aggregate entries by account on each side (Debit/Credit).
     *
     * For each account on each side, sums the amounts.
     * For correct entries, also sums the weights.
     * Account names are normalized to lowercase for case-insensitive matching.
     *
     * @param array $entries The entries to aggregate.
     * @param bool $includweights Whether to include and aggregate weights (for correct entries).
     * @return array Aggregated data with 'debit' and 'credit' keys, each containing
     *               account => ['amount' => float, 'weight_account' => int, 'weight_amount' => int].
     */
    protected function aggregate_entries(array $entries, bool $includweights): array {
        $aggregated = [
            'debit' => [],
            'credit' => [],
        ];

        foreach ($entries as $entry) {
            // Aggregate Debit (Soll) side.
            // Normalize account name to lowercase for case-insensitive matching.
            $sollkonto = strtolower(trim($entry['sollkonto'] ?? ''));
            if (!empty($sollkonto)) {
                if (!isset($aggregated['debit'][$sollkonto])) {
                    $aggregated['debit'][$sollkonto] = [
                        'amount' => 0,
                        'weight_account' => 0,
                        'weight_amount' => 0,
                    ];
                }
                $aggregated['debit'][$sollkonto]['amount'] += (float)($entry['sollbetrag'] ?? 0);
                if ($includweights) {
                    $aggregated['debit'][$sollkonto]['weight_account'] += ($entry['weight_sollkonto'] ?? 1);
                    $aggregated['debit'][$sollkonto]['weight_amount'] += ($entry['weight_sollbetrag'] ?? 1);
                }
            }

            // Aggregate Credit (Haben) side.
            // Normalize account name to lowercase for case-insensitive matching.
            $habenkonto = strtolower(trim($entry['habenkonto'] ?? ''));
            if (!empty($habenkonto)) {
                if (!isset($aggregated['credit'][$habenkonto])) {
                    $aggregated['credit'][$habenkonto] = [
                        'amount' => 0,
                        'weight_account' => 0,
                        'weight_amount' => 0,
                    ];
                }
                $aggregated['credit'][$habenkonto]['amount'] += (float)($entry['habenbetrag'] ?? 0);
                if ($includweights) {
                    $aggregated['credit'][$habenkonto]['weight_account'] += ($entry['weight_habenkonto'] ?? 1);
                    $aggregated['credit'][$habenkonto]['weight_amount'] += ($entry['weight_habenbetrag'] ?? 1);
                }
            }
        }

        return $aggregated;
    }

    /**
     * Compare two amounts with floating-point tolerance.
     *
     * @param float $amount1 First amount.
     * @param float $amount2 Second amount.
     * @param float $tolerance The tolerance for comparison (default 0.01).
     * @return bool True if amounts are equal within tolerance.
     */
    protected function amounts_equal(float $amount1, float $amount2, float $tolerance = 0.01): bool {
        return abs($amount1 - $amount2) < $tolerance;
    }

    /**
     * Parse the response into structured entries.
     *
     * @param array $response The response data.
     * @return array The parsed entries.
     */
    protected function parse_response(array $response): array {
        $entries = [];
        $max = self::get_max_entry_index($response);

        for ($i = 0; $i <= $max; $i++) {
            $sollkonto = trim($response["sollkonto_{$i}"] ?? '');
            $habenkonto = trim($response["habenkonto_{$i}"] ?? '');

            if (!empty($sollkonto) || !empty($habenkonto)) {
                $entries[] = [
                    'sollkonto' => $sollkonto,
                    'sollbetrag' => \qtype_buchungssatz\amount_helper::parse_amount($response["sollbetrag_{$i}"] ?? '', $this->numberformat),
                    'habenkonto' => $habenkonto,
                    'habenbetrag' => \qtype_buchungssatz\amount_helper::parse_amount($response["habenbetrag_{$i}"] ?? '', $this->numberformat),
                ];
            }
        }

        return $entries;
    }

    /**
     * Compute the final grade.
     *
     * @param array $responses All responses from the attempts.
     * @param int $totaltries The total number of tries.
     * @return float The final grade fraction.
     */
    public function compute_final_grade($responses, $totaltries): float {
        $fraction = 0;
        foreach ($responses as $response) {
            $fraction = max($fraction, $this->calculate_fraction($response));
        }
        return $fraction;
    }
}
