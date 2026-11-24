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
 * Create, upload, export and delete the site-wide chart of accounts templates.
 *
 * The templates are listed on the plugin's settings page; this hidden admin page handles the actions.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Login and capabilities are checked by chart_page::setup().
require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

use qtype_accounting\chart_manager;
use qtype_accounting\local\chart_page;

$action = optional_param('action', '', PARAM_ALPHA);
$chartid = optional_param('chartid', 0, PARAM_INT);

$baseurl = new moodle_url('/question/type/accounting/manage_charts.php');
$context = chart_page::setup($baseurl, get_string('sitecharts_add', 'qtype_accounting'));
$doneurl = chart_page::settings_url();

if ($action === 'delete' && $chartid) {
    $chart = chart_page::get_template($chartid);
    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        chart_manager::delete_chart($chartid);
        redirect($doneurl, get_string('chartdeleted', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdelete', 'qtype_accounting', format_string($chart->name)),
        new single_button(
            new moodle_url($baseurl, ['action' => 'delete', 'chartid' => $chartid, 'confirm' => 1]),
            get_string('deletechart', 'qtype_accounting'),
            'post'
        ),
        $doneurl
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'export' && $chartid) {
    chart_page::send_chart_file(chart_page::get_template($chartid));
}

// Create an empty template, then open the editor to add accounts.
$createform = new \qtype_accounting\form\chart_create_form($baseurl);
if ($data = $createform->get_data()) {
    $newchartid = chart_manager::create_chart(trim($data->chartname), $context->id);
    redirect(
        new moodle_url('/question/type/accounting/edit_chart.php', ['chartid' => $newchartid]),
        get_string('chartcreated', 'qtype_accounting'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$uploadform = new \qtype_accounting\form\chart_file_form($baseurl, [
    'submitlabel' => get_string('uploadchartcsv_btn', 'qtype_accounting'),
]);
if ($uploadform->get_data()) {
    $file = $uploadform->get_uploaded_file();
    if (!$file) {
        redirect($doneurl, get_string('csvfilerequired', 'qtype_accounting'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $result = chart_manager::import_chart_from_csv($file->get_content(), $context->id, $file->get_filename());
    chart_page::redirect_import_result($doneurl, $result);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sitecharts_add', 'qtype_accounting'));
echo html_writer::link($doneurl, '&laquo; ' . get_string('sitecharts', 'qtype_accounting'));

echo $OUTPUT->heading(get_string('createchart', 'qtype_accounting'), 3);
$createform->display();

echo $OUTPUT->heading(get_string('uploadchartcsv', 'qtype_accounting'), 3);
$uploadform->display();

echo $OUTPUT->footer();
