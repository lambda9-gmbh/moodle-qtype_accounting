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
 * Account loading + dropdown filtering for the Buchungssatz renderer.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Loads and filters the account list used by the question dropdowns.
 *
 * Extracted from {@see \qtype_accounting_renderer} so the renderer can stay focused
 * on HTML output. The provider is stateless: callers pass in the question and response.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account_provider {
    /**
     * Get all accounts from the chart, keyed by ID.
     *
     * Returns an empty array if no chart is configured.
     *
     * @param int $chartid The chart of accounts ID.
     * @return array The list of account records keyed by ID.
     */
    public function get_for_chart(int $chartid): array {
        global $DB;
        if ($chartid <= 0) {
            return [];
        }
        return $DB->get_records(
            'qtype_accounting_accounts',
            ['chartid' => $chartid],
            'accountname'
        );
    }

    /**
     * Build the filtered account list used by all dropdowns on this question.
     *
     * In edit mode with a per-question dropdown limit, the list is narrowed to the
     * correct accounts + a deterministic random sample + any accounts the student
     * has already selected.
     *
     * @param object $question The question definition.
     * @param array $response The current student response (used to preserve selected accounts).
     * @param bool $readonly True when rendering the review/feedback view (no filtering).
     * @return array Account records keyed by ID.
     */
    public function build_filtered_accounts(object $question, array $response, bool $readonly): array {
        $accounts = $this->get_for_chart($question->chartofaccountsid);
        $accountslimit = $question->accountsindropdown ?? 0;
        if ($accountslimit <= 0 || $readonly) {
            return $accounts;
        }
        $selectedids = [];
        foreach ($response as $key => $value) {
            if (preg_match('/^(?:debitaccount|creditaccount)_\d+$/', $key) && $value !== '' && (int)$value > 0) {
                $selectedids[(int)$value] = true;
            }
        }
        return $this->filter_for_dropdown(
            $accounts,
            $question->get_all_correct_account_ids(),
            $accountslimit,
            array_keys($selectedids),
            $question->dropdownseed ?? 0
        );
    }

    /**
     * Filter accounts to a deterministic subset for dropdowns.
     *
     * Builds a shared pool: all correct accounts + all student-selected accounts +
     * N additional random accounts chosen via a seeded shuffle.
     *
     * @param array $allaccounts All available accounts from the chart.
     * @param array $correctaccountids Array of correct account IDs from all entries.
     * @param int $limit Number of additional random accounts to include (0 = all).
     * @param array $selectedaccountids Array of student-selected account IDs.
     * @param int $seed Random seed for deterministic shuffling.
     * @return array The filtered list of account records, sorted by account name.
     */
    public function filter_for_dropdown(
        array $allaccounts,
        array $correctaccountids,
        int $limit,
        array $selectedaccountids = [],
        int $seed = 0
    ): array {
        if ($limit <= 0 || empty($allaccounts)) {
            return $allaccounts;
        }
        $correctset = array_flip($correctaccountids);
        $selectedset = array_flip($selectedaccountids);

        $result = [];
        $otheraccounts = [];
        foreach ($allaccounts as $account) {
            if (isset($correctset[$account->id]) || isset($selectedset[$account->id])) {
                $result[$account->id] = $account;
            } else {
                $otheraccounts[$account->id] = $account;
            }
        }
        if ($limit >= count($otheraccounts)) {
            return $allaccounts;
        }
        if (!empty($otheraccounts)) {
            $otherkeys = array_values(array_keys($otheraccounts));
            $this->seeded_shuffle($otherkeys, $seed);
            $selectedkeys = array_slice($otherkeys, 0, $limit);
            foreach ($selectedkeys as $key) {
                $result[$key] = $otheraccounts[$key];
            }
        }
        uasort($result, function ($a, $b) {
            return strcmp($a->accountname, $b->accountname);
        });
        return $result;
    }

    /**
     * Shuffle an array in place using a deterministic seed.
     *
     * Uses Fisher-Yates (Knuth) shuffle with mt_rand seeded by the given value.
     *
     * @param array $array The array to shuffle (modified in place by reference).
     * @param int $seed The random seed.
     */
    protected function seeded_shuffle(array &$array, int $seed): void {
        mt_srand($seed);
        $count = count($array);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }
        // Reseed to avoid predictable sequences in subsequent mt_rand calls.
        mt_srand();
    }
}
