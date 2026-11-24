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
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Aggregates student responses against the correct answer to produce per-cell and per-side feedback.
 *
 * Logic was extracted from {@see \qtype_accounting_renderer} so the renderer can stay
 * focused on HTML output. The methods are stateless; the renderer instantiates one calculator
 * and delegates.
 *
 * @package    qtype_accounting
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
     * @return array Map of row index => keyed CSS classes for debitaccount, debitamount, creditaccount, creditamount.
     */
    public function build_cell_feedback_map(object $question, array $response): array {
        $feedbackmap = [];
        if (empty($question->entries)) {
            return $feedbackmap;
        }
        $numberformat = $question->numberformat ?? 'de';
        $correctaggregated = aggregator::aggregate($question->entries);
        $studentaggregated = aggregator::aggregate_response($response, $numberformat);
        $maxindex = aggregator::max_index($response);

        $accountstatus = $this->classify_account_statuses($studentaggregated, $correctaggregated);

        for ($i = 0; $i <= $maxindex; $i++) {
            $debitaccountid = (int)($response["debitaccount_{$i}"] ?? 0);
            $creditaccountid = (int)($response["creditaccount_{$i}"] ?? 0);
            $row = ['debitaccount' => '', 'debitamount' => '', 'creditaccount' => '', 'creditamount' => ''];

            if ($debitaccountid > 0 && isset($accountstatus['debit'][$debitaccountid])) {
                $row['debitaccount'] = 'qtype_accounting-' . $accountstatus['debit'][$debitaccountid]['account'];
                $row['debitamount'] = 'qtype_accounting-' . $accountstatus['debit'][$debitaccountid]['amount'];
            }
            if ($creditaccountid > 0 && isset($accountstatus['credit'][$creditaccountid])) {
                $row['creditaccount'] = 'qtype_accounting-' . $accountstatus['credit'][$creditaccountid]['account'];
                $row['creditamount'] = 'qtype_accounting-' . $accountstatus['credit'][$creditaccountid]['amount'];
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
        $correctaggregated = aggregator::aggregate($question->entries);
        $studentaggregated = aggregator::aggregate_response($response, $numberformat);

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
     * Classify each student-side account: extra (not in correct) or matched (account/amount).
     *
     * @param array $studentaggregated ['debit' => [...], 'credit' => [...]].
     * @param array $correctaggregated Same shape, holding correct totals.
     * @return array ['debit' => [accountid => ['account' => str, 'amount' => str]], 'credit' => same].
     */
    protected function classify_account_statuses(array $studentaggregated, array $correctaggregated): array {
        $accountstatus = ['debit' => [], 'credit' => []];
        foreach (['debit', 'credit'] as $side) {
            foreach ($studentaggregated[$side] as $account => $student) {
                if (!isset($correctaggregated[$side][$account])) {
                    $accountstatus[$side][$account] = ['account' => 'incorrect', 'amount' => 'incorrect'];
                    continue;
                }
                $studentamount = $student['amount'];
                $correctamount = $correctaggregated[$side][$account]['amount'];
                $amountstatus = ($studentamount === $correctamount) ? 'correct' : 'incorrect';
                $accountstatus[$side][$account] = ['account' => 'correct', 'amount' => $amountstatus];
            }
        }
        return $accountstatus;
    }

    /**
     * Compare the aggregated student totals on one side against the correct totals.
     *
     * @param array $correctside Correct sums for this side from {@see aggregator::aggregate()}.
     * @param array $studentside Student sums for this side from {@see aggregator::aggregate()}.
     * @return array ['status' => 'correct|partial|incorrect', 'details' => [per-account rows]].
     */
    protected function compare_side(array $correctside, array $studentside): array {
        $details = [];
        $correctcount = 0;
        $totalcount = 0;
        foreach ($correctside as $account => $correct) {
            $totalcount++;
            $correctamount = $correct['amount'];
            $studentamount = $studentside[$account]['amount'] ?? null;
            if ($studentamount === null) {
                $details[] = [
                    'account' => $account,
                    'correct_amount' => $correctamount,
                    'student_amount' => null,
                    'status' => 'missing',
                ];
            } else if ($studentamount !== $correctamount) {
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
