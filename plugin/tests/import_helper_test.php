<?php
// This file is part of MoFT BuSa - Moodle Question Type Buchungssatz.
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
// along with MoFT BuSa.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_buchungssatz;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for import_helper class.
 *
 * Tests the new CSV format: Liste;Kontokl;Kontonr;Name
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_buchungssatz\import_helper
 */
class import_helper_test extends \advanced_testcase {

    /**
     * Test delimiter detection with semicolon.
     */
    public function test_detect_delimiter_semicolon(): void {
        $line = 'Liste;Kontokl;Kontonr;Name';
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals(';', $delimiter);
    }

    /**
     * Test delimiter detection with comma.
     */
    public function test_detect_delimiter_comma(): void {
        $line = 'Liste,Kontokl,Kontonr,Name';
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals(',', $delimiter);
    }

    /**
     * Test delimiter detection with tab.
     */
    public function test_detect_delimiter_tab(): void {
        $line = "Liste\tKontokl\tKontonr\tName";
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals("\t", $delimiter);
    }

    /**
     * Test delimiter detection with mixed delimiters (semicolon wins).
     */
    public function test_detect_delimiter_mixed(): void {
        $line = 'Liste;Kontokl,with comma;Kontonr;Name';
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals(';', $delimiter);
    }

    /**
     * Test column mapping detection with German headers.
     */
    public function test_detect_column_mapping_with_headers(): void {
        $firstrow = ['Liste', 'Kontokl', 'Kontonr', 'Name'];
        $mapping = import_helper::detect_column_mapping($firstrow);

        $this->assertTrue($mapping['has_header']);
        $this->assertEquals(0, $mapping['columns']['liste']);
        $this->assertEquals(1, $mapping['columns']['kontokl']);
        $this->assertEquals(2, $mapping['columns']['kontonr']);
        $this->assertEquals(3, $mapping['columns']['name']);
    }

    /**
     * Test column mapping detection with alternative header names.
     */
    public function test_detect_column_mapping_alternative_headers(): void {
        $firstrow = ['Kontenplan', 'Kontoklasse', 'Kontonummer', 'Bezeichnung'];
        $mapping = import_helper::detect_column_mapping($firstrow);

        $this->assertTrue($mapping['has_header']);
        $this->assertArrayHasKey('liste', $mapping['columns']);
        $this->assertArrayHasKey('kontokl', $mapping['columns']);
        $this->assertArrayHasKey('kontonr', $mapping['columns']);
        $this->assertArrayHasKey('name', $mapping['columns']);
    }

    /**
     * Test column mapping detection without headers (data starts with text).
     */
    public function test_detect_column_mapping_no_header(): void {
        $firstrow = ['Kontenplan LTN', '0', '01000', 'Immaterielle Vermögensgegenstände'];
        $mapping = import_helper::detect_column_mapping($firstrow);

        // First column doesn't start with digit, so it's treated as header.
        $this->assertTrue($mapping['has_header']);
    }

    /**
     * Test account class validation.
     */
    public function test_validate_account_class(): void {
        $this->assertEquals(0, import_helper::validate_account_class(0));
        $this->assertEquals(1, import_helper::validate_account_class(1));
        $this->assertEquals(2, import_helper::validate_account_class(2));
        $this->assertEquals(3, import_helper::validate_account_class(3));
        $this->assertEquals(4, import_helper::validate_account_class(4));
        $this->assertEquals(5, import_helper::validate_account_class(5));

        // Invalid values should return 0.
        $this->assertEquals(0, import_helper::validate_account_class(-1));
        $this->assertEquals(0, import_helper::validate_account_class(6));
        $this->assertEquals(0, import_helper::validate_account_class(100));
        $this->assertEquals(0, import_helper::validate_account_class('invalid'));
    }

