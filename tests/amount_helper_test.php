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
 * Exact monetary parsing and formatting.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\amount_helper
 */
final class amount_helper_test extends \advanced_testcase {
    /**
     * Decimal text, including database precision, round-trips without float conversion.
     */
    public function test_decimal_round_trip(): void {
        foreach (['de', 'us'] as $format) {
            $cases = [
                ['0.10', 10], ['0.20', 20], ['0.30', 30], ['-0.01', -1],
                ['1234.56', 123456], ['9999999999999.99', 999999999999999],
            ];
            foreach ($cases as [$decimal, $cents]) {
                $this->assertSame($cents, amount_helper::parse_cents($decimal, 'us'));
                $this->assertSame($decimal, amount_helper::decimal_amount($cents));
                $display = amount_helper::format_amount($decimal, $format);
                $this->assertSame($cents, amount_helper::parse_cents($display, $format));
            }
        }
    }

    /**
     * Cent rounding is decimal, symmetric, and independent of binary floating point.
     */
    public function test_rounding_and_empty_input(): void {
        $cases = [
            '1.004' => 100, '1.005' => 101, '1.015' => 102, '9.995' => 1000,
            '-1.004' => -100, '-1.005' => -101, '.005' => 1, '+0.20' => 20,
            '' => 0, 'invalid' => 0, '0' => 0,
        ];
        foreach ($cases as $decimal => $cents) {
            $this->assertSame($cents, amount_helper::parse_cents((string)$decimal, 'us'));
            $this->assertSame($cents, amount_helper::parse_cents(str_replace('.', ',', (string)$decimal), 'de'));
        }
        $this->assertSame('1.234,56', amount_helper::format_cents(123456, 'de'));
        $this->assertSame('-1,234.56', amount_helper::format_cents(-123456, 'us'));
    }

    /**
     * Overflow must not silently turn cents into a float.
     */
    public function test_overflow_is_rejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        amount_helper::parse_cents('999999999999999999999999.99', 'us');
    }
}
