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
 * Manage charts of accounts for a question bank, or the site charts when no question bank is given.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Login and capabilities are checked by chart_page::setup() for the requested context.
require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing

use qtype_accounting\chart_manager;

$bankcontext = \qtype_accounting\local\chart_page::get_requested_context();
$action = optional_param('action', '', PARAM_ALPHA);
$chartid = optional_param('chartid', 0, PARAM_INT);
$sort = optional_param('sort', 'name', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$embedded = optional_param('embedded', false, PARAM_BOOL);

// Without a question bank context, the site charts offered to every question bank are managed.
$urlparams = \qtype_accounting\local\chart_page::url_params($bankcontext);
$baseurl = new moodle_url('/question/type/accounting/manage_charts.php', $urlparams);
if ($embedded) {
    $baseurl->param('embedded', 1);
}
if ($returnurl !== '') {
    $baseurl->param('returnurl', $returnurl);
}
$context = \qtype_accounting\local\chart_page::setup(
    $bankcontext,
    $baseurl,
    get_string('managecharts', 'qtype_accounting'),
    $embedded
);
// Site charts are listed on the plugin's settings page, so actions return there.
$doneurl = $bankcontext ? $baseurl : new moodle_url('/admin/settings.php', ['section' => 'qtypesettingaccounting']);

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

// Handle export action. Site charts can be exported from every question bank.
if ($action === 'export' && $chartid) {
    $chart = chart_manager::get_chart($chartid);
    if (!$chart || ((int)$chart->contextid !== (int)$context->id && !chart_manager::is_site_chart($chart))) {
        throw new moodle_exception('chartnotfound', 'qtype_accounting');
    }

    \qtype_accounting\local\chart_page::send_chart_file($chart);
}

// Copy a site chart into the question bank so that teachers can adapt it.
if ($action === 'copysite' && $chartid && $bankcontext && data_submitted()) {
    require_sesskey();
    $chart = chart_manager::get_chart($chartid);
    if (!$chart || !chart_manager::is_site_chart($chart)) {
        throw new moodle_exception('chartnotfound', 'qtype_accounting');
    }
    $newchartid = chart_manager::duplicate_chart($chartid, $context->id);
    $editurl = new moodle_url('/question/type/accounting/edit_chart.php', $urlparams + ['chartid' => $newchartid]);
    if ($embedded) {
        $editurl->param('embedded', 1);
    }
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
    if ($embedded) {
        $editurl->param('embedded', 1);
    }
    if ($returnurl !== '') {
        $editurl->param('returnurl', $returnurl);
    }
    redirect($editurl, get_string('chartcreated', 'qtype_accounting'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle chart upload via Moodle filepicker form.
$uploadform = new \qtype_accounting\form\chart_file_form($baseurl, [
    'submitlabel' => get_string('uploadchartcsv_btn', 'qtype_accounting'),
]);
if ($uploadform->get_data()) {
    $file = $uploadform->get_uploaded_file();
    if (!$file) {
        redirect($doneurl, get_string('csvfilerequired', 'qtype_accounting'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $result = chart_manager::import_chart_from_csv($file->get_content(), $context->id, $file->get_filename());
    \qtype_accounting\local\chart_page::redirect_import_result($doneurl, $result);
}

// Display the page.
$PAGE->requires->js_call_amd('qtype_accounting/manage_charts', 'init');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($bankcontext ? 'managecharts' : 'sitecharts_add', 'qtype_accounting'));
if (!$bankcontext) {
    echo html_writer::link($doneurl, '&laquo; ' . get_string('sitecharts', 'qtype_accounting'));
}

// Create and upload sections.
echo $OUTPUT->heading(get_string('createchart', 'qtype_accounting'), 3);
$createform->display();

echo $OUTPUT->heading(get_string('uploadchartcsv', 'qtype_accounting'), 3);
$uploadform->display();

// Charts table. Site charts are listed on the settings page instead.
$charts = $bankcontext ? chart_manager::get_charts_for_context($context->id, $sort, $dir) : [];

if (empty($charts) && $bankcontext) {
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

    $accountcounts = $DB->get_records_sql_menu(
        "SELECT a.chartid, COUNT(a.id)
           FROM {qtype_accounting_accounts} a
           JOIN {qtype_accounting_charts} c ON c.id = a.chartid
          WHERE c.contextid = :contextid
       GROUP BY a.chartid",
        ['contextid' => $context->id]
    );
    foreach ($charts as $chart) {
        $accountcount = (int)($accountcounts[$chart->id] ?? 0);

        $editurl = new moodle_url('/question/type/accounting/edit_chart.php', $urlparams + ['chartid' => $chart->id]);
        if ($embedded) {
            $editurl->param('embedded', 1);
        }
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

// Site charts are read-only in a question bank, but can be exported or copied into it.
$sitecharts = $bankcontext ? chart_manager::get_charts_for_context(context_system::instance()->id) : [];
if ($sitecharts) {
    echo $OUTPUT->heading(get_string('sitecharts', 'qtype_accounting'), 3);
    echo html_writer::tag('p', get_string('sitecharts_bank_desc', 'qtype_accounting'));
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
        // Copying changes data, so it is a form submission rather than a link.
        $copyform = html_writer::tag(
            'form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . html_writer::tag('button', $OUTPUT->pix_icon('t/copy', ''), [
                    'type' => 'submit',
                    'class' => 'btn btn-link p-0 align-baseline',
                    'title' => get_string('copytobank', 'qtype_accounting'),
                    'aria-label' => get_string('copytobank', 'qtype_accounting'),
                ]),
            [
                'method' => 'post',
                'action' => (new moodle_url($baseurl, ['action' => 'copysite', 'chartid' => $chart->id]))->out(false),
                'class' => 'd-inline',
            ]
        );
        $sitetable->data[] = [
            format_string($chart->name),
            (int)($counts[$chart->id] ?? 0),
            html_writer::link(
                $exporturl,
                $OUTPUT->pix_icon('i/export', get_string('exportaccounts', 'qtype_accounting'))
            ) . ' ' . $copyform,
        ];
    }
    echo html_writer::table($sitetable);
}

echo $OUTPUT->footer();
