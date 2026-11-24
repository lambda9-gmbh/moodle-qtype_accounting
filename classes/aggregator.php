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

namespace qtype_accounting;

/**
 * Reads booking entries from responses and sums them per side and account.
 *
 * Grading ({@see scorer}) and feedback ({@see feedback_calculator}) both compare these sums,
 * so they always agree on what counts as a correct account or amount.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aggregator {
    /**
     * Find the highest entry index N present in responses (debitaccount_N / creditaccount_N).
     *
     * @param array ...$responses Response data arrays.
     * @return int Highest index, or -1 if none.
     */
    public static function max_index(array ...$responses): int {
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
     * Parse a response array into entry rows; rows without any account are skipped.
     *
     * @param array $response The response data.
     * @param string $numberformat 'de' or 'us', the format of the response amounts.
     * @return array Entry rows with account IDs and canonical decimal amount strings.
     */
    public static function parse_response(array $response, string $numberformat): array {
        $entries = [];
        $max = self::max_index($response);
        for ($i = 0; $i <= $max; $i++) {
            $debitaccountid = (int)($response["debitaccount_{$i}"] ?? 0);
            $creditaccountid = (int)($response["creditaccount_{$i}"] ?? 0);
            if ($debitaccountid <= 0 && $creditaccountid <= 0) {
                continue;
            }
            $entries[] = [
                'debitaccountid' => $debitaccountid,
                'debitamount' => amount_helper::decimal_amount(
                    amount_helper::parse_cents((string)($response["debitamount_{$i}"] ?? ''), $numberformat)
                ),
                'creditaccountid' => $creditaccountid,
                'creditamount' => amount_helper::decimal_amount(
                    amount_helper::parse_cents((string)($response["creditamount_{$i}"] ?? ''), $numberformat)
                ),
            ];
        }
        return $entries;
    }

    /**
     * Sum entries per side and account.
     *
     * @param array $entries Entry rows with canonical decimal amounts and, optionally, weights.
     * @param bool $includeweights Whether to also sum the account and amount weights.
     * @return array ['debit' => [accountid => ['amount' => cents, 'weight_account' => int, 'weight_amount' => int]],
     *               'credit' => same].
     */
    public static function aggregate(array $entries, bool $includeweights = false): array {
        $aggregated = ['debit' => [], 'credit' => []];
        foreach ($entries as $entry) {
            foreach (['debit', 'credit'] as $side) {
                $accountid = (int)($entry[$side . 'accountid'] ?? 0);
                if ($accountid <= 0) {
                    continue;
                }
                $aggregated[$side][$accountid] ??= ['amount' => 0, 'weight_account' => 0, 'weight_amount' => 0];
                $aggregated[$side][$accountid]['amount'] +=
                    amount_helper::parse_cents((string)($entry[$side . 'amount'] ?? 0), 'us');
                if ($includeweights) {
                    $aggregated[$side][$accountid]['weight_account'] += (int)($entry['weight_' . $side . 'account'] ?? 1);
                    $aggregated[$side][$accountid]['weight_amount'] += (int)($entry['weight_' . $side . 'amount'] ?? 1);
                }
            }
        }
        return $aggregated;
    }

    /**
     * Sum a response per side and account.
     *
     * @param array $response The response data.
     * @param string $numberformat 'de' or 'us', the format of the response amounts.
     * @return array Same shape as {@see aggregate()}, without weights.
     */
    public static function aggregate_response(array $response, string $numberformat): array {
        return self::aggregate(self::parse_response($response, $numberformat));
    }
}