    /**
     * Test parsing CSV with semicolon delimiter.
     */
    public function test_parse_csv_semicolon(): void {
        $csv = "Liste;Kontokl;Kontonr;Name\n";
        $csv .= "Kontenplan LTN;0;01000;Immaterielle Vermögensgegenstände\n";
        $csv .= "Kontenplan LTN;1;11001;Wareneinkauf";

        $result = import_helper::parse_csv($csv);

        $this->assertEquals('Kontenplan LTN', $result['chartname']);
        $this->assertCount(2, $result['accounts']);
        $this->assertArrayHasKey('01000', $result['accounts']);
        $this->assertArrayHasKey('11001', $result['accounts']);
    }

    /**
     * Test parsing CSV with comma delimiter.
     */
    public function test_parse_csv_comma(): void {
        $csv = "Liste,Kontokl,Kontonr,Name\n";
        $csv .= "Test Chart,0,02001,Grundstücke\n";
        $csv .= "Test Chart,1,14002,Bank";

        $result = import_helper::parse_csv($csv);

        $this->assertEquals('Test Chart', $result['chartname']);
        $this->assertCount(2, $result['accounts']);
    }

    /**
     * Test that chart name is extracted from Liste column.
     */
    public function test_parse_csv_extracts_chart_name(): void {
        $csv = "Liste;Kontokl;Kontonr;Name\n";
        $csv .= "Mein Kontenplan 2024;0;01000;Test Account";

        $result = import_helper::parse_csv($csv);

        $this->assertEquals('Mein Kontenplan 2024', $result['chartname']);
    }

    /**
     * Test that accountclass is correctly parsed.
     */
    public function test_parse_csv_account_class(): void {
        $csv = "Liste;Kontokl;Kontonr;Name\n";
        $csv .= "Test;0;01000;Account Class 0\n";
        $csv .= "Test;1;11000;Account Class 1\n";
        $csv .= "Test;2;21000;Account Class 2\n";
        $csv .= "Test;3;31000;Account Class 3\n";
        $csv .= "Test;4;41000;Account Class 4\n";
        $csv .= "Test;5;51000;Account Class 5";

        $result = import_helper::parse_csv($csv);

        $this->assertEquals(0, $result['accounts']['01000']['accountclass']);
        $this->assertEquals(1, $result['accounts']['11000']['accountclass']);
        $this->assertEquals(2, $result['accounts']['21000']['accountclass']);
        $this->assertEquals(3, $result['accounts']['31000']['accountclass']);
        $this->assertEquals(4, $result['accounts']['41000']['accountclass']);
        $this->assertEquals(5, $result['accounts']['51000']['accountclass']);
    }

    /**
     * Test that duplicate account numbers are skipped.
     */
    public function test_parse_csv_skips_duplicates(): void {
        $csv = "Liste;Kontokl;Kontonr;Name\n";
        $csv .= "Test;0;01000;First Account\n";
        $csv .= "Test;0;01000;Duplicate Account\n";
        $csv .= "Test;1;11000;Second Account";

        $result = import_helper::parse_csv($csv);

        $this->assertCount(2, $result['accounts']);
        $this->assertEquals('First Account', $result['accounts']['01000']['accountname']);
    }

    /**
     * Test that empty rows are skipped.
     */
    public function test_parse_csv_skips_empty_rows(): void {
        $csv = "Liste;Kontokl;Kontonr;Name\n";
        $csv .= "Test;0;01000;Account 1\n";
        $csv .= ";;;\n";
        $csv .= "Test;1;11000;Account 2";

        $result = import_helper::parse_csv($csv);

        $this->assertCount(2, $result['accounts']);
    }

