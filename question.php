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
 * Buchungssatz question definition class.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Represents a Buchungssatz question.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_accounting_question extends question_graded_automatically {
    /** Maximum number of answer rows offered by the web and app interfaces. */
    public const MAX_ENTRIES = \qtype_accounting\entry_helper::MAX_ENTRIES;
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

    /** @var string Grading mode: 'percent' or 'points'. */
    public $gradingmode = \qtype_accounting\scorer::GRADINGMODE_PERCENT;

    /** @var bool Points mode: no partial point for a pair with a wrong account or amount (site setting). */
    public $strictpairs = true;

    /** @var bool Points mode: correct accounts without any amounts earn one point (site setting). */
    public $accountsonlyexception = false;

    /** @var int|null Random seed for deterministic dropdown filtering. */
    public $dropdownseed;

    /** @var array Map of account ID => account name for display lookups. */
    public $accountsmap = [];

    /**
     * Allow files from the currently applicable hint when feedback is visible.
     *
     * @param question_attempt $qa The current question attempt.
     * @param question_display_options $options The current display options.
     * @param string $component File component.
     * @param string $filearea File area.
     * @param array $args File path arguments, starting with the item ID.
     * @param bool $forcedownload Whether the file must be downloaded.
     * @return bool Whether access is allowed.
     */
    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component === 'question' && $filearea === 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        }
        return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
    }

    /**
     * Find the highest entry index present across one or more response arrays.
     *
     * Scans array keys for patterns like debitaccount_N or creditaccount_N and returns
     * the highest N found, or -1 if no matching keys exist.
     *
     * @param array ...$responses One or more response arrays to scan.
     * @return int The highest entry index found, or -1 if none.
     */
    protected static function get_max_entry_index(array ...$responses): int {
        $maxindex = -1;
        foreach ($responses as $response) {
            foreach (array_keys($response) as $key) {
                if (preg_match('/^(?:debitaccount|creditaccount)_(\d+)$/', $key, $matches)) {
                    $maxindex = max($maxindex, (int)$matches[1]);
                }
            }
        }
        return $maxindex;
    }

    /**
     * Get the expected data keys for the question.
     *
     * Declares all fields offered by the web and app interfaces, retaining larger
     * imported solutions. Uses PARAM_RAW for amounts to preserve empty
     * strings (PARAM_FLOAT converts them to 0).
     *
     * @return array The expected data keys and their types.
     */
    public function get_expected_data(): array {
        $expected = [];

        // Match the explicit UI limit, retaining support for larger imported questions.
        $maxindex = max(self::MAX_ENTRIES, count($this->entries)) - 1;
        for ($i = 0; $i <= $maxindex; $i++) {
            $expected["debitaccount_{$i}"] = PARAM_RAW;
            $expected["debitamount_{$i}"] = PARAM_RAW;
            $expected["creditaccount_{$i}"] = PARAM_RAW;
            $expected["creditamount_{$i}"] = PARAM_RAW;
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
            $response["debitaccount_{$i}"] = $entry['debitaccountid'] ?? 0;
            $response["debitamount_{$i}"] = \qtype_accounting\amount_helper::format_amount(
                $entry['debitamount'],
                $this->numberformat
            );
            $response["creditaccount_{$i}"] = $entry['creditaccountid'] ?? 0;
            $response["creditamount_{$i}"] = \qtype_accounting\amount_helper::format_amount(
                $entry['creditamount'],
                $this->numberformat
            );
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
            $debitaccountid = (int)($response["debitaccount_{$i}"] ?? 0);
            $debitamount = $response["debitamount_{$i}"] ?? '';
            $creditaccountid = (int)($response["creditaccount_{$i}"] ?? 0);
            $creditamount = $response["creditamount_{$i}"] ?? '';

            if ($debitaccountid > 0 || $creditaccountid > 0) {
                $debitname = $this->accountsmap[$debitaccountid] ?? '';
                $creditname = $this->accountsmap[$creditaccountid] ?? '';
                $parts[] = sprintf(
                    "%s %s / %s %s",
                    $debitname,
                    \qtype_accounting\amount_helper::decimal_amount(
                        \qtype_accounting\amount_helper::parse_cents($debitamount, $this->numberformat)
                    ),
                    $creditname,
                    \qtype_accounting\amount_helper::decimal_amount(
                        \qtype_accounting\amount_helper::parse_cents($creditamount, $this->numberformat)
                    )
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
            $debitaccountid = (int)($response["debitaccount_{$i}"] ?? 0);
            $creditaccountid = (int)($response["creditaccount_{$i}"] ?? 0);

            if ($debitaccountid > 0 || $creditaccountid > 0) {
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
            return get_string('pleaseenteranswer', 'qtype_accounting');
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
            $fields = ["debitaccount_{$i}", "debitamount_{$i}", "creditaccount_{$i}", "creditamount_{$i}"];
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
        $scorer = new \qtype_accounting\scorer(
            $this->extraentrydeduction,
            (bool)$this->allornothinggrading,
            $this->numberformat,
            (string)$this->gradingmode,
            (bool)$this->strictpairs,
            (bool)$this->accountsonlyexception
        );
        return $scorer->calculate_fraction($this->entries, $response);
    }

    /**
     * Clear incorrect parts for interactive hints, retaining correct split bookings.
     *
     * Moodle's standard interactive behaviour applies the configured try penalties.
     *
     * @param array $response Student response.
     * @return array Response with incorrect controls cleared.
     */
    public function clear_wrong_from_response(array $response) {
        $scorer = new \qtype_accounting\scorer(null, false, $this->numberformat);
        $parts = $scorer->response_parts($this->entries, $response);
        foreach ($response as $key => $value) {
            if (preg_match('/^(debit|credit)account_(\d+)$/', $key, $matches)) {
                $status = $parts[$matches[1]][(int)$value] ?? [false, false];
                if (!$status[0]) {
                    $response[$key] = '';
                }
                if (!$status[1]) {
                    $response[$matches[1] . 'amount_' . $matches[2]] = '';
                }
            }
        }
        return $response;
    }

    /**
     * Report correct account/amount parts for interactive hints.
     *
     * @param array $response Student response.
     * @return array Number correct and total number of parts.
     */
    public function get_num_parts_right(array $response) {
        $right = 0;
        $total = 0;
        $scorer = new \qtype_accounting\scorer(null, false, $this->numberformat);
        foreach ($scorer->response_parts($this->entries, $response) as $accounts) {
            foreach ($accounts as $parts) {
                $right += count(array_filter($parts));
                $total += count($parts);
            }
        }
        return [$right, $total];
    }

    /**
     * Classify observed responses for quiz response analysis.
     *
     * @param array $response Student response.
     * @return array Classification keyed by question ID.
     */
    public function classify_response(array $response) {
        if (!$this->is_complete_response($response)) {
            return [$this->id => question_classified_response::no_response()];
        }
        $fraction = $this->calculate_fraction($response);
        return [$this->id => new question_classified_response(
            $fraction >= 1 ? 1 : 0,
            $this->summarise_response($response),
            $fraction
        )];
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
            $this->dropdownseed = crc32('accounting_' . $this->id);
        }
    }

    /**
     * Get all unique correct account IDs from all entries (both debit and credit).
     *
     * @return array List of unique account ID integers.
     */
    public function get_all_correct_account_ids(): array {
        $ids = [];
        foreach ($this->entries as $entry) {
            foreach (['debitaccountid', 'creditaccountid'] as $field) {
                $val = (int)($entry[$field] ?? 0);
                if ($val > 0) {
                    $ids[$val] = true;
                }
            }
        }
        return array_keys($ids);
    }
}
