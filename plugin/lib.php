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
 * Library functions for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extend course navigation with a "Manage Charts of Accounts" link.
 *
 * @param navigation_node $parentnode The course navigation node.
 * @param stdClass $course The course object.
 * @param context_course $context The course context.
 */
function qtype_buchungssatz_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
    if (has_capability('qtype/buchungssatz:managecharts', $context)) {
        $url = new moodle_url('/question/type/buchungssatz/manage_charts.php', ['courseid' => $course->id]);
        $parentnode->add(
            get_string('managecharts', 'qtype_buchungssatz'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'qtype_buchungssatz_managecharts',
            new pix_icon('icon', '', 'qtype_buchungssatz')
        );
    }
}
