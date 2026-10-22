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

// Hidden helper page for creating and uploading site charts; it is reached from the settings page.
$ADMIN->add('qtypesettings', new admin_externalpage(
    'qtype_accounting_sitecharts',
    get_string('sitecharts', 'qtype_accounting'),
    new moodle_url('/question/type/accounting/manage_charts.php'),
    'moodle/site:config',
    true
));

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'qtype_accounting/sitechartsheading',
        get_string('sitecharts', 'qtype_accounting'),
        get_string('sitecharts_desc', 'qtype_accounting')
    ));
    $settings->add(new \qtype_accounting\admin\setting_sitecharts());
}
