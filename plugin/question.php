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

    /** @var bool Whether multiple booking entries are allowed. */
    public $allowmultipleentries;

    /** @var int Maximum number of entries. */
    public $maxentries;

    /** @var array The correct answer entries. */
    public $entries = [];

    /** @var bool If true, only full or zero marks (no partial credit). */
    public $allornothinggrading;

    /** @var int Maximum entries allowed for student responses. */
    const MAX_STUDENT_ENTRIES = 20;

    /**
     * Get the expected data keys for the question.
     *
     * @return array The expected data keys and their types.
     */
    public function get_expected_data(): array {
        $expected = [];

        // Always allow up to MAX_STUDENT_ENTRIES for student responses.
        // Use PARAM_RAW for amounts to preserve empty strings (PARAM_FLOAT converts them to 0).
        for ($i = 0; $i < self::MAX_STUDENT_ENTRIES; $i++) {
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

        for ($i = 0; $i < self::MAX_STUDENT_ENTRIES; $i++) {
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
        for ($i = 0; $i < self::MAX_STUDENT_ENTRIES; $i++) {
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
        for ($i = 0; $i < self::MAX_STUDENT_ENTRIES; $i++) {
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
     * Calculate the fraction of correctness.
     *
     * @param array $response The response data.
     * @return float The fraction of correctness (0 to 1).
     */
    protected function calculate_fraction(array $response): float {
        if (empty($this->entries)) {
            return 0;
        }

        $totalfraction = 0;
        $maxfraction = 0;

        // Get student responses.
        $studententries = $this->parse_response($response);

        // Calculate max possible fraction.
        foreach ($this->entries as $entry) {
            $maxfraction += $entry['fraction'];
        }

        if ($maxfraction == 0) {
            return 0;
        }

        // Match student entries against correct entries.
        $matchedcorrect = [];

        foreach ($studententries as $studententry) {
            foreach ($this->entries as $index => $correctentry) {
                if (in_array($index, $matchedcorrect)) {
                    continue;
                }

                if ($this->entries_match($studententry, $correctentry)) {
                    $totalfraction += $correctentry['fraction'];
                    $matchedcorrect[] = $index;
                    break;
                }
            }
        }

        $fraction = $totalfraction / $maxfraction;

        // Extra entry deduction is saved but not applied yet.
        // TODO: Implement when we have access to the quiz slot's max mark.

        // Apply all-or-nothing grading if enabled.
        // If any entry is incorrect, the student gets 0 points.
        if (!empty($this->allornothinggrading) && $fraction < 1) {
            return 0;
        }

        return $fraction;
    }

    /**
     * Count the number of extra entries beyond the correct entries.
     *
     * Extra entries are those beyond the number of correct entries.
     * Each extra line can count as 0, 1, or 2 entries depending on which sides are filled:
     * - Debit side filled (account + amount) = 1 entry
     * - Credit side filled (account + amount) = 1 entry
     * - Both sides filled = 2 entries
     *
     * @param array $studententries The parsed student entries.
     * @return int The count of extra entries.
     */
    protected function count_extra_entries(array $studententries): int {
        $correctcount = count($this->entries);
        $extracount = 0;

        // Only count entries beyond the number of correct entries.
        for ($i = $correctcount; $i < count($studententries); $i++) {
            $entry = $studententries[$i];

            // Count debit side as extra if filled.
            if (!empty($entry['sollkonto'])) {
                $extracount++;
            }

            // Count credit side as extra if filled.
            if (!empty($entry['habenkonto'])) {
                $extracount++;
            }
        }

        return $extracount;
    }

    /**
     * Parse the response into structured entries.
     *
     * @param array $response The response data.
     * @return array The parsed entries.
     */
    protected function parse_response(array $response): array {
        $entries = [];

        for ($i = 0; $i < self::MAX_STUDENT_ENTRIES; $i++) {
            $sollkonto = trim($response["sollkonto_{$i}"] ?? '');
            $habenkonto = trim($response["habenkonto_{$i}"] ?? '');

            if (!empty($sollkonto) || !empty($habenkonto)) {
                $entries[] = [
                    'sollkonto' => $sollkonto,
                    'sollbetrag' => (float)($response["sollbetrag_{$i}"] ?? 0),
                    'habenkonto' => $habenkonto,
                    'habenbetrag' => (float)($response["habenbetrag_{$i}"] ?? 0),
                ];
            }
        }

        return $entries;
    }

    /**
     * Check if a student entry matches a correct entry.
     *
     * @param array $student The student entry.
     * @param array $correct The correct entry.
     * @return bool True if the entries match.
     */
    protected function entries_match(array $student, array $correct): bool {
        // Compare accounts (case-insensitive).
        $sollmatch = strcasecmp($student['sollkonto'], $correct['sollkonto']) === 0;
        $habenmatch = strcasecmp($student['habenkonto'], $correct['habenkonto']) === 0;

        // Compare amounts with small tolerance for floating point.
        $sollamountmatch = abs($student['sollbetrag'] - $correct['sollbetrag']) < 0.01;
        $habenamountmatch = abs($student['habenbetrag'] - $correct['habenbetrag']) < 0.01;

        return $sollmatch && $habenmatch && $sollamountmatch && $habenamountmatch;
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
