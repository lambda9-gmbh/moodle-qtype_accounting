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

namespace qtype_buchungssatz;

/**
 * Unit tests for import_helper class.
 *
 * Tests the name-only CSV format: each line = one account name.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_buchungssatz\import_helper
 */
class import_helper_test extends \advanced_testcase {
    /**
     * Test basic line-per-name parsing.
     */
    public function test_parse_csv_basic(): void {
        $data = "1200 Bank\n8400 Erlöse 19% USt\n1000 Kasse";

        $result = import_helper::parse_csv($data);

        $this->assertCount(3, $result['accounts']);
        $this->assertArrayHasKey('1200 Bank', $result['accounts']);
        $this->assertArrayHasKey('8400 Erlöse 19% USt', $result['accounts']);
        $this->assertArrayHasKey('1000 Kasse', $result['accounts']);
    }

    /**
     * Test that whitespace is trimmed from each line.
     */
    public function test_parse_csv_trims_whitespace(): void {
        $data = "  1200 Bank  \n  8400 Erlöse  ";

        $result = import_helper::parse_csv($data);

        $this->assertCount(2, $result['accounts']);
        $this->assertArrayHasKey('1200 Bank', $result['accounts']);
        $this->assertArrayHasKey('8400 Erlöse', $result['accounts']);
    }

    /**
     * Test that blank lines are skipped.
     */
    public function test_parse_csv_skips_blank_lines(): void {
        $data = "Account 1\n\n\nAccount 2\n\nAccount 3";

        $result = import_helper::parse_csv($data);

        $this->assertCount(3, $result['accounts']);
    }

    /**
     * Test that duplicate names are skipped (first occurrence kept).
     */
    public function test_parse_csv_deduplicates(): void {
        $data = "1200 Bank\n8400 Erlöse\n1200 Bank\n8400 Erlöse";

        $result = import_helper::parse_csv($data);

        $this->assertCount(2, $result['accounts']);
        $this->assertEquals(0, $result['accounts']['1200 Bank']['sortorder']);
        $this->assertEquals(1, $result['accounts']['8400 Erlöse']['sortorder']);
    }

    /**
     * Test that empty input throws an exception.
     */
    public function test_parse_csv_empty_input_throws(): void {
        $this->expectException(\Exception::class);
        import_helper::parse_csv('');
    }

    /**
     * Test that input with only blank lines throws an exception.
     */
    public function test_parse_csv_blank_lines_only_throws(): void {
        $this->expectException(\Exception::class);
        import_helper::parse_csv("\n\n\n");
    }

    /**
     * Test chart name from filename.
     */
    public function test_parse_csv_chart_name_from_filename(): void {
        $data = "Account 1\nAccount 2";

        $result = import_helper::parse_csv($data, 'My Chart.csv');

        $this->assertEquals('My Chart', $result['chartname']);
    }

    /**
     * Test chart name auto-generated when no filename given.
     */
    public function test_parse_csv_chart_name_auto(): void {
        $data = "Account 1\nAccount 2";

        $result = import_helper::parse_csv($data);

        $this->assertStringStartsWith('Imported Chart ', $result['chartname']);
    }

    /**
     * Test that a known header line is skipped.
     */
    public function test_parse_csv_skips_header(): void {
        $data = "Kontoname\n1200 Bank\n8400 Erlöse";

        $result = import_helper::parse_csv($data);

        $this->assertCount(2, $result['accounts']);
        $this->assertArrayNotHasKey('Kontoname', $result['accounts']);
        $this->assertArrayHasKey('1200 Bank', $result['accounts']);
        $this->assertArrayHasKey('8400 Erlöse', $result['accounts']);
    }

    /**
     * Test that header detection is case-insensitive.
     */
    public function test_parse_csv_skips_header_case_insensitive(): void {
        $data = "KONTONAME\n1200 Bank\n8400 Erlöse";

        $result = import_helper::parse_csv($data);

        $this->assertCount(2, $result['accounts']);
        $this->assertArrayNotHasKey('KONTONAME', $result['accounts']);
    }

    /**
     * Test that a non-header first line is kept.
     */
    public function test_parse_csv_keeps_non_header_first_line(): void {
        $data = "1200 Bank\n8400 Erlöse\n1000 Kasse";

        $result = import_helper::parse_csv($data);

        $this->assertCount(3, $result['accounts']);
        $this->assertArrayHasKey('1200 Bank', $result['accounts']);
    }

    /**
     * Test sortorder is assigned sequentially.
     */
    public function test_parse_csv_sortorder(): void {
        $data = "First\nSecond\nThird";

        $result = import_helper::parse_csv($data);

        $this->assertEquals(0, $result['accounts']['First']['sortorder']);
        $this->assertEquals(1, $result['accounts']['Second']['sortorder']);
        $this->assertEquals(2, $result['accounts']['Third']['sortorder']);
    }

