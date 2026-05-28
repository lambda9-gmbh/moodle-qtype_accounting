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
 * Helper class for parsing amount strings using the known number format.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amount_helper {
    /**
     * Parse an amount string to a float using the known number format.
     *
     * Unlike guessing-based parsing, this method uses the question's configured
     * number format to unambiguously interpret commas and dots.
     *
     * @param string $value The input value to parse.
     * @param string $format The number format: 'de' (German) or 'us' (US).
     * @return float The parsed numeric value.
     */
    public static function parse_amount(string $value, string $format = 'de'): float {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        if ($format === 'us') {
            // US: comma = thousands separator, dot = decimal.
            $value = str_replace(',', '', $value);
        } else {
            // German/EU: dot = thousands separator, comma = decimal.
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return (float)$value;
    }
}
