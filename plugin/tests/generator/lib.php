<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Buchungssatz question type data generator.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Buchungssatz question type data generator.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_generator extends component_generator_base {

    /**
     * Generate a default data set for a Buchungssatz question.
     *
     * @return array The default data.
     */
    public function get_default_question_data(): array {
        return [
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 0,
            'maxentries' => 5,
            'sollkonto' => ['1200'],
            'sollbetrag' => [1000.00],
            'habenkonto' => ['8400'],
            'habenbetrag' => [1000.00],
            'grade' => [100],
            'explanation' => [''],
        ];
    }
}
