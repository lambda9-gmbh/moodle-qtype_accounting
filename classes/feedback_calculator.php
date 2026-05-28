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
 * Feedback calculation for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_buchungssatz;

/**
 * Aggregates student responses against the correct answer to produce per-cell and per-side feedback.
 *
 * Logic was extracted from {@see \qtype_buchungssatz_renderer} so the renderer can stay
 * focused on HTML output. The methods are stateless; the renderer instantiates one calculator
 * and delegates.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_calculator {
    /**
     * Build per-cell feedback classes for each student entry row.
     *
     * Entries sharing an account ID are aggregated; the resulting per-account status is
     * applied to every row that uses that account, so split entries get consistent feedback.
     *
     * @param object $question The question object with correct entries.
     * @param array $response The student response data.
     * @return array Map of row index => keyed CSS classes for sollkonto, sollbetrag, habenkonto, habenbetrag.
     */
    public function build_cell_feedback_map(object $question, array $response): array {
        $feedbackmap = [];
        if (empty($question->entries)) {
            return $feedbackmap;
        }
        $numberformat = $question->numberformat ?? 'de';
        $correctaggregated = $this->aggregate_entries_for_feedback($question->entries);

        $maxindex = $this->max_response_index($response);
        $studentaggregated = $this->aggregate_response_amounts($response, $maxindex, $numberformat);

        $accountstatus = $this->classify_account_statuses($studentaggregated, $correctaggregated);

        for ($i = 0; $i <= $maxindex; $i++) {
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);
            $row = ['sollkonto' => '', 'sollbetrag' => '', 'habenkonto' => '', 'habenbetrag' => ''];

            if ($sollkontoid > 0 && isset($accountstatus['debit'][$sollkontoid])) {
                $row['sollkonto'] = 'buchungssatz-' . $accountstatus['debit'][$sollkontoid]['account'];
                $row['sollbetrag'] = 'buchungssatz-' . $accountstatus['debit'][$sollkontoid]['amount'];
            }
            if ($habenkontoid > 0 && isset($accountstatus['credit'][$habenkontoid])) {
                $row['habenkonto'] = 'buchungssatz-' . $accountstatus['credit'][$habenkontoid]['account'];
                $row['habenbetrag'] = 'buchungssatz-' . $accountstatus['credit'][$habenkontoid]['amount'];
            }
            $feedbackmap[$i] = $row;
        }
        return $feedbackmap;
    }

    /**
     * Compare student response to correct answer and produce overall per-side feedback.
     *
     * @param object $question The question object with correct entries.
     * @param array $response The student's response data.
     * @return array Feedback with per-side status ('correct'|'partial'|'incorrect'),
     *               per-account details, extra-account flags, and the all_correct flag.
     */
    public function calculate_aggregated_feedback(object $question, array $response): array {
        $feedback = [
            'debit_status' => 'correct',
            'credit_status' => 'correct',
            'all_correct' => true,
            'debit_details' => [],
            'credit_details' => [],
            'has_extra_debit' => false,
            'has_extra_credit' => false,
        ];
        if (empty($question->entries)) {
            return $feedback;
        }

        $numberformat = $question->numberformat ?? 'de';
        $correctaggregated = $this->aggregate_entries_for_feedback($question->entries);
        $studententries = $this->parse_student_response($response, $numberformat);
        $studentaggregated = $this->aggregate_entries_for_feedback($studententries);

        $debit = $this->compare_side($correctaggregated['debit'], $studentaggregated['debit']);
        $credit = $this->compare_side($correctaggregated['credit'], $studentaggregated['credit']);
        $feedback['debit_status'] = $debit['status'];
        $feedback['debit_details'] = $debit['details'];
        $feedback['credit_status'] = $credit['status'];
        $feedback['credit_details'] = $credit['details'];

        $feedback['has_extra_debit'] = $this->has_extra_accounts($studentaggregated['debit'], $correctaggregated['debit']);
        $feedback['has_extra_credit'] = $this->has_extra_accounts(
            $studentaggregated['credit'],
            $correctaggregated['credit']
        );

        // Extra accounts downgrade a 'correct' side to 'partial' but never lift 'incorrect'.
        if ($feedback['has_extra_debit'] && $feedback['debit_status'] === 'correct') {
            $feedback['debit_status'] = 'partial';
        }
        if ($feedback['has_extra_credit'] && $feedback['credit_status'] === 'correct') {
            $feedback['credit_status'] = 'partial';
        }

        $feedback['all_correct'] = ($feedback['debit_status'] === 'correct'
            && $feedback['credit_status'] === 'correct');
        return $feedback;
    }

    /**
     * Aggregate entries by account ID on each side.
     *
     * @param array $entries Entries (each with sollkontoid/sollbetrag/habenkontoid/habenbetrag).
     * @return array ['debit' => [accountid => amount], 'credit' => [accountid => amount]].
     */
    public function aggregate_entries_for_feedback(array $entries): array {
        $aggregated = ['debit' => [], 'credit' => []];
        foreach ($entries as $entry) {
            $sollkontoid = (int)($entry['sollkontoid'] ?? 0);
            if ($sollkontoid > 0) {
                $aggregated['debit'][$sollkontoid] = ($aggregated['debit'][$sollkontoid] ?? 0)
                    + (float)($entry['sollbetrag'] ?? 0);
            }
            $habenkontoid = (int)($entry['habenkontoid'] ?? 0);
            if ($habenkontoid > 0) {
                $aggregated['credit'][$habenkontoid] = ($aggregated['credit'][$habenkontoid] ?? 0)
                    + (float)($entry['habenbetrag'] ?? 0);
            }
        }
        return $aggregated;
    }

    /**
     * Parse a Moodle response array (flat sollkonto_i/sollbetrag_i/... keys) into entry rows.
     *
     * @param array $response The response data.
     * @param string $numberformat The number format ('de' or 'us') for amount parsing.
     * @return array Entry rows in the same shape as $question->entries.
     */
    public function parse_student_response(array $response, string $numberformat = 'de'): array {
        $entries = [];
        $maxindex = $this->max_response_index($response);
        for ($i = 0; $i <= $maxindex; $i++) {
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);
            if ($sollkontoid <= 0 && $habenkontoid <= 0) {
                continue;
            }
            $entries[] = [
                'sollkontoid' => $sollkontoid,
                'sollbetrag' => amount_helper::parse_amount(
                    $response["sollbetrag_{$i}"] ?? '',
                    $numberformat
                ),
                'habenkontoid' => $habenkontoid,
                'habenbetrag' => amount_helper::parse_amount(
                    $response["habenbetrag_{$i}"] ?? '',
                    $numberformat
                ),
            ];
        }
        return $entries;
    }

    /**
     * Find the highest entry index N present in the response, across sollkonto_N / habenkonto_N keys.
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

    /**
     * Aggregate raw response amounts per account ID per side, parsing each amount in the given format.
     *
     * @param array $response The response data.
     * @param int $maxindex The highest valid index (from max_response_index()).
     * @param string $numberformat The number format ('de' or 'us').
     * @return array ['debit' => [accountid => amount], 'credit' => [accountid => amount]].
     */
    protected function aggregate_response_amounts(array $response, int $maxindex, string $numberformat): array {
        $aggregated = ['debit' => [], 'credit' => []];
        for ($i = 0; $i <= $maxindex; $i++) {
            $sollkontoid = (int)($response["sollkonto_{$i}"] ?? 0);
            $habenkontoid = (int)($response["habenkonto_{$i}"] ?? 0);
            if ($sollkontoid > 0) {
                $amount = amount_helper::parse_amount($response["sollbetrag_{$i}"] ?? '', $numberformat);
                $aggregated['debit'][$sollkontoid] = ($aggregated['debit'][$sollkontoid] ?? 0) + $amount;
            }
            if ($habenkontoid > 0) {
                $amount = amount_helper::parse_amount($response["habenbetrag_{$i}"] ?? '', $numberformat);
                $aggregated['credit'][$habenkontoid] = ($aggregated['credit'][$habenkontoid] ?? 0) + $amount;
            }
        }
        return $aggregated;
    }

    /**
     * Classify each student-side account: extra (not in correct) or matched (account/amount).
     *
     * @param array $studentaggregated ['debit' => [...], 'credit' => [...]].
     * @param array $correctaggregated Same shape, holding correct totals.
     * @return array ['debit' => [accountid => ['account' => str, 'amount' => str]], 'credit' => same].
     */
    protected function classify_account_statuses(array $studentaggregated, array $correctaggregated): array {
        $accountstatus = ['debit' => [], 'credit' => []];
        foreach (['debit', 'credit'] as $side) {
            foreach ($studentaggregated[$side] as $account => $studentamount) {
                if (!isset($correctaggregated[$side][$account])) {
                    $accountstatus[$side][$account] = ['account' => 'incorrect', 'amount' => 'incorrect'];
                    continue;
                }
                $correctamount = $correctaggregated[$side][$account];
                $amountstatus = (abs($studentamount - $correctamount) < 0.01) ? 'correct' : 'incorrect';
                $accountstatus[$side][$account] = ['account' => 'correct', 'amount' => $amountstatus];
            }
        }
        return $accountstatus;
    }

    /**
     * Compare the aggregated student totals on one side against the correct totals.
     *
     * @param array $correctside Correct totals for this side, keyed by account ID.
     * @param array $studentside Student totals for this side, keyed by account ID.
     * @return array ['status' => 'correct|partial|incorrect', 'details' => [per-account rows]].
     */
    protected function compare_side(array $correctside, array $studentside): array {
        $details = [];
        $correctcount = 0;
        $totalcount = 0;
        foreach ($correctside as $account => $correctamount) {
            $totalcount++;
            $studentamount = $studentside[$account] ?? null;
            if ($studentamount === null) {
                $details[] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => null,
                    'status' => 'missing',
                ];
            } else if ($studentamount != $correctamount) {
                $details[] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => $studentamount,
                    'status' => 'wrong_amount',
                ];
            } else {
                $correctcount++;
                $details[] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => $studentamount,
                    'status' => 'correct',
                ];
            }
        }
        return ['status' => $this->classify_side_status($correctcount, $totalcount), 'details' => $details];
    }

    /**
     * Map (correctcount, totalcount) to a side status string.
     *
     * @param int $correctcount How many correct accounts the student matched.
     * @param int $totalcount How many correct accounts there are on this side.
     * @return string 'correct', 'partial', or 'incorrect'.
     */
    protected function classify_side_status(int $correctcount, int $totalcount): string {
        if ($totalcount === 0 || $correctcount === $totalcount) {
            return 'correct';
        }
        return $correctcount > 0 ? 'partial' : 'incorrect';
    }

    /**
     * Whether the student-side aggregation includes any account that isn't in the correct answer.
     *
     * @param array $studentside Student totals for this side.
     * @param array $correctside Correct totals for this side.
     * @return bool True if at least one extra account is present.
     */
    protected function has_extra_accounts(array $studentside, array $correctside): bool {
        foreach (array_keys($studentside) as $account) {
            if (!isset($correctside[$account])) {
                return true;
            }
        }
        return false;
    }
}