    /**
     * Test finding matching chart.
     */
    public function test_find_matching_chart(): void {
        $this->resetAfterTest();

        // Create a chart with accounts.
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Test Chart', $contextid);
        chart_manager::add_account($chartid, '01000', 'Account 1', 0, 0);
        chart_manager::add_account($chartid, '11000', 'Account 2', 1, 1);
        chart_manager::add_account($chartid, '21000', 'Account 3', 2, 2);

        // Test finding chart with matching accounts.
        $accounts = [
            '01000' => ['accountnumber' => '01000', 'accountname' => 'Account 1', 'accountclass' => 0, 'sortorder' => 0],
            '11000' => ['accountnumber' => '11000', 'accountname' => 'Account 2', 'accountclass' => 1, 'sortorder' => 1],
        ];
        $foundid = import_helper::find_matching_chart($accounts);
        $this->assertEquals($chartid, $foundid);

        // Test not finding chart when account is missing.
        $accounts = [
            '01000' => ['accountnumber' => '01000', 'accountname' => 'Account 1', 'accountclass' => 0, 'sortorder' => 0],
            '99999' => ['accountnumber' => '99999', 'accountname' => 'NonExistent', 'accountclass' => 0, 'sortorder' => 1],
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

        $csv = "Liste;Kontokl;Kontonr;Name\n";
        $csv .= "Kontenplan LTN 2.08.2024;0;01000;Immaterielle Vermögensgegenstände\n";
        $csv .= "Kontenplan LTN 2.08.2024;0;02001;Grundstücke\n";
        $csv .= "Kontenplan LTN 2.08.2024;1;11001;Wareneinkauf\n";
        $csv .= "Kontenplan LTN 2.08.2024;1;14002;Bank\n";
        $csv .= "Kontenplan LTN 2.08.2024;2;21000;Eigenkapital\n";
        $csv .= "Kontenplan LTN 2.08.2024;3;32001;Verbindlichkeiten\n";
        $csv .= "Kontenplan LTN 2.08.2024;4;41001;Wareneinsatz\n";
        $csv .= "Kontenplan LTN 2.08.2024;5;50001;Warenverkauf";

        $contextid = \context_system::instance()->id;
        $result = chart_manager::import_chart_from_csv($csv, $contextid);

        $this->assertGreaterThan(0, $result['chartid']);
        $this->assertEquals('Kontenplan LTN 2.08.2024', $result['chartname']);
        $this->assertEquals(8, $result['imported']);
        $this->assertEmpty($result['errors']);

        // Verify the accounts were created correctly.
        $accounts = chart_manager::get_accounts($result['chartid']);
        $this->assertCount(8, $accounts);

        // Check account classes are correct.
        $accountmap = [];
        foreach ($accounts as $acc) {
            $accountmap[$acc->accountnumber] = $acc;
        }

        $this->assertEquals(0, $accountmap['01000']->accountclass);
        $this->assertEquals(0, $accountmap['02001']->accountclass);
        $this->assertEquals(1, $accountmap['11001']->accountclass);
        $this->assertEquals(1, $accountmap['14002']->accountclass);
        $this->assertEquals(2, $accountmap['21000']->accountclass);
        $this->assertEquals(3, $accountmap['32001']->accountclass);
        $this->assertEquals(4, $accountmap['41001']->accountclass);
        $this->assertEquals(5, $accountmap['50001']->accountclass);

        // Verify account names.
        $this->assertEquals('Immaterielle Vermögensgegenstände', $accountmap['01000']->accountname);
        $this->assertEquals('Bank', $accountmap['14002']->accountname);
        $this->assertEquals('Warenverkauf', $accountmap['50001']->accountname);
    }

    /**
     * Test export to CSV.
     *
     * @covers \qtype_buchungssatz\chart_manager::export_to_csv
     */
    public function test_export_to_csv(): void {
        $this->resetAfterTest();

        // Create a chart with accounts.
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Export Test', $contextid);
        chart_manager::add_account($chartid, '01000', 'Account 1', 0, 0);
        chart_manager::add_account($chartid, '11000', 'Account 2', 1, 1);

        $csv = chart_manager::export_to_csv($chartid);

        // Check header.
        $this->assertStringContainsString('Liste;Kontokl;Kontonr;Name', $csv);

        // Check data rows.
        $this->assertStringContainsString('Export Test', $csv);
        $this->assertStringContainsString('01000', $csv);
        $this->assertStringContainsString('Account 1', $csv);
        $this->assertStringContainsString('11000', $csv);
        $this->assertStringContainsString('Account 2', $csv);
    }
}
