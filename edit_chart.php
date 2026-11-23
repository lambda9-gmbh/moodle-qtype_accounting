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

use qtype_accounting\chart_manager;
use qtype_accounting\account_manager;

$chartid = required_param('chartid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$accountid = optional_param('accountid', 0, PARAM_INT);
$editaccount = optional_param('editaccount', 0, PARAM_INT);

$manageurl = \qtype_accounting\local\chart_page::settings_url();
$baseurl = new moodle_url('/question/type/accounting/edit_chart.php', ['chartid' => $chartid]);
\qtype_accounting\local\chart_page::setup($baseurl, get_string('editchart', 'qtype_accounting'));
$chart = \qtype_accounting\local\chart_page::get_template($chartid);
$PAGE->set_title(get_string('editchart', 'qtype_accounting') . ': ' . format_string($chart->name));
$PAGE->navbar->add(format_string($chart->name));

// Handle rename.
if ($action === 'rename' && data_submitted() && confirm_sesskey()) {
    $newname = required_param('chartname', PARAM_TEXT);
    $newname = trim($newname);
    if (!empty($newname)) {
        chart_manager::update_chart($chartid, $newname);
        redirect($baseurl, get_string('chartrenamed', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($baseurl);
}

// Handle add account.
if ($action === 'addaccount' && data_submitted() && confirm_sesskey()) {
    $accountname = required_param('accountname', PARAM_TEXT);
    $accountname = trim($accountname);

    if (!empty($accountname)) {
        // Determine sort order (append at end).
        $accounts = account_manager::get_for_chart($chartid);
        $maxsort = 0;
        foreach ($accounts as $acc) {
            if ($acc->sortorder > $maxsort) {
                $maxsort = $acc->sortorder;
            }
        }
        account_manager::add($chartid, $accountname, $maxsort + 1);
        redirect($baseurl, get_string('accountadded', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($baseurl);
}

// Handle update account.
if ($action === 'updateaccount' && $accountid && data_submitted() && confirm_sesskey()) {
    global $DB;
    // Verify account belongs to this chart.
    $account = $DB->get_record('qtype_accounting_accounts', ['id' => $accountid, 'chartid' => $chartid]);
    if ($account) {
        $accountname = required_param('accountname', PARAM_TEXT);
        account_manager::update($accountid, trim($accountname));
        redirect($baseurl, get_string('accountupdated', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($baseurl);
}

// Handle delete account.
if ($action === 'deleteaccount' && $accountid) {
    global $DB;
    // Verify account belongs to this chart.
    $account = $DB->get_record('qtype_accounting_accounts', ['id' => $accountid, 'chartid' => $chartid]);
    if (!$account) {
        redirect($baseurl);
    }

    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        account_manager::delete($accountid);
        redirect($baseurl, get_string('accountdeleted', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $warning = html_writer::tag('p', get_string('confirmdeleteaccount', 'qtype_accounting') . ' ' .
        html_writer::tag('strong', format_string($account->accountname)));

    // Show confirmation page.
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        $warning,
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

// Handle account import to existing chart via Moodle filepicker form.
$importform = new \qtype_accounting\form\chart_file_form($baseurl, [
    'submitlabel' => get_string('importchart', 'qtype_accounting'),
]);
if ($importform->get_data()) {
    $file = $importform->get_uploaded_file();
    if (!$file) {
        redirect($baseurl, get_string('csvfilerequired', 'qtype_accounting'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $result = account_manager::import_into_chart($chartid, $file->get_content(), $file->get_filename());
    \qtype_accounting\local\chart_page::redirect_import_result($baseurl, $result);
}

// Handle export.
if ($action === 'export') {
    require_sesskey();
    \qtype_accounting\local\chart_page::send_chart_file($chart);
}

// Display the page.
echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($chart->name));
echo html_writer::div(get_string('templatechangesinfo', 'qtype_accounting'), 'alert alert-info');

// Rename form.
$renameurl = new moodle_url($baseurl, ['action' => 'rename']);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $renameurl->out(false),
    'class' => 'd-flex flex-wrap align-items-center mb-4',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', get_string('chartname', 'qtype_accounting') . ': ', [
    'for' => 'chartname',
    'class' => 'me-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'chartname',
    'name' => 'chartname',
    'value' => $chart->name,
    'class' => 'form-control w-auto me-2',
    'size' => 40,
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

// Accounts table.
$tsort = optional_param('tsort', '', PARAM_ALPHA);
$tdir = optional_param('tdir', 'asc', PARAM_ALPHA);
$tdir = ($tdir === 'desc') ? 'desc' : 'asc';

if ($tsort === 'accountname') {
    $sortorder = ($tdir === 'asc') ? 'accountname ASC' : 'accountname DESC';
} else {
    $sortorder = 'sortorder, accountname';
}

$accounts = account_manager::get_for_chart($chartid, $sortorder);

// Heading row with add-account form on the right.
echo html_writer::start_div('qtype_accounting-account-toolbar d-flex flex-wrap justify-content-between align-items-center mb-3');
echo $OUTPUT->heading(get_string('editaccounts', 'qtype_accounting') . ' (' . count($accounts) . ')', 3, 'mb-0');

$addurl = new moodle_url($baseurl, ['action' => 'addaccount']);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $addurl->out(false),
    'class' => 'd-flex flex-wrap align-items-center',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'accountname',
    'placeholder' => get_string('accountname', 'qtype_accounting'),
    'class' => 'form-control w-auto me-2',
    'size' => 30,
    'required' => 'required',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('addaccount', 'qtype_accounting'),
    'class' => 'btn btn-success',
]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

if (!empty($accounts)) {
    // Render edit forms outside the table for accounts being edited.
    if ($editaccount) {
        $updateurl = new moodle_url($baseurl, ['action' => 'updateaccount', 'accountid' => $editaccount]);
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $updateurl->out(false),
            'id' => 'form_account_' . $editaccount,
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::end_tag('form');
    }

    // Build sortable header for account name column.
    $newdir = ($tsort === 'accountname' && $tdir === 'asc') ? 'desc' : 'asc';
    $sorturl = new moodle_url($baseurl, ['tsort' => 'accountname', 'tdir' => $newdir]);
    $arrow = ($tsort === 'accountname') ? ($tdir === 'asc' ? ' ▲' : ' ▼') : '';
    $accountnameheader = html_writer::link($sorturl, get_string('accountname', 'qtype_accounting') . $arrow);

    $table = new html_table();
    $table->head = [
        $accountnameheader,
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($accounts as $account) {
        $row = new html_table_row();
        $isediting = ($editaccount === (int)$account->id);

        if ($isediting) {
            $formid = 'form_account_' . $account->id;

            // Editable input with form attribute.
            $namecell = html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'accountname',
                'value' => $account->accountname,
                'class' => 'form-control',
                'size' => 40,
                'form' => $formid,
            ]);

            // Save + Cancel buttons.
            $savebtn = html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => get_string('savechanges'),
                'class' => 'btn btn-sm btn-primary me-1',
                'form' => $formid,
            ]);
            $cancelbtn = html_writer::link($baseurl, get_string('cancel'), ['class' => 'btn btn-sm btn-secondary']);
            $actions = $savebtn . ' ' . $cancelbtn;
        } else {
            // Read-only display.
            $namecell = format_string($account->accountname);

            // Edit + Delete buttons.
            $editurl = new moodle_url($baseurl, ['editaccount' => $account->id]);
            $deleteurl = new moodle_url($baseurl, ['action' => 'deleteaccount', 'accountid' => $account->id]);
            $editbtn = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));
            $deletebtn = html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));
            $actions = $editbtn . ' ' . $deletebtn;
        }

        $row->cells = [$namecell, $actions];
        $table->data[] = $row;
    }

    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('noaccounts', 'qtype_accounting'), ['class' => 'alert alert-info']);
}

// CSV import section — full-width Moodle filepicker form.
echo html_writer::start_div('qtype_accounting-csv-import mt-4');
echo $OUTPUT->heading(get_string('importchart', 'qtype_accounting'), 3);
$importform->display();
echo html_writer::end_div();

// Bottom action row: Back button on the left, Export button next to it.
$exporturl = new moodle_url($baseurl, ['action' => 'export', 'sesskey' => sesskey()]);
echo html_writer::start_div(
    'qtype_accounting-chart-actions d-flex flex-wrap justify-content-center align-items-center mt-4 mb-3'
);
echo html_writer::link($manageurl, '&laquo; ' . get_string('sitecharts', 'qtype_accounting'), [
    'class' => 'btn btn-primary',
]);
echo html_writer::link($exporturl, get_string('exportaccounts', 'qtype_accounting'), [
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_div();

echo $OUTPUT->footer();
