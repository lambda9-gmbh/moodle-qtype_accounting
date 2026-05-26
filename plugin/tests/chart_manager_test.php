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
 * Unit tests for the chart_manager class.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_buchungssatz\chart_manager
 */
class chart_manager_test extends \advanced_testcase {
    /**
     * Test creating and retrieving a chart.
     */
    public function test_create_and_get_chart(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('SKR03', $contextid);

        $this->assertGreaterThan(0, $chartid);

        $chart = chart_manager::get_chart($chartid);
        $this->assertNotFalse($chart);
        $this->assertEquals('SKR03', $chart->name);
        $this->assertEquals($contextid, $chart->contextid);
        $this->assertGreaterThan(0, $chart->timecreated);
        $this->assertGreaterThan(0, $chart->timemodified);
    }

    /**
     * Test get_chart returns false for non-existent chart.
     */
    public function test_get_chart_not_found(): void {
        $this->resetAfterTest();

        $chart = chart_manager::get_chart(99999);
        $this->assertFalse($chart);
    }

    /**
     * Test updating a chart name.
     */
    public function test_update_chart(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Old Name', $contextid);

        $result = chart_manager::update_chart($chartid, 'New Name');
        $this->assertTrue($result);

        $chart = chart_manager::get_chart($chartid);
        $this->assertEquals('New Name', $chart->name);
    }

    /**
     * Test deleting a chart also removes its accounts.
     */
    public function test_delete_chart_removes_accounts(): void {
        global $DB;
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Delete Test', $contextid);
        chart_manager::add_account($chartid, '1200 Bank', 0);
        chart_manager::add_account($chartid, '8400 Erlöse', 1);

        // Verify accounts exist.
        $this->assertEquals(2, $DB->count_records('qtype_buchungssatz_accounts', ['chartid' => $chartid]));

        $result = chart_manager::delete_chart($chartid);
        $this->assertTrue($result);

        // Verify chart and accounts are gone.
        $this->assertFalse(chart_manager::get_chart($chartid));
        $this->assertEquals(0, $DB->count_records('qtype_buchungssatz_accounts', ['chartid' => $chartid]));
    }

    /**
     * Test adding and retrieving accounts.
     */
    public function test_add_and_get_accounts(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Accounts Test', $contextid);

        $id1 = chart_manager::add_account($chartid, '1200 Bank', 0);
        $id2 = chart_manager::add_account($chartid, '8400 Erlöse 19%', 1);
        $id3 = chart_manager::add_account($chartid, '1000 Kasse', 2);

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertGreaterThan(0, $id3);

        $accounts = chart_manager::get_accounts($chartid);
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
        $accountid = chart_manager::add_account($chartid, '1200 Bank', 0);

        $result = chart_manager::update_account($accountid, '1201 Bankkonten');
        $this->assertTrue($result);

        $accounts = chart_manager::get_accounts($chartid);
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
        $id1 = chart_manager::add_account($chartid, '1200 Bank', 0);
        chart_manager::add_account($chartid, '8400 Erlöse', 1);

        $this->assertCount(2, chart_manager::get_accounts($chartid));

        $result = chart_manager::delete_account($id1);
        $this->assertTrue($result);

        $accounts = chart_manager::get_accounts($chartid);
        $this->assertCount(1, $accounts);
        $remaining = reset($accounts);
        $this->assertEquals('8400 Erlöse', $remaining->accountname);
    }

    /**
     * Test getting charts for a specific context.
     */
    public function test_get_charts_for_context(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        chart_manager::create_chart('Chart B', $contextid);
        chart_manager::create_chart('Chart A', $contextid);
        chart_manager::create_chart('Chart C', $contextid);

        // Default sort by name ASC.
        $charts = chart_manager::get_charts_for_context($contextid);
        $this->assertCount(3, $charts);

        $names = array_map(function ($c) {
            return $c->name;
        }, array_values($charts));
        $this->assertEquals(['Chart A', 'Chart B', 'Chart C'], $names);
    }

    /**
     * Test getting charts for context returns empty for different context.
     */
    public function test_get_charts_for_context_isolation(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        chart_manager::create_chart('System Chart', $contextid);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        // Different context should return no charts.
        $charts = chart_manager::get_charts_for_context($coursecontext->id);
        $this->assertEmpty($charts);
    }

    /**
     * Test finding a chart by name within a context.
     */
    public function test_get_chart_by_name(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Find Me', $contextid);

        $chart = chart_manager::get_chart_by_name('Find Me', $contextid);
        $this->assertNotFalse($chart);
        $this->assertEquals($chartid, $chart->id);

        // Non-existent name returns false.
        $chart = chart_manager::get_chart_by_name('Does Not Exist', $contextid);
        $this->assertFalse($chart);
    }

