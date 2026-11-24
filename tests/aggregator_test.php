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
 * Tests for summing booking entries per side and account.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\aggregator
 */
final class aggregator_test extends \basic_testcase {
    /**
     * Rows of the same account are summed in cents; rows without accounts and gaps are ignored.
     */
    public function test_aggregate_response(): void {
        $response = [
            'debitaccount_0' => 1, 'debitamount_0' => '1.000,50', 'creditaccount_0' => 2, 'creditamount_0' => '600',
            'debitaccount_2' => 1, 'debitamount_2' => '0,50', 'creditaccount_2' => 3, 'creditamount_2' => '401',
            'debitaccount_3' => 0, 'debitamount_3' => '99', 'creditaccount_3' => 0, 'creditamount_3' => '99',
        ];
        $this->assertSame(3, aggregator::max_index($response));
        $this->assertSame([
            'debit' => [1 => ['amount' => 100100, 'weight_account' => 0, 'weight_amount' => 0]],
            'credit' => [
                2 => ['amount' => 60000, 'weight_account' => 0, 'weight_amount' => 0],
                3 => ['amount' => 40100, 'weight_account' => 0, 'weight_amount' => 0],
            ],
        ], aggregator::aggregate_response($response, 'de'));
        $this->assertSame(100100, aggregator::aggregate_response(
            ['debitaccount_0' => 1, 'debitamount_0' => '1,001.00'],
            'us'
        )['debit'][1]['amount']);
    }

    /**
     * Weights of rows with the same account are summed; missing weights count as one.
     */
    public function test_aggregate_weights(): void {
        $entries = [
            ['debitaccountid' => 1, 'debitamount' => '10.00', 'weight_debitaccount' => 3, 'weight_debitamount' => 2],
            ['debitaccountid' => 1, 'debitamount' => '5.25', 'creditaccountid' => 2, 'creditamount' => '15.25'],
        ];
        $this->assertSame([
            'debit' => [1 => ['amount' => 1525, 'weight_account' => 4, 'weight_amount' => 3]],
            'credit' => [2 => ['amount' => 1525, 'weight_account' => 1, 'weight_amount' => 1]],
        ], aggregator::aggregate($entries, true));
    }
}
