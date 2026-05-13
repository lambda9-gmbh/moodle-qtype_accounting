<?php
// This file is part of Moodle - https://moodle.org/
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
// along with MoFT BuSa.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Edit a chart of accounts and its accounts.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use qtype_buchungssatz\chart_manager;

/**
 * Form for importing accounts into an existing chart of accounts from a CSV file.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_chart_account_import_form extends moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'chartcsvfile', '', null,
            ['maxbytes' => 2097152, 'accepted_types' => ['.csv', '.txt']]);

        $this->add_action_buttons(false, get_string('importchart', 'qtype_buchungssatz'));
    }
}

$courseid = required_param('courseid', PARAM_INT);
$chartid = required_param('chartid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$accountid = optional_param('accountid', 0, PARAM_INT);
$editaccount = optional_param('editaccount', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

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
if ($returnurl !== '') {
    $manageurl->param('returnurl', $returnurl);
}
$baseurl = new moodle_url('/question/type/buchungssatz/edit_chart.php', [
    'courseid' => $courseid,
    'chartid' => $chartid,
]);
if ($returnurl !== '') {
    $baseurl->param('returnurl', $returnurl);
}

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
    $accountname = required_param('accountname', PARAM_TEXT);
    $accountname = trim($accountname);

    if (!empty($accountname)) {
        // Determine sort order (append at end).
        $accounts = chart_manager::get_accounts($chartid);
        $maxsort = 0;
        foreach ($accounts as $acc) {
            if ($acc->sortorder > $maxsort) {
                $maxsort = $acc->sortorder;
            }
        }
        chart_manager::add_account($chartid, $accountname, $maxsort + 1);
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
        $accountname = required_param('accountname', PARAM_TEXT);
        chart_manager::update_account($accountid, trim($accountname));
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
            . format_string($account->accountname) . '</strong>',
        new moodle_url($baseurl, ['action' => 'deleteaccount', 'accountid' => $accountid, 'confirm' => 1]),
        $baseurl
    );
    echo $OUTPUT->footer();
    die;
}

// Handle CSV import to existing chart via Moodle filepicker form.
$importform = new qtype_buchungssatz_chart_account_import_form($baseurl);
if ($data = $importform->get_data()) {
    global $USER;

    $draftitemid = $data->chartcsvfile;
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);

    if (!empty($files)) {
        $file = reset($files);
        $csvcontent = $file->get_content();
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
    require_sesskey();
    $csv = chart_manager::export_to_csv($chartid);
    $filename = clean_filename($chart->name) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    die;
}

// Display the page.
echo $OUTPUT->header();

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
$tsort = optional_param('tsort', '', PARAM_ALPHA);
$tdir = optional_param('tdir', 'asc', PARAM_ALPHA);
$tdir = ($tdir === 'desc') ? 'desc' : 'asc';

if ($tsort === 'accountname') {
    $sortorder = ($tdir === 'asc') ? 'accountname ASC' : 'accountname DESC';
} else {
    $sortorder = 'sortorder, accountname';
}

$accounts = chart_manager::get_accounts($chartid, $sortorder);

// Heading row with add-account form on the right.
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo $OUTPUT->heading(get_string('editaccounts', 'qtype_buchungssatz') . ' (' . count($accounts) . ')', 3, 'mb-0');

$addurl = new moodle_url($baseurl, ['action' => 'addaccount']);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $addurl->out(false),
    'class' => 'form-inline',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'accountname',
    'placeholder' => get_string('accountname', 'qtype_buchungssatz'),
    'class' => 'form-control mr-2',
    'size' => 30,
    'required' => 'required',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('addaccount', 'qtype_buchungssatz'),
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
    $accountnameheader = html_writer::link($sorturl, get_string('accountname', 'qtype_buchungssatz') . $arrow);

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
                'class' => 'btn btn-sm btn-primary mr-1',
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
    echo html_writer::tag('p', get_string('noaccounts', 'qtype_buchungssatz'), ['class' => 'alert alert-info']);
}

// CSV import section — full-width Moodle filepicker form.
echo html_writer::start_div('buchungssatz-csv-import mt-4');
echo $OUTPUT->heading(get_string('importchart', 'qtype_buchungssatz'), 3);
$importform->display();
echo html_writer::end_div();

// Bottom action row: Back button on the left, Export button next to it.
$exporturl = new moodle_url($baseurl, ['action' => 'export', 'sesskey' => sesskey()]);
echo html_writer::start_div('buchungssatz-chart-actions d-flex flex-wrap justify-content-center align-items-center mt-4 mb-3',
    ['style' => 'gap: 0.5rem;']);
echo html_writer::link($manageurl, '&laquo; ' . get_string('managecharts', 'qtype_buchungssatz'), [
    'class' => 'btn btn-primary',
]);
echo html_writer::link($exporturl, get_string('exportaccounts', 'qtype_buchungssatz'), [
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_div();

echo $OUTPUT->footer();