    /**
     * Test Windows-style line endings (CRLF).
     */
    public function test_parse_csv_crlf(): void {
        $data = "Account 1\r\nAccount 2\r\nAccount 3";

        $result = import_helper::parse_csv($data);

        $this->assertCount(3, $result['accounts']);
    }

    /**
     * Test that UTF-8 BOM is stripped from the beginning of data.
     */
    public function test_parse_csv_strips_utf8_bom(): void {
        $bom = "\xEF\xBB\xBF";
        $data = $bom . "1200 Bank\n8400 Erlöse";

        $result = import_helper::parse_csv($data);

        $this->assertCount(2, $result['accounts']);
        $this->assertArrayHasKey('1200 Bank', $result['accounts']);
        $this->assertArrayHasKey('8400 Erlöse', $result['accounts']);
    }

    /**
     * Test that Windows-1252 encoded data (German umlauts) is converted to UTF-8.
     */
    public function test_parse_csv_converts_windows1252(): void {
        // The words "Erlöse" and "Büro" in Windows-1252 encoding.
        $data = mb_convert_encoding("1200 Erlöse\n1400 Büro", 'Windows-1252', 'UTF-8');

        $result = import_helper::parse_csv($data);

        $this->assertCount(2, $result['accounts']);
        $this->assertArrayHasKey('1200 Erlöse', $result['accounts']);
        $this->assertArrayHasKey('1400 Büro', $result['accounts']);
    }

    /**
     * Test that valid UTF-8 data passes through unchanged.
     */
    public function test_parse_csv_valid_utf8_unchanged(): void {
        $data = "1200 Erlöse\n1400 Büromöbel\n8400 Übrige";

        $result = import_helper::parse_csv($data);

        $this->assertCount(3, $result['accounts']);
        $this->assertArrayHasKey('1200 Erlöse', $result['accounts']);
        $this->assertArrayHasKey('1400 Büromöbel', $result['accounts']);
        $this->assertArrayHasKey('8400 Übrige', $result['accounts']);
    }

    /**
     * Test finding matching chart by account names.
     */
    public function test_find_matching_chart(): void {
        $this->resetAfterTest();

        // Create a chart with accounts.
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Test Chart', $contextid);
        account_manager::add($chartid, 'Account 1', 0);
        account_manager::add($chartid, 'Account 2', 1);
        account_manager::add($chartid, 'Account 3', 2);

        // Test finding chart with matching accounts.
        $accounts = [
            'Account 1' => ['accountname' => 'Account 1', 'sortorder' => 0],
            'Account 2' => ['accountname' => 'Account 2', 'sortorder' => 1],
        ];
        $foundid = import_helper::find_matching_chart($accounts);
        $this->assertEquals($chartid, $foundid);

        // Test not finding chart when account is missing.
        $accounts = [
            'Account 1' => ['accountname' => 'Account 1', 'sortorder' => 0],
            'NonExistent' => ['accountname' => 'NonExistent', 'sortorder' => 1],
        ];
        $foundid = import_helper::find_matching_chart($accounts);
        $this->assertNull($foundid);
    }

    /**
     * Test full import workflow with chart_manager.
     *
     * @covers \qtype_buchungssatz\chart_manager::import_chart_from_csv
     */
    public function test_chart_import_from_csv(): void {
        $this->resetAfterTest();

        $data = "1200 Bank\n8400 Erlöse 19% USt\n1000 Kasse\n1600 Verbindlichkeiten";

        $contextid = \context_system::instance()->id;
        $result = chart_manager::import_chart_from_csv($data, $contextid, 'Test Chart.csv');

        $this->assertGreaterThan(0, $result['chartid']);
        $this->assertEquals('Test Chart', $result['chartname']);
        $this->assertEquals(4, $result['imported']);
        $this->assertEmpty($result['errors']);

        // Verify the accounts were created correctly.
        $accounts = account_manager::get_for_chart($result['chartid']);
        $this->assertCount(4, $accounts);

        // Verify account names.
        $names = array_map(function ($a) {
            return $a->accountname;
        }, array_values($accounts));
        $this->assertContains('1200 Bank', $names);
        $this->assertContains('8400 Erlöse 19% USt', $names);
    }

    /**
     * Test export to text format (one name per line).
     *
     * @covers \qtype_buchungssatz\chart_manager::export_to_csv
     */
    public function test_export_to_csv(): void {
        $this->resetAfterTest();

        // Create a chart with accounts.
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Export Test', $contextid);
        account_manager::add($chartid, 'Account 1', 0);
        account_manager::add($chartid, 'Account 2', 1);

        $output = chart_manager::export_to_csv($chartid);

        $this->assertStringContainsString('Account 1', $output);
        $this->assertStringContainsString('Account 2', $output);
        // Should NOT contain old-style CSV headers.
        $this->assertStringNotContainsString('Liste;Kontokl;Kontonr;Name', $output);
    }
}
