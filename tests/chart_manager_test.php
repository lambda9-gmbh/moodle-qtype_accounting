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

#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting\chart_manager::class)]
/**
 * Unit tests for the chart_manager class.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\chart_manager
 */
final class chart_manager_test extends \advanced_testcase {
    /**
     * Deleting a chart must not introduce one query per account.
     */
    public function test_delete_chart_has_bounded_reads(): void {
        global $DB;
        $this->resetAfterTest();
        $chartid = chart_manager::create_chart('Bulk deletion', \context_system::instance()->id);
        for ($i = 0; $i < 30; $i++) {
            account_manager::add($chartid, 'Account ' . $i);
        }
        $before = $DB->perf_get_reads();
        $this->assertTrue(chart_manager::delete_chart($chartid));
        $this->assertLessThanOrEqual(8, $DB->perf_get_reads() - $before);
    }

    /**
     * Test creating and retrieving a chart.
     */
    public function test_create_and_get_chart(): void {
        $this->resetAfterTest();

        $clock = $this->mock_clock_with_frozen(1700000000);
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('SKR03', $contextid);

        $this->assertGreaterThan(0, $chartid);

        $chart = chart_manager::get_chart($chartid);
        $this->assertNotFalse($chart);
        $this->assertEquals('SKR03', $chart->name);
        $this->assertEquals($contextid, $chart->contextid);
        $this->assertEquals(1700000000, $chart->timecreated);
        $this->assertEquals(1700000000, $chart->timemodified);

        $clock->bump(60);
        chart_manager::update_chart($chartid, 'SKR04');
        $chart = chart_manager::get_chart($chartid);
        $this->assertEquals(1700000000, $chart->timecreated);
        $this->assertEquals(1700000060, $chart->timemodified);
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
        account_manager::add($chartid, '1200 Bank', 0);
        account_manager::add($chartid, '8400 Erlöse', 1);

        // Verify accounts exist.
        $this->assertEquals(2, $DB->count_records('qtype_accounting_accounts', ['chartid' => $chartid]));

        $result = chart_manager::delete_chart($chartid);
        $this->assertTrue($result);

        // Verify chart and accounts are gone.
        $this->assertFalse(chart_manager::get_chart($chartid));
        $this->assertEquals(0, $DB->count_records('qtype_accounting_accounts', ['chartid' => $chartid]));
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

        $accounts = account_manager::get_for_chart($result['chartid']);
        $this->assertCount(2, $accounts);
    }

    /**
     * Test export_to_csv produces one name per line.
     */
    public function test_export_to_csv(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('CSV Export', $contextid);
        account_manager::add($chartid, '1200 Bank', 0);
        account_manager::add($chartid, '8400 Erlöse 19%', 1);

        $output = chart_manager::export_to_csv($chartid);

        $this->assertStringContainsString('1200 Bank', $output);
        $this->assertStringContainsString('8400 Erlöse 19%', $output);
    }
}
