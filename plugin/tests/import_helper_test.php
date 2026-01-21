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
        $line = 'Sollkonto;Sollname;Sollbetrag;Habenkonto;Habenname;Habenbetrag';
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals(';', $delimiter);
    }

    /**
     * Test delimiter detection with comma.
     */
    public function test_detect_delimiter_comma(): void {
        $line = 'Sollkonto,Sollname,Sollbetrag,Habenkonto,Habenname,Habenbetrag';
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals(',', $delimiter);
    }

    /**
     * Test delimiter detection with tab.
     */
    public function test_detect_delimiter_tab(): void {
        $line = "Sollkonto\tSollname\tSollbetrag\tHabenkonto\tHabenname\tHabenbetrag";
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals("\t", $delimiter);
    }

    /**
     * Test delimiter detection with mixed delimiters (semicolon wins).
     */
    public function test_detect_delimiter_mixed(): void {
        $line = 'Sollkonto;Sollname,with comma;Sollbetrag';
        $delimiter = import_helper::detect_delimiter($line);
        $this->assertEquals(';', $delimiter);
    }

    /**
     * Test column mapping detection with German headers.
     */
    public function test_detect_column_mapping_german_headers(): void {
        $firstrow = ['Sollkonto', 'Sollname', 'Sollbetrag', 'Habenkonto', 'Habenname', 'Habenbetrag'];
        $mapping = import_helper::detect_column_mapping($firstrow);

        $this->assertTrue($mapping['has_header']);
        $this->assertEquals(0, $mapping['columns']['sollkonto']);
        $this->assertEquals(1, $mapping['columns']['sollname']);
        $this->assertEquals(2, $mapping['columns']['sollbetrag']);
        $this->assertEquals(3, $mapping['columns']['habenkonto']);
        $this->assertEquals(4, $mapping['columns']['habenname']);
        $this->assertEquals(5, $mapping['columns']['habenbetrag']);
    }

    /**
     * Test column mapping detection with English headers.
     */
    public function test_detect_column_mapping_english_headers(): void {
        $firstrow = ['Debit Account', 'Debit Name', 'Debit Amount', 'Credit Account', 'Credit Name', 'Credit Amount'];
        $mapping = import_helper::detect_column_mapping($firstrow);

        $this->assertTrue($mapping['has_header']);
        $this->assertEquals(0, $mapping['columns']['sollkonto']);
        $this->assertEquals(2, $mapping['columns']['sollbetrag']);
        $this->assertEquals(3, $mapping['columns']['habenkonto']);
        $this->assertEquals(5, $mapping['columns']['habenbetrag']);
    }

    /**
     * Test column mapping detection without headers (6 columns - full format).
     */
    public function test_detect_column_mapping_no_header_full(): void {
        $firstrow = ['1200', 'Bank', '1000.00', '8400', 'Revenue', '1000.00'];
        $mapping = import_helper::detect_column_mapping($firstrow);

        $this->assertFalse($mapping['has_header']);
        $this->assertEquals(0, $mapping['columns']['sollkonto']);
        $this->assertEquals(1, $mapping['columns']['sollname']);
        $this->assertEquals(2, $mapping['columns']['sollbetrag']);
        $this->assertEquals(3, $mapping['columns']['habenkonto']);
        $this->assertEquals(4, $mapping['columns']['habenname']);
        $this->assertEquals(5, $mapping['columns']['habenbetrag']);
    }

    /**
     * Test column mapping detection without headers (4 columns - compact format).
     */
    public function test_detect_column_mapping_no_header_compact(): void {
        $firstrow = ['1200', '1000.00', '8400', '1000.00'];
        $mapping = import_helper::detect_column_mapping($firstrow);

        $this->assertFalse($mapping['has_header']);
        $this->assertEquals(0, $mapping['columns']['sollkonto']);
        $this->assertEquals(1, $mapping['columns']['sollbetrag']);
        $this->assertEquals(2, $mapping['columns']['habenkonto']);
        $this->assertEquals(3, $mapping['columns']['habenbetrag']);
    }

    /**
     * Test parsing standard decimal amount.
     */
    public function test_parse_amount_standard(): void {
        $this->assertEquals('1000.00', import_helper::parse_amount('1000.00'));
        $this->assertEquals('500.50', import_helper::parse_amount('500.50'));
        $this->assertEquals('0.00', import_helper::parse_amount('0'));
    }

    /**
     * Test parsing German format amount (comma as decimal separator).
     */
    public function test_parse_amount_german_decimal(): void {
        $this->assertEquals('1000.00', import_helper::parse_amount('1000,00'));
        $this->assertEquals('500.50', import_helper::parse_amount('500,50'));
    }

    /**
     * Test parsing German format amount with thousand separators.
     */
    public function test_parse_amount_german_thousands(): void {
        $this->assertEquals('1234.56', import_helper::parse_amount('1.234,56'));
        $this->assertEquals('1000000.00', import_helper::parse_amount('1.000.000,00'));
    }

    /**
     * Test parsing amount with currency symbols.
     */
    public function test_parse_amount_with_currency(): void {
        $this->assertEquals('1000.00', import_helper::parse_amount('€1000.00'));
        $this->assertEquals('500.00', import_helper::parse_amount('$ 500.00'));
        $this->assertEquals('250.00', import_helper::parse_amount('£250'));
    }

    /**
     * Test parsing amount with whitespace.
     */
    public function test_parse_amount_with_whitespace(): void {
        $this->assertEquals('1000.00', import_helper::parse_amount('  1000.00  '));
        $this->assertEquals('500.00', import_helper::parse_amount('500 . 00'));
    }

    /**
     * Test guessing account type for asset accounts.
     */
    public function test_guess_account_type_asset(): void {
        $this->assertEquals('asset', import_helper::guess_account_type('0100'));
        $this->assertEquals('asset', import_helper::guess_account_type('1200'));
        $this->assertEquals('asset', import_helper::guess_account_type('1400'));
    }

    /**
     * Test guessing account type for liability accounts.
     */
    public function test_guess_account_type_liability(): void {
        $this->assertEquals('liability', import_helper::guess_account_type('2000'));
        $this->assertEquals('liability', import_helper::guess_account_type('2400'));
    }

    /**
     * Test guessing account type for expense accounts.
     */
    public function test_guess_account_type_expense(): void {
        $this->assertEquals('expense', import_helper::guess_account_type('3000'));
        $this->assertEquals('expense', import_helper::guess_account_type('4400'));
        $this->assertEquals('expense', import_helper::guess_account_type('5000'));
        $this->assertEquals('expense', import_helper::guess_account_type('6000'));
        $this->assertEquals('expense', import_helper::guess_account_type('7000'));
    }

    /**
     * Test guessing account type for revenue accounts.
     */
    public function test_guess_account_type_revenue(): void {
        $this->assertEquals('revenue', import_helper::guess_account_type('8000'));
        $this->assertEquals('revenue', import_helper::guess_account_type('8400'));
    }

    /**
     * Test guessing account type for equity accounts.
     */
    public function test_guess_account_type_equity(): void {
        $this->assertEquals('equity', import_helper::guess_account_type('9000'));
        $this->assertEquals('equity', import_helper::guess_account_type('9999'));
    }

    /**
     * Test parsing CSV with semicolon delimiter.
     */
    public function test_parse_csv_semicolon(): void {
        $csv = "Sollkonto;Sollname;Sollbetrag;Habenkonto;Habenname;Habenbetrag\n";
        $csv .= "1200;Bank;1000,00;8400;Umsatzerloese;1000,00\n";
        $csv .= "4400;Mietaufwand;500,00;1200;Bank;500,00";

        $result = import_helper::parse_csv($csv);

        $this->assertEquals(';', $result['delimiter']);
        $this->assertTrue($result['mapping']['has_header']);
        $this->assertCount(3, $result['rows']);
    }

    /**
     * Test parsing CSV with comma delimiter.
     */
    public function test_parse_csv_comma(): void {
        $csv = "1200,Bank,1000.00,8400,Revenue,1000.00\n";
        $csv .= "4400,Rent,500.00,1200,Bank,500.00";

        $result = import_helper::parse_csv($csv);

        $this->assertEquals(',', $result['delimiter']);
        $this->assertFalse($result['mapping']['has_header']);
        $this->assertCount(2, $result['rows']);
    }

    /**
     * Test extracting entries from parsed CSV.
     */
    public function test_extract_entries(): void {
        $rows = [
            ['Sollkonto', 'Sollname', 'Sollbetrag', 'Habenkonto', 'Habenname', 'Habenbetrag'],
            ['1200', 'Bank', '1000,00', '8400', 'Umsatzerloese', '1000,00'],
            ['4400', 'Mietaufwand', '500,00', '1200', 'Bank', '500,00'],
        ];
        $mapping = [
            'has_header' => true,
            'columns' => [
                'sollkonto' => 0,
                'sollname' => 1,
                'sollbetrag' => 2,
                'habenkonto' => 3,
                'habenname' => 4,
                'habenbetrag' => 5,
            ],
        ];

        $result = import_helper::extract_entries($rows, $mapping);

        $this->assertCount(2, $result['entries']);
        $this->assertCount(3, $result['accounts']); // 1200, 8400, 4400

        // Check first entry.
        $this->assertEquals('1200', $result['entries'][0]['sollkonto']);
        $this->assertEquals('1000.00', $result['entries'][0]['sollbetrag']);
        $this->assertEquals('8400', $result['entries'][0]['habenkonto']);
        $this->assertEquals('1000.00', $result['entries'][0]['habenbetrag']);

        // Check second entry.
        $this->assertEquals('4400', $result['entries'][1]['sollkonto']);
        $this->assertEquals('500.00', $result['entries'][1]['sollbetrag']);
        $this->assertEquals('1200', $result['entries'][1]['habenkonto']);
        $this->assertEquals('500.00', $result['entries'][1]['habenbetrag']);

        // Check accounts.
        $this->assertArrayHasKey('1200', $result['accounts']);
        $this->assertArrayHasKey('8400', $result['accounts']);
        $this->assertArrayHasKey('4400', $result['accounts']);
        $this->assertEquals('Bank', $result['accounts']['1200']);
        $this->assertEquals('Umsatzerloese', $result['accounts']['8400']);
    }

    /**
     * Test extracting entries from compact format (4 columns).
     */
    public function test_extract_entries_compact_format(): void {
        $rows = [
            ['1200', '1000.00', '8400', '1000.00'],
            ['4400', '500.00', '1200', '500.00'],
        ];
        $mapping = [
            'has_header' => false,
            'columns' => [
                'sollkonto' => 0,
                'sollbetrag' => 1,
                'habenkonto' => 2,
                'habenbetrag' => 3,
            ],
        ];

        $result = import_helper::extract_entries($rows, $mapping);

        $this->assertCount(2, $result['entries']);
        $this->assertEquals('1200', $result['entries'][0]['sollkonto']);
        $this->assertEquals('1000.00', $result['entries'][0]['sollbetrag']);
    }

    /**
     * Test that empty rows are skipped.
     */
    public function test_extract_entries_skips_empty_rows(): void {
        $rows = [
            ['1200', '1000.00', '8400', '1000.00'],
            ['', '', '', ''],
            ['4400', '500.00', '1200', '500.00'],
        ];
        $mapping = [
            'has_header' => false,
            'columns' => [
                'sollkonto' => 0,
                'sollbetrag' => 1,
                'habenkonto' => 2,
                'habenbetrag' => 3,
            ],
        ];

        $result = import_helper::extract_entries($rows, $mapping);

        $this->assertCount(2, $result['entries']);
    }

    /**
     * Test finding matching chart.
     */
    public function test_find_matching_chart(): void {
        $this->resetAfterTest();

        // Create a chart with accounts.
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Test Chart', 'Description', $contextid);
        chart_manager::add_account($chartid, '1200', 'Bank', 'asset', 0);
        chart_manager::add_account($chartid, '8400', 'Revenue', 'revenue', 1);
        chart_manager::add_account($chartid, '4400', 'Rent', 'expense', 2);

        // Test finding chart with matching accounts.
        $accounts = ['1200' => 'Bank', '8400' => 'Revenue'];
        $foundid = import_helper::find_matching_chart($accounts);
        $this->assertEquals($chartid, $foundid);

        // Test not finding chart when account is missing.
        $accounts = ['1200' => 'Bank', '9999' => 'NonExistent'];
        $foundid = import_helper::find_matching_chart($accounts);
        $this->assertNull($foundid);
    }

    /**
     * Test full import workflow.
     */
    public function test_full_import_workflow(): void {
        $csv = "Sollkonto;Sollname;Sollbetrag;Habenkonto;Habenname;Habenbetrag\n";
        $csv .= "1200;Bank;1000,00;1400;Forderungen;1000,00\n";
        $csv .= "4400;Mietaufwand;500,00;1200;Bank;500,00\n";
        $csv .= "1200;Bank;2500,00;8400;Umsatzerloese;2500,00";

        $parsed = import_helper::parse_csv($csv);
        $result = import_helper::extract_entries($parsed['rows'], $parsed['mapping']);

        $this->assertCount(3, $result['entries']);
        $this->assertCount(4, $result['accounts']); // 1200, 1400, 4400, 8400

        // Verify first entry.
        $this->assertEquals('1200', $result['entries'][0]['sollkonto']);
        $this->assertEquals('1000.00', $result['entries'][0]['sollbetrag']);
        $this->assertEquals('1400', $result['entries'][0]['habenkonto']);
        $this->assertEquals('1000.00', $result['entries'][0]['habenbetrag']);

        // Verify third entry.
        $this->assertEquals('1200', $result['entries'][2]['sollkonto']);
        $this->assertEquals('2500.00', $result['entries'][2]['sollbetrag']);
        $this->assertEquals('8400', $result['entries'][2]['habenkonto']);
        $this->assertEquals('2500.00', $result['entries'][2]['habenbetrag']);
    }
}
