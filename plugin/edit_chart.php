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
 * Edit a chart of accounts and its accounts.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use qtype_buchungssatz\chart_manager;

$courseid = required_param('courseid', PARAM_INT);
$chartid = required_param('chartid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$accountid = optional_param('accountid', 0, PARAM_INT);
$editaccount = optional_param('editaccount', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('qtype/buchungssatz:managecharts', $context);

// Verify chart belongs to this course context.
$chart = chart_manager::get_chart($chartid);
if (!$chart || (int)$chart->contextid !== (int)$context->id) {
    throw new moodle_exception('chartnotfound', 'qtype_buchungssatz');
}

$manageurl = new moodle_url('/question/type/buchungssatz/manage_charts.php', ['courseid' => $courseid]);
$baseurl = new moodle_url('/question/type/buchungssatz/edit_chart.php', [
    'courseid' => $courseid,
    'chartid' => $chartid,
]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('editchart', 'qtype_buchungssatz') . ': ' . format_string($chart->name));
$PAGE->set_heading($course->fullname);

// Breadcrumbs.
$PAGE->navbar->add(
    get_string('managecharts', 'qtype_buchungssatz'),
    $manageurl
);
$PAGE->navbar->add(format_string($chart->name));

// Handle rename.
if ($action === 'rename' && confirm_sesskey()) {
    $newname = required_param('chartname', PARAM_TEXT);
    $newname = trim($newname);
    if (!empty($newname)) {
        chart_manager::update_chart($chartid, $newname);
        redirect($baseurl, get_string('chartrenamed', 'qtype_buchungssatz'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($baseurl);
}

// Handle add account.
if ($action === 'addaccount' && confirm_sesskey()) {
    $accountnumber = required_param('accountnumber', PARAM_TEXT);
    $accountname = required_param('accountname', PARAM_TEXT);
    $accountclass = required_param('accountclass', PARAM_INT);

    $accountnumber = trim($accountnumber);
    $accountname = trim($accountname);

    if (!empty($accountnumber) && !empty($accountname)) {
        // Determine sort order (append at end).
        $accounts = chart_manager::get_accounts($chartid);
        $maxsort = 0;
        foreach ($accounts as $acc) {
            if ($acc->sortorder > $maxsort) {
                $maxsort = $acc->sortorder;
            }
        }
        chart_manager::add_account($chartid, $accountnumber, $accountname, $accountclass, $maxsort + 1);
        redirect($baseurl, get_string('accountadded', 'qtype_buchungssatz'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($baseurl);
}

// Handle update account.
if ($action === 'updateaccount' && $accountid && confirm_sesskey()) {
    global $DB;
    // Verify account belongs to this chart.
    $account = $DB->get_record('qtype_buchungssatz_accounts', ['id' => $accountid, 'chartid' => $chartid]);
    if ($account) {
        $accountnumber = required_param('accountnumber', PARAM_TEXT);
        $accountname = required_param('accountname', PARAM_TEXT);
        $accountclass = required_param('accountclass', PARAM_INT);
        chart_manager::update_account($accountid, trim($accountnumber), trim($accountname), $accountclass);
        redirect($baseurl, get_string('accountupdated', 'qtype_buchungssatz'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($baseurl);
}

// Handle delete account.
if ($action === 'deleteaccount' && $accountid) {
    global $DB;
    // Verify account belongs to this chart.
    $account = $DB->get_record('qtype_buchungssatz_accounts', ['id' => $accountid, 'chartid' => $chartid]);
    if (!$account) {
        redirect($baseurl);
    }

    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        chart_manager::delete_account($accountid);
        redirect($baseurl, get_string('accountdeleted', 'qtype_buchungssatz'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Show confirmation page.
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeleteaccount', 'qtype_buchungssatz') . ' <strong>'
            . format_string($account->accountnumber . ' - ' . $account->accountname) . '</strong>',
        new moodle_url($baseurl, ['action' => 'deleteaccount', 'accountid' => $accountid, 'confirm' => 1]),
        $baseurl
    );
    echo $OUTPUT->footer();
    die;
}

// Handle CSV import to existing chart.
if ($action === 'import' && confirm_sesskey()) {
    $csvfile = $_FILES['csvfile'] ?? null;
    if ($csvfile && $csvfile['error'] === UPLOAD_ERR_OK && $csvfile['size'] > 0) {
        $csvcontent = file_get_contents($csvfile['tmp_name']);
        $result = chart_manager::import_from_csv($chartid, $csvcontent);

        if ($result['imported'] > 0) {
            $msg = get_string('imported', 'qtype_buchungssatz', $result['imported']);
            if (!empty($result['errors'])) {
                $msg .= ' ' . get_string('witherrors', 'qtype_buchungssatz') . ': ' . implode(', ', $result['errors']);
            }
            redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            $msg = get_string('chartimportfailed', 'qtype_buchungssatz');
            if (!empty($result['errors'])) {
                $msg .= ': ' . implode(', ', $result['errors']);
            }
            redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        redirect($baseurl, get_string('csvfilerequired', 'qtype_buchungssatz'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Handle export.
if ($action === 'export') {
    $csv = chart_manager::export_to_csv($chartid);
    $filename = clean_filename($chart->name) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    die;
}

// Display the page.
echo $OUTPUT->header();

// Back link.
echo html_writer::link($manageurl, '&laquo; ' . get_string('managecharts', 'qtype_buchungssatz'), ['class' => 'mb-3 d-block']);

echo $OUTPUT->heading(format_string($chart->name));

// Rename form.
$renameurl = new moodle_url($baseurl, ['action' => 'rename']);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $renameurl->out(false),
    'class' => 'form-inline mb-4',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', get_string('chartname', 'qtype_buchungssatz') . ': ', [
    'for' => 'chartname',
    'class' => 'mr-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'chartname',
    'name' => 'chartname',
    'value' => $chart->name,
    'class' => 'form-control mr-2',
    'size' => 40,
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('savechanges'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

// Accounts table.
$accounts = chart_manager::get_accounts($chartid);

echo $OUTPUT->heading(get_string('editaccounts', 'qtype_buchungssatz') . ' (' . count($accounts) . ')', 3);

// Account class options for dropdowns and display.
$accountclassoptions = [];
for ($i = 0; $i <= 5; $i++) {
    $accountclassoptions[$i] = $i . ' - ' . get_string('accountclass_' . $i, 'qtype_buchungssatz');
}

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

    $table = new html_table();
    $table->head = [
        get_string('accountnumber', 'qtype_buchungssatz'),
        get_string('accountname', 'qtype_buchungssatz'),
        get_string('accountclass', 'qtype_buchungssatz'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($accounts as $account) {
        $row = new html_table_row();
        $isediting = ($editaccount === (int)$account->id);

        if ($isediting) {
            $formid = 'form_account_' . $account->id;

            // Editable inputs with form attribute.
            $numbercell = html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'accountnumber',
                'value' => $account->accountnumber,
                'class' => 'form-control',
                'size' => 10,
                'form' => $formid,
            ]);
            $namecell = html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'accountname',
                'value' => $account->accountname,
                'class' => 'form-control',
                'size' => 30,
                'form' => $formid,
            ]);
            $classcell = html_writer::select($accountclassoptions, 'accountclass', $account->accountclass, false, [
                'class' => 'form-control',
                'form' => $formid,
            ]);

            // Save + Cancel buttons.
            $savebtn = html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => get_string('savechanges'),
                'class' => 'btn btn-sm btn-primary mr-1',
                'form' => $formid,
            ]);
            $cancelbtn = html_writer::link($baseurl, get_string('cancel'), ['class' => 'btn btn-sm btn-secondary']);
            $actions = $savebtn . ' ' . $cancelbtn;
        } else {
            // Read-only display.
            $numbercell = format_string($account->accountnumber);
            $namecell = format_string($account->accountname);
            $classcell = $accountclassoptions[$account->accountclass] ?? (string)$account->accountclass;

            // Edit + Delete buttons.
            $editurl = new moodle_url($baseurl, ['editaccount' => $account->id]);
            $deleteurl = new moodle_url($baseurl, ['action' => 'deleteaccount', 'accountid' => $account->id]);
            $editbtn = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));
            $deletebtn = html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));
            $actions = $editbtn . ' ' . $deletebtn;
        }

        $row->cells = [$numbercell, $namecell, $classcell, $actions];
        $table->data[] = $row;
    }

    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('noaccounts', 'qtype_buchungssatz'), ['class' => 'alert alert-info']);
}

// Add account form.
echo $OUTPUT->heading(get_string('addaccount', 'qtype_buchungssatz'), 4);
$addurl = new moodle_url($baseurl, ['action' => 'addaccount']);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $addurl->out(false),
    'class' => 'form-inline mb-4',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'accountnumber',
    'placeholder' => get_string('accountnumber', 'qtype_buchungssatz'),
    'class' => 'form-control mr-2',
    'size' => 10,
    'required' => 'required',
]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'accountname',
    'placeholder' => get_string('accountname', 'qtype_buchungssatz'),
    'class' => 'form-control mr-2',
    'size' => 30,
    'required' => 'required',
]);
echo html_writer::select($accountclassoptions, 'accountclass', 0, false, ['class' => 'form-control mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('addaccount', 'qtype_buchungssatz'),
    'class' => 'btn btn-success',
]);
echo html_writer::end_tag('form');

// CSV import form.
echo $OUTPUT->heading(get_string('importaccounts', 'qtype_buchungssatz'), 4);
$importurl = new moodle_url($baseurl, ['action' => 'import']);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $importurl->out(false),
    'enctype' => 'multipart/form-data',
    'class' => 'form-inline mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'file',
    'name' => 'csvfile',
    'accept' => '.csv,.txt',
    'class' => 'form-control mr-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('importchart', 'qtype_buchungssatz'),
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

// Export button.
$exporturl = new moodle_url($baseurl, ['action' => 'export']);
echo html_writer::link($exporturl, get_string('exportaccounts', 'qtype_buchungssatz'), ['class' => 'btn btn-secondary mb-3']);

echo $OUTPUT->footer();
