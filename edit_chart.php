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
 * Edit a site-wide chart of accounts template and its accounts.
 *
 * Questions keep their own copy of the accounts, so changes here only affect later copies.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Login and capabilities are checked by chart_page::setup().
require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

use qtype_accounting\account_manager;
use qtype_accounting\local\chart_page;

$chartid = required_param('chartid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$accountid = optional_param('accountid', 0, PARAM_INT);

$manageurl = chart_page::settings_url();
$baseurl = new moodle_url('/question/type/accounting/edit_chart.php', ['chartid' => $chartid]);
chart_page::setup($baseurl, get_string('editchart', 'qtype_accounting'));
$chart = chart_page::get_template($chartid);
$PAGE->set_title(get_string('editchart', 'qtype_accounting') . ': ' . format_string($chart->name));
$PAGE->navbar->add(format_string($chart->name));

// Delete an account; the JavaScript confirmation posts directly, without JavaScript a confirmation page is shown.
if ($action === 'deleteaccount' && $accountid) {
    $account = $DB->get_record('qtype_accounting_accounts', ['id' => $accountid, 'chartid' => $chartid]);
    if (!$account) {
        redirect($baseurl);
    }
    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        account_manager::delete($accountid);
        redirect($baseurl, get_string('accountdeleted', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeleteaccount', 'qtype_accounting') . ' ' . format_string($account->accountname),
        new single_button(
            new moodle_url($baseurl, ['action' => 'deleteaccount', 'accountid' => $accountid, 'confirm' => 1]),
            get_string('deleteaccount', 'qtype_accounting'),
            'post'
        ),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

$importform = new \qtype_accounting\form\chart_file_form($baseurl, [
    'submitlabel' => get_string('importchart', 'qtype_accounting'),
]);
if ($importform->get_data()) {
    $file = $importform->get_uploaded_file();
    if (!$file) {
        redirect($baseurl, get_string('csvfilerequired', 'qtype_accounting'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $result = account_manager::import_into_chart($chartid, $file->get_content(), $file->get_filename());
    chart_page::redirect_import_result($baseurl, $result);
}

if ($action === 'export') {
    require_sesskey();
    chart_page::send_chart_file($chart);
}

$tsort = optional_param('tsort', '', PARAM_ALPHA);
$tdir = optional_param('tdir', 'asc', PARAM_ALPHA) === 'desc' ? 'desc' : 'asc';
$sortbyname = $tsort === 'accountname';
$accounts = account_manager::get_for_chart($chartid, $sortbyname ? 'accountname ' . strtoupper($tdir) : 'sortorder, accountname');
$rows = [];
foreach ($accounts as $account) {
    $rows[] = [
        'name' => chart_page::account_name_editable($account)->export_for_template($OUTPUT),
        'deleteurl' => (new moodle_url($baseurl, ['action' => 'deleteaccount', 'accountid' => $account->id]))->out(false),
        'confirmmessage' => get_string('confirmdeleteaccount', 'qtype_accounting') . ' ' . format_string($account->accountname),
    ];
}

$PAGE->requires->js_call_amd('qtype_accounting/manage_charts', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading($OUTPUT->render(chart_page::chart_name_editable($chart)));
echo $OUTPUT->notification(get_string('templatechangesinfo', 'qtype_accounting'), \core\output\notification::NOTIFY_INFO, false);

echo $OUTPUT->render_from_template('qtype_accounting/chart_accounts', [
    'chartid' => $chartid,
    'count' => count($rows),
    'hasaccounts' => !empty($rows),
    'sorturl' => (new moodle_url($baseurl, [
        'tsort' => 'accountname',
        'tdir' => ($sortbyname && $tdir === 'asc') ? 'desc' : 'asc',
    ]))->out(false),
    'sortedasc' => $sortbyname && $tdir === 'asc',
    'sorteddesc' => $sortbyname && $tdir === 'desc',
    'accounts' => $rows,
]);

echo html_writer::start_div('qtype_accounting-csv-import mt-4');
echo $OUTPUT->heading(get_string('importchart', 'qtype_accounting'), 3);
$importform->display();
echo html_writer::end_div();

echo html_writer::start_div('qtype_accounting-chart-actions d-flex flex-wrap justify-content-center align-items-center mt-4 mb-3');
echo html_writer::link($manageurl, '&laquo; ' . get_string('sitecharts', 'qtype_accounting'), ['class' => 'btn btn-primary']);
echo html_writer::link(
    new moodle_url($baseurl, ['action' => 'export', 'sesskey' => sesskey()]),
    get_string('exportaccounts', 'qtype_accounting'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();

echo $OUTPUT->footer();
