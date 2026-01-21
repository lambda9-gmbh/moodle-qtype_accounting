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

/**
 * Behat steps for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_buchungssatz extends behat_base {

    /**
     * Convert page names to URLs for behat navigation.
     *
     * Recognised page names are:
     * | Page name             | Description                           |
     * | Manage charts         | Chart of accounts management page     |
     * | Edit chart            | Edit a specific chart (requires id)   |
     *
     * @param string $page The page name.
     * @return moodle_url The URL.
     * @throws Exception If the page is not recognised.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'manage charts':
                return new moodle_url('/question/type/buchungssatz/manage_charts.php');
            default:
                throw new Exception("Unrecognised qtype_buchungssatz page '{$page}'");
        }
    }

    /**
     * Convert page names with an identifier to URLs for behat navigation.
     *
     * @param string $page The page name.
     * @param string $identifier The identifier (e.g., chart ID).
     * @return moodle_url The URL.
     * @throws Exception If the page is not recognised.
     */
    protected function resolve_page_instance_url(string $page, string $identifier): moodle_url {
        switch (strtolower($page)) {
            case 'edit chart':
                return new moodle_url('/question/type/buchungssatz/edit_chart.php', ['id' => $identifier]);
            default:
                throw new Exception("Unrecognised qtype_buchungssatz page '{$page}' with identifier '{$identifier}'");
        }
    }
}
