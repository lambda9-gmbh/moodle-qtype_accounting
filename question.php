<?php
// This file is part of Moodle - https://moodle.org/
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
// along with MoFT BuSa.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Buchungssatz question definition class.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Represents a Buchungssatz question.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_question extends question_graded_automatically {
    /** @var int Chart of accounts ID. */
    public $chartofaccountsid;

    /** @var int Number of accounts to show in dropdown (0 = all). */
    public $accountsindropdown;

    /** @var string Number format: 'de' or 'us'. */
    public $numberformat;

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

    /** @var int|null Random seed for deterministic dropdown filtering. */
    public $dropdownseed;

    /** @var array Map of account ID => account name for display lookups. */
    public $accountsmap = [];

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
     * Declares fields for indices 0 through a generous upper bound based on the
     * number of correct entries. Uses PARAM_RAW for amounts to preserve empty
     * strings (PARAM_FLOAT converts them to 0).
     *
     * @return array The expected data keys and their types.
     */
    public function get_expected_data(): array {
        $expected = [];

        // Use a generous upper bound: number of correct entries + headroom for student additions.
        $maxindex = max(count($this->entries), 1) + 19;
        for ($i = 0; $i <= $maxindex; $i++) {
            $expected["sollkonto_{$i}"] = PARAM_RAW;
            $expected["sollbetrag_{$i}"] = PARAM_RAW;
            $expected["habenkonto_{$i}"] = PARAM_RAW;
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
            $response["sollkonto_{$i}"] = $entry['sollkontoid'] ?? 0;
            $response["sollbetrag_{$i}"] = $entry['sollbetrag'];
            $response["habenkonto_{$i}"] = $entry['habenkontoid'] ?? 0;
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
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $sollbetrag = $response["sollbetrag_{$i}"] ?? '';
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);
            $habenbetrag = $response["habenbetrag_{$i}"] ?? '';

            if ($sollkontoid > 0 || $habenkontoid > 0) {
                $sollname = $this->accountsmap[$sollkontoid] ?? '';
                $habenname = $this->accountsmap[$habenkontoid] ?? '';
                $parts[] = sprintf(
                    "%s %.2f / %s %.2f",
                    $sollname,
                    (float)$sollbetrag,
                    $habenname,
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
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);

            if ($sollkontoid > 0 || $habenkontoid > 0) {
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
     * The algorithm aggregates entries by account ID on each side (Debit/Credit):
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
        $scorer = new \qtype_buchungssatz\scorer(
            $this->extraentrydeduction,
            (bool)$this->allornothinggrading,
            $this->numberformat
        );
        return $scorer->calculate_fraction($this->entries, $response);
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

    /**
     * Set up the random seed for dropdown account filtering when an attempt starts.
     *
     * @param question_attempt_step $step The first step of the attempt.
     * @param int $variant The question variant.
     */
    public function start_attempt(question_attempt_step $step, $variant): void {
        parent::start_attempt($step, $variant);
        $step->set_qt_var('_dropdownseed', (string) mt_rand());
    }

    /**
     * Restore the random seed for dropdown account filtering from a prior attempt step.
     *
     * @param question_attempt_step $step The step containing the saved state.
     */
    public function apply_attempt_state(question_attempt_step $step): void {
        parent::apply_attempt_state($step);
        $seed = $step->get_qt_var('_dropdownseed');
        if ($seed !== null && $seed !== '') {
            $this->dropdownseed = (int) $seed;
        } else {
            // Fallback for attempts started before this feature.
            $this->dropdownseed = crc32('buchungssatz_' . $this->id);
        }
    }

    /**
     * Get all unique correct account IDs from all entries (both soll and haben).
     *
     * @return array List of unique account ID integers.
     */
    public function get_all_correct_account_ids(): array {
        $ids = [];
        foreach ($this->entries as $entry) {
            foreach (['sollkontoid', 'habenkontoid'] as $field) {
                $val = (int)($entry[$field] ?? 0);
                if ($val > 0) {
                    $ids[$val] = true;
                }
            }
        }
        return array_keys($ids);
    }
}
