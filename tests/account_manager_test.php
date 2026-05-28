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

namespace qtype_buchungssatz;

/**
 * Unit tests for the account_manager class.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_buchungssatz\account_manager
 */
class account_manager_test extends \advanced_testcase {
    /**
     * Test adding and retrieving accounts.
     */
    public function test_add_and_get_accounts(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Accounts Test', $contextid);

        $id1 = account_manager::add($chartid, '1200 Bank', 0);
        $id2 = account_manager::add($chartid, '8400 Erlöse 19%', 1);
        $id3 = account_manager::add($chartid, '1000 Kasse', 2);

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertGreaterThan(0, $id3);

        $accounts = account_manager::get_for_chart($chartid);
        $this->assertCount(3, $accounts);

        // Accounts are returned sorted alphabetically by accountname.
        $accountnames = array_map(function ($a) {
            return $a->accountname;
        }, array_values($accounts));
        $this->assertEquals(['1000 Kasse', '1200 Bank', '8400 Erlöse 19%'], $accountnames);
    }

    /**
     * Test updating an account.
     */
    public function test_update_account(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Update Account Test', $contextid);
        $accountid = account_manager::add($chartid, '1200 Bank', 0);

        $result = account_manager::update($accountid, '1201 Bankkonten');
        $this->assertTrue($result);

        $accounts = account_manager::get_for_chart($chartid);
        $account = reset($accounts);
        $this->assertEquals('1201 Bankkonten', $account->accountname);
    }

    /**
     * Test deleting a single account.
     */
    public function test_delete_account(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Delete Account Test', $contextid);
        $id1 = account_manager::add($chartid, '1200 Bank', 0);
        account_manager::add($chartid, '8400 Erlöse', 1);

        $this->assertCount(2, account_manager::get_for_chart($chartid));

        $result = account_manager::delete($id1);
        $this->assertTrue($result);

        $accounts = account_manager::get_for_chart($chartid);
        $this->assertCount(1, $accounts);
        $remaining = reset($accounts);
        $this->assertEquals('8400 Erlöse', $remaining->accountname);
    }

    /**
     * Test that import_into_chart skips duplicate account names.
     */
    public function test_import_into_chart_skips_duplicates(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Dup Test', $contextid);
        account_manager::add($chartid, '1200 Bank', 0);

        $data = "1200 Bank\n8400 Erlöse";

        $result = account_manager::import_into_chart($chartid, $data);

        // Only the new account should be imported.
        $this->assertEquals(1, $result['imported']);
        $accounts = account_manager::get_for_chart($chartid);
        $this->assertCount(2, $accounts);
    }
}
