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

/**
 * Admin settings for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'qtype_accounting/gradingheading',
        get_string('settings_grading', 'qtype_accounting'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'qtype_accounting/defaultgradingmode',
        get_string('defaultgradingmode', 'qtype_accounting'),
        get_string('defaultgradingmode_desc', 'qtype_accounting'),
        \qtype_accounting\scorer::GRADINGMODE_PERCENT,
        [
            \qtype_accounting\scorer::GRADINGMODE_PERCENT => get_string('gradingmode_percent', 'qtype_accounting'),
            \qtype_accounting\scorer::GRADINGMODE_POINTS => get_string('gradingmode_points', 'qtype_accounting'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'qtype_accounting/strictpairs',
        get_string('strictpairs', 'qtype_accounting'),
        get_string('strictpairs_desc', 'qtype_accounting'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'qtype_accounting/accountsonlyexception',
        get_string('accountsonlyexception', 'qtype_accounting'),
        get_string('accountsonlyexception_desc', 'qtype_accounting'),
        0
    ));
}
