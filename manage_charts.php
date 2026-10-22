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
 * Manage charts of accounts for a course, or the site charts when no course is given.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use qtype_accounting\chart_manager;
use qtype_accounting\account_manager;

/**
 * Form for uploading a chart of accounts CSV file.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_accounting_chart_upload_form extends moodleform {
    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'filepicker',
            'chartcsvfile',
            get_string('csvfile', 'qtype_accounting'),
            null,
            ['maxbytes' => 2097152, 'accepted_types' => ['.csv', '.txt']]
        );
        $mform->addHelpButton('chartcsvfile', 'csvfile', 'qtype_accounting');

        $this->add_action_buttons(false, get_string('uploadchartcsv_btn', 'qtype_accounting'));
    }
}

$courseid = optional_param('courseid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$chartid = optional_param('chartid', 0, PARAM_INT);
$sort = optional_param('sort', 'name', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Without a course, the site charts offered to every course are managed.
$urlparams = $courseid ? ['courseid' => $courseid] : [];
$baseurl = new moodle_url('/question/type/accounting/manage_charts.php', $urlparams);
if ($returnurl !== '') {
    $baseurl->param('returnurl', $returnurl);
}
if ($courseid) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($course->id);
    require_capability('qtype/accounting:managecharts', $context);
    $PAGE->set_url($baseurl);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('managecharts', 'qtype_accounting'));
    $PAGE->set_heading($course->fullname);
} else {
    require_once($CFG->libdir . '/adminlib.php');
    admin_externalpage_setup('qtype_accounting_sitecharts', '', $baseurl->params(), $baseurl);
    $context = context_system::instance();
}
// Site charts are listed on the plugin's settings page, so actions return there.
$doneurl = $courseid ? $baseurl : new moodle_url('/admin/settings.php', ['section' => 'qtypesettingaccounting']);

// Handle delete action.
if ($action === 'delete' && $chartid) {
    $chart = chart_manager::get_chart($chartid);
    if (!$chart || (int)$chart->contextid !== (int)$context->id) {
        throw new moodle_exception('chartnotfound', 'qtype_accounting');
    }

    if (data_submitted() && optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        chart_manager::delete_chart($chartid);
        redirect($doneurl, get_string('chartdeleted', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Show confirmation page.
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

// Handle export action. Site charts can be exported from every course.
if ($action === 'export' && $chartid) {
    $chart = chart_manager::get_chart($chartid);
    if (!$chart || ((int)$chart->contextid !== (int)$context->id && !chart_manager::is_site_chart($chart))) {
        throw new moodle_exception('chartnotfound', 'qtype_accounting');
    }

    $csv = chart_manager::export_to_csv($chartid);
    $filename = clean_filename($chart->name) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $csv;
    exit;
}

// Copy a site chart into the course so that teachers can adapt it.
if ($action === 'copysite' && $chartid && $courseid) {
    require_sesskey();
    $chart = chart_manager::get_chart($chartid);
    if (!$chart || !chart_manager::is_site_chart($chart)) {
        throw new moodle_exception('chartnotfound', 'qtype_accounting');
    }
    $newchartid = chart_manager::duplicate_chart($chartid, $context->id);
    $editurl = new moodle_url('/question/type/accounting/edit_chart.php', $urlparams + ['chartid' => $newchartid]);
    if ($returnurl !== '') {
        $editurl->param('returnurl', $returnurl);
    }
    redirect($editurl, get_string('chartcopied', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Create an empty chart, then open the existing editor to add accounts.
$createform = new \qtype_accounting\form\chart_create_form($baseurl);
if ($data = $createform->get_data()) {
    $newchartid = chart_manager::create_chart(trim($data->chartname), $context->id);
    $editurl = new moodle_url('/question/type/accounting/edit_chart.php', $urlparams + ['chartid' => $newchartid]);
    if ($returnurl !== '') {
        $editurl->param('returnurl', $returnurl);
    }
    redirect($editurl, get_string('chartcreated', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle CSV upload via Moodle filepicker form.
$uploadform = new qtype_accounting_chart_upload_form($baseurl);
if ($data = $uploadform->get_data()) {
    global $USER;

    $draftitemid = $data->chartcsvfile;
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);

    if (!empty($files)) {
        $file = reset($files);
        $csvcontent = $file->get_content();
        $filename = $file->get_filename();

        $result = chart_manager::import_chart_from_csv($csvcontent, $context->id, $filename);

        if ($result['imported'] > 0) {
            $msg = get_string('imported', 'qtype_accounting', $result['imported']);
            if (!empty($result['errors'])) {
                $msg .= ' ' . get_string('witherrors', 'qtype_accounting') . ': ' . implode(', ', $result['errors']);
            }
            redirect($doneurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            $msg = get_string('chartimportfailed', 'qtype_accounting');
            if (!empty($result['errors'])) {
                $msg .= ': ' . implode(', ', $result['errors']);
            }
            redirect($doneurl, $msg, null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        redirect($doneurl, get_string('csvfilerequired', 'qtype_accounting'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Display the page.
$PAGE->requires->js_call_amd('qtype_accounting/manage_charts', 'init');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($courseid ? 'managecharts' : 'sitecharts_add', 'qtype_accounting'));
if (!$courseid) {
    echo html_writer::link($doneurl, '&laquo; ' . get_string('sitecharts', 'qtype_accounting'));
}

// Create and upload sections.
echo $OUTPUT->heading(get_string('createchart', 'qtype_accounting'), 3);
$createform->display();

echo $OUTPUT->heading(get_string('uploadchartcsv', 'qtype_accounting'), 3);
$uploadform->display();

// Charts table. Site charts are listed on the settings page instead.
$charts = $courseid ? chart_manager::get_charts_for_context($context->id, $sort, $dir) : [];

if (empty($charts) && $courseid) {
    echo html_writer::tag('p', get_string('nocharts', 'qtype_accounting'), ['class' => 'alert alert-info']);
} else if ($charts) {
    // Build sortable table headers.
    $oppositedir = ($dir === 'ASC') ? 'DESC' : 'ASC';

    $table = new html_table();
    $table->head = [];
    $table->attributes['class'] = 'generaltable';

    // Chart name header (sortable).
    $namesorturl = new moodle_url($baseurl, ['sort' => 'name', 'dir' => ($sort === 'name') ? $oppositedir : 'ASC']);
    $namearrow = ($sort === 'name') ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    $table->head[] = html_writer::link($namesorturl, get_string('chartname', 'qtype_accounting') . $namearrow);

    // Account count header.
    $table->head[] = get_string('accounts', 'qtype_accounting');

    // Created date header (sortable).
    $datesorturl = new moodle_url($baseurl, ['sort' => 'timecreated', 'dir' => ($sort === 'timecreated') ? $oppositedir : 'ASC']);
    $datearrow = ($sort === 'timecreated') ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    $table->head[] = html_writer::link($datesorturl, get_string('importdate', 'qtype_accounting') . $datearrow);

    // Actions header.
    $table->head[] = get_string('actions');

    foreach ($charts as $chart) {
        $accounts = account_manager::get_for_chart($chart->id);
        $accountcount = count($accounts);

        $editurl = new moodle_url('/question/type/accounting/edit_chart.php', $urlparams + ['chartid' => $chart->id]);
        if ($returnurl !== '') {
            $editurl->param('returnurl', $returnurl);
        }
        $exporturl = new moodle_url($baseurl, ['action' => 'export', 'chartid' => $chart->id]);
        $deleteurl = new moodle_url($baseurl, ['action' => 'delete', 'chartid' => $chart->id]);

        $actions = html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));
        $actions .= ' ';
        $actions .= html_writer::link(
            $exporturl,
            $OUTPUT->pix_icon('i/export', get_string('exportaccounts', 'qtype_accounting'))
        );
        $actions .= ' ';
        $actions .= html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')), [
            'data-action' => 'qtype_accounting-delete-chart',
            'data-confirmation-title' => get_string('deletechart', 'qtype_accounting'),
            'data-confirmation-message' => get_string('confirmdelete', 'qtype_accounting', format_string($chart->name)),
        ]);

        $table->data[] = [
            html_writer::link($editurl, format_string($chart->name)),
            $accountcount,
            userdate($chart->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            $actions,
        ];
    }

    echo html_writer::table($table);
}

// Site charts are read-only in a course, but can be exported or copied into the course.
$sitecharts = $courseid ? chart_manager::get_charts_for_context(context_system::instance()->id) : [];
if ($sitecharts) {
    echo $OUTPUT->heading(get_string('sitecharts', 'qtype_accounting'), 3);
    echo html_writer::tag('p', get_string('sitecharts_course_desc', 'qtype_accounting'));
    [$insql, $params] = $DB->get_in_or_equal(array_keys($sitecharts));
    $counts = $DB->get_records_sql_menu(
        "SELECT chartid, COUNT(1) FROM {qtype_accounting_accounts} WHERE chartid $insql GROUP BY chartid",
        $params
    );
    $sitetable = new html_table();
    $sitetable->attributes['class'] = 'generaltable';
    $sitetable->head = [get_string('chartname', 'qtype_accounting'), get_string('accounts', 'qtype_accounting'),
        get_string('actions')];
    foreach ($sitecharts as $chart) {
        $exporturl = new moodle_url($baseurl, ['action' => 'export', 'chartid' => $chart->id]);
        $copyurl = new moodle_url($baseurl, ['action' => 'copysite', 'chartid' => $chart->id, 'sesskey' => sesskey()]);
        $sitetable->data[] = [
            format_string($chart->name),
            (int)($counts[$chart->id] ?? 0),
            html_writer::link(
                $exporturl,
                $OUTPUT->pix_icon('i/export', get_string('exportaccounts', 'qtype_accounting'))
            ) . ' ' . html_writer::link(
                $copyurl,
                $OUTPUT->pix_icon('t/copy', get_string('copytocourse', 'qtype_accounting'))
            ),
        ];
    }
    echo html_writer::table($sitetable);
}

echo $OUTPUT->footer();
