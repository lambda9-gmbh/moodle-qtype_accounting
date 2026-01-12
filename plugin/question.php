<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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

    /**
     * Get the expected data keys for the question.
     *
     * @return array
     */
    public function get_expected_data(): array {
        $expected = [];
        $maxentries = $this->allowmultipleentries ? $this->maxentries : 1;

        for ($i = 0; $i < $maxentries; $i++) {
            $expected["sollkonto_{$i}"] = PARAM_TEXT;
            $expected["sollbetrag_{$i}"] = PARAM_FLOAT;
            $expected["habenkonto_{$i}"] = PARAM_TEXT;
            $expected["habenbetrag_{$i}"] = PARAM_FLOAT;
        }

        return $expected;
    }

    /**
     * Get the correct response.
     *
     * @return array
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
     * @param array $response
     * @return string
     */
    public function summarise_response(array $response): ?string {
        $parts = [];
        $maxentries = $this->allowmultipleentries ? $this->maxentries : 1;

        for ($i = 0; $i < $maxentries; $i++) {
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
     * @param array $response
     * @return bool
     */
    public function is_complete_response(array $response): bool {
        // At least one entry must be filled.
        $sollkonto = $response['sollkonto_0'] ?? '';
        $habenkonto = $response['habenkonto_0'] ?? '';

        return !empty($sollkonto) && !empty($habenkonto);
    }

    /**
     * Check if a response is gradable.
     *
     * @param array $response
     * @return bool
     */
    public function is_gradable_response(array $response): bool {
        return $this->is_complete_response($response);
    }

    /**
     * Get validation error for a response.
     *
     * @param array $response
     * @return string
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
     * @param array $prevresponse
     * @param array $newresponse
     * @return bool
     */
    public function is_same_response(array $prevresponse, array $newresponse): bool {
        $maxentries = $this->allowmultipleentries ? $this->maxentries : 1;

        for ($i = 0; $i < $maxentries; $i++) {
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
     * @param array $response
     * @return array [fraction, state]
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
     * @param array $response
     * @return float
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

        return $totalfraction / $maxfraction;
    }

    /**
     * Parse the response into structured entries.
     *
     * @param array $response
     * @return array
     */
    protected function parse_response(array $response): array {
        $entries = [];
        $maxentries = $this->allowmultipleentries ? $this->maxentries : 1;

        for ($i = 0; $i < $maxentries; $i++) {
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
     * @param array $student
     * @param array $correct
     * @return bool
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
     * @param array $responses
     * @param int $totaltries
     * @return float
     */
    public function compute_final_grade($responses, $totaltries): float {
        $fraction = 0;
        foreach ($responses as $response) {
            $fraction = max($fraction, $this->calculate_fraction($response));
        }
        return $fraction;
    }
}
