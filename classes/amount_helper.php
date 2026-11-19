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
 * Converts decimal amounts to integer cents for monetary calculations.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amount_helper {
    /**
     * Format a canonical database amount for a response or display control.
     *
     * @param string|int|float $amount Canonical decimal amount.
     * @param string $format Question number format.
     * @return string Localised amount, including two decimal places.
     */
    public static function format_amount($amount, string $format = 'de'): string {
        return self::format_cents(self::parse_cents((string)$amount, 'us'), $format);
    }

    /**
     * Parse decimal text directly into cents, without floating-point arithmetic.
     *
     * Extra decimal places are rounded to the nearest cent, with ties away from zero.
     * Canonical database/form amounts use the US decimal separator without grouping.
     *
     * @param string $value The input value to parse.
     * @param string $format The number format: 'de' (German) or 'us' (US).
     * @return int The amount in cents; empty or invalid input is zero.
     */
    public static function parse_cents(string $value, string $format = 'de'): int {
        $value = trim($value);
        if ($format === 'us') {
            // US: comma = thousands separator, dot = decimal.
            $value = str_replace(',', '', $value);
        } else {
            // German/EU: dot = thousands separator, comma = decimal.
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/D', $value, $matches)) {
            return 0;
        }
        $whole = $matches[2];
        $decimals = str_pad($matches[3] ?? '', 3, '0');
        $digits = ltrim($whole . substr($decimals, 0, 2), '0');
        $digits = $digits === '' ? '0' : $digits;
        // Leave room for rounding, and never let PHP promote an overflow to a float.
        $maximum = (string)(PHP_INT_MAX - 1);
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new \InvalidArgumentException('Amount exceeds the integer cents range.');
        }
        $cents = (int)$digits + ($decimals[2] >= '5' ? 1 : 0);
        return $matches[1] === '-' ? -$cents : $cents;
    }

    /**
     * Convert cents to a canonical decimal string for database and XML boundaries.
     *
     * @param int $cents Amount in cents.
     * @return string Decimal amount without grouping, with two decimal places.
     */
    public static function decimal_amount(int $cents): string {
        $digits = str_pad(ltrim((string)$cents, '-'), 3, '0', STR_PAD_LEFT);
        return ($cents < 0 ? '-' : '') . substr($digits, 0, -2) . '.' . substr($digits, -2);
    }

    /**
     * Format cents for display without converting them to a float.
     *
     * @param int $cents Amount in cents.
     * @param string $format German or US number format.
     * @return string Localised amount with grouping and two decimal places.
     */
    public static function format_cents(int $cents, string $format = 'de'): string {
        [$whole, $fraction] = explode('.', self::decimal_amount($cents));
        $grouping = $format === 'us' ? ',' : '.';
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', $grouping, $whole);
        return $whole . ($format === 'us' ? '.' : ',') . $fraction;
    }
}