    /**
     * Test finding a matching chart in a context by name and account names.
     */
    public function test_find_matching_chart_in_context(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Match Test', $contextid);
        chart_manager::add_account($chartid, '1200 Bank', 0);
        chart_manager::add_account($chartid, '8400 Erlöse', 1);
        chart_manager::add_account($chartid, '1000 Kasse', 2);

        // Search with a subset of accounts — should find the chart.
        $accounts = [
            '1200 Bank' => ['accountname' => '1200 Bank', 'sortorder' => 0],
            '8400 Erlöse' => ['accountname' => '8400 Erlöse', 'sortorder' => 1],
        ];
        $found = chart_manager::find_matching_chart_in_context('Match Test', $contextid, $accounts);
        $this->assertEquals($chartid, $found);
    }

    /**
     * Test find_matching_chart_in_context returns null when accounts don't match.
     */
    public function test_find_matching_chart_no_match(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Partial Match', $contextid);
        chart_manager::add_account($chartid, '1200 Bank', 0);

        // Search for accounts that include one not in the chart.
        $accounts = [
            '1200 Bank' => ['accountname' => '1200 Bank', 'sortorder' => 0],
            'Missing Account' => ['accountname' => 'Missing Account', 'sortorder' => 1],
        ];
        $found = chart_manager::find_matching_chart_in_context('Partial Match', $contextid, $accounts);
        $this->assertNull($found);
    }

    /**
     * Test find_matching_chart_in_context returns null for wrong name.
     */
    public function test_find_matching_chart_wrong_name(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Right Name', $contextid);
        chart_manager::add_account($chartid, '1200 Bank', 0);

        $accounts = [
            '1200 Bank' => ['accountname' => '1200 Bank', 'sortorder' => 0],
        ];
        $found = chart_manager::find_matching_chart_in_context('Wrong Name', $contextid, $accounts);
        $this->assertNull($found);
    }

    /**
     * Test duplicating a chart to a new context.
     */
    public function test_duplicate_chart(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $sourceid = chart_manager::create_chart('Source Chart', $contextid);
        chart_manager::add_account($sourceid, '1200 Bank', 0);
        chart_manager::add_account($sourceid, '8400 Erlöse', 1);

        $course = $this->getDataGenerator()->create_course();
        $targetcontext = \context_course::instance($course->id);

        $newid = chart_manager::duplicate_chart($sourceid, $targetcontext->id);

        $this->assertGreaterThan(0, $newid);
        $this->assertNotEquals($sourceid, $newid);

        // Verify the new chart has the correct properties.
        $newchart = chart_manager::get_chart($newid);
        $this->assertEquals('Source Chart', $newchart->name);
        $this->assertEquals($targetcontext->id, $newchart->contextid);

        // Verify all accounts were copied.
        $newaccounts = chart_manager::get_accounts($newid);
        $this->assertCount(2, $newaccounts);

        $accountnames = array_map(function ($a) {
            return $a->accountname;
        }, array_values($newaccounts));
        $this->assertContains('1200 Bank', $accountnames);
        $this->assertContains('8400 Erlöse', $accountnames);
    }

    /**
     * Test get_charts_for_context with DESC sort.
     */
    public function test_get_charts_for_context_sort_desc(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        chart_manager::create_chart('Alpha', $contextid);
        chart_manager::create_chart('Zulu', $contextid);

        $charts = chart_manager::get_charts_for_context($contextid, 'name', 'DESC');
        $names = array_map(function ($c) {
            return $c->name;
        }, array_values($charts));
        $this->assertEquals(['Zulu', 'Alpha'], $names);
    }

    /**
     * Test that import_chart_from_csv creates chart with accounts.
     */
    public function test_import_chart_from_csv(): void {
        $this->resetAfterTest();

        $data = "1200 Bank\n8400 Erlöse";

        $contextid = \context_system::instance()->id;
        $result = chart_manager::import_chart_from_csv($data, $contextid, 'Test CSV Chart.csv');

        $this->assertGreaterThan(0, $result['chartid']);
        $this->assertEquals('Test CSV Chart', $result['chartname']);
        $this->assertEquals(2, $result['imported']);
        $this->assertEmpty($result['errors']);

        $accounts = chart_manager::get_accounts($result['chartid']);
        $this->assertCount(2, $accounts);
    }

    /**
     * Test that import_from_csv skips duplicate account names.
     */
    public function test_import_from_csv_skips_duplicates(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Dup Test', $contextid);
        chart_manager::add_account($chartid, '1200 Bank', 0);

        $data = "1200 Bank\n8400 Erlöse";

        $result = chart_manager::import_from_csv($chartid, $data);

        // Only the new account should be imported.
        $this->assertEquals(1, $result['imported']);
        $accounts = chart_manager::get_accounts($chartid);
        $this->assertCount(2, $accounts);
    }

    /**
     * Test export_to_csv produces one name per line.
     */
    public function test_export_to_csv(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('CSV Export', $contextid);
        chart_manager::add_account($chartid, '1200 Bank', 0);
        chart_manager::add_account($chartid, '8400 Erlöse 19%', 1);

        $output = chart_manager::export_to_csv($chartid);

        $this->assertStringContainsString('1200 Bank', $output);
        $this->assertStringContainsString('8400 Erlöse 19%', $output);
    }
}
