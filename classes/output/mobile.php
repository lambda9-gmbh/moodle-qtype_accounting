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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

namespace qtype_accounting\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Supplies the app renderer; question data comes from Moodle's quiz web services.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Return the shared template and controller downloaded by the app.
     *
     * @return array
     */
    public static function mobile_get_accounting(): array {
        global $CFG, $OUTPUT;

        return [
            'templates' => [[
                'id' => 'main',
                'html' => $OUTPUT->render_from_template('qtype_accounting/mobileapp/question', []),
            ]],
            'javascript' => file_get_contents($CFG->dirroot . '/question/type/accounting/js/mobileapp/question.js'),
        ];
    }
}
