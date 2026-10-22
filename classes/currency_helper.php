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
 * Currencies whose symbol is shown after the amount fields.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class currency_helper {
    /** @var string Currency key that shows no symbol. */
    public const NONE = 'none';

    /** @var string Currency used when neither the question nor the site setting defines a valid one. */
    public const DEFAULT_CURRENCY = 'EUR';

    /** @var string[] Symbol per currency key. */
    protected const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'CHF' => 'CHF',
        self::NONE => '',
    ];

    /**
     * Get the currency choices for the site setting and the question editor.
     *
     * @return string[] Labels keyed by currency key.
     */
    public static function get_options(): array {
        $options = [];
        foreach (array_keys(self::SYMBOLS) as $currency) {
            $options[$currency] = get_string('currency_' . strtolower($currency), 'qtype_accounting');
        }
        return $options;
    }

    /**
     * Whether a currency key is known.
     *
     * @param string|null $currency The currency key.
     * @return bool
     */
    public static function is_valid(?string $currency): bool {
        return $currency !== null && array_key_exists($currency, self::SYMBOLS);
    }

    /**
     * Get the site default currency (site setting defaultcurrency).
     *
     * @return string The currency key.
     */
    public static function get_default(): string {
        $currency = get_config('qtype_accounting', 'defaultcurrency');
        return self::is_valid($currency) ? $currency : self::DEFAULT_CURRENCY;
    }

    /**
     * Normalise a stored or submitted currency key; unknown or empty values fall back to the site default.
     *
     * @param string|null $currency The currency key.
     * @return string A valid currency key.
     */
    public static function normalise(?string $currency): string {
        return self::is_valid($currency) ? $currency : self::get_default();
    }

    /**
     * Get the symbol to show after the amount fields.
     *
     * @param string|null $currency The currency key; empty or unknown keys use the site default.
     * @return string The symbol, or '' if no symbol is shown.
     */
    public static function get_symbol(?string $currency): string {
        return self::SYMBOLS[self::normalise($currency)];
    }
}
