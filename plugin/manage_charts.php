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
 * Chart of accounts management page.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG, $PAGE, $OUTPUT;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use qtype_buchungssatz\chart_manager;

require_login();

$context = context_system::instance();
require_capability('qtype/buchungssatz:managecharts', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$chartid = optional_param('chartid', 0, PARAM_INT);
$tsort = optional_param('tsort', 'name', PARAM_ALPHA);
$tdir = optional_param('tdir', 'asc', PARAM_ALPHA);

// Instantiate the import form.
require_once($CFG->dirroot . '/question/type/buchungssatz/chart_import_form.php');
$importurl = new moodle_url($PAGE->url, ['action' => 'import']);
$importform = new chart_import_form($importurl, ['contextid' => $context->id]);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/question/type/buchungssatz/manage_charts.php', ['tsort' => $tsort, 'tdir' => $tdir]));
$PAGE->set_title(get_string('managecharts', 'qtype_buchungssatz'));
$PAGE->set_heading(get_string('managecharts', 'qtype_buchungssatz'));

// Handle actions.
switch ($action) {
    case 'import':
        if ($importform->is_cancelled()) {
            redirect($PAGE->url);
        } else if ($data = $importform->get_data()) {
            // Get parsed data from validation.
            $parsed = $importform->get_parsed_data();

            if (empty($parsed)) {
                redirect($PAGE->url, get_string('chartimportfailed', 'qtype_buchungssatz'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }

            $chartname = $parsed['chartname'];

            // If override enabled, delete existing chart.
            if (!empty($data->overrideexisting)) {
                $existingchart = chart_manager::get_chart_by_name($chartname, $context->id);
                if ($existingchart) {
                    chart_manager::delete_chart($existingchart->id);
                }
            }

            // Import chart.
            $csvdata = $importform->get_csv_content();
            $result = chart_manager::import_chart_from_csv($csvdata, $context->id);

            if ($result['chartid'] > 0) {
                $message = get_string('chartimportsuccess', 'qtype_buchungssatz', $result['imported']);
                if (!empty($result['errors'])) {
                    $message .= ' ' . get_string('witherrors', 'qtype_buchungssatz') . ': ' .
                        implode(', ', $result['errors']);
                }
                redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
            } else {
                $errormsg = get_string('chartimportfailed', 'qtype_buchungssatz');
                if (!empty($result['errors'])) {
                    $errormsg .= ': ' . implode(', ', $result['errors']);
                }
                redirect($PAGE->url, $errormsg, null, \core\output\notification::NOTIFY_ERROR);
            }
        }
        break;

    case 'delete':
        if ($chartid && confirm_sesskey()) {
            chart_manager::delete_chart($chartid);
            redirect($PAGE->url, get_string('chartdeleted', 'qtype_buchungssatz'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        break;

    case 'createdefault':
        if (confirm_sesskey()) {
            chart_manager::create_default_skr03($context->id);
            redirect($PAGE->url, get_string('defaultchartcreated', 'qtype_buchungssatz'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        break;


    case 'export':
        if ($chartid) {
            $chart = chart_manager::get_chart($chartid);
            $csv = chart_manager::export_to_csv($chartid);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . clean_filename($chart->name) . '.csv"');
            echo $csv;
            exit;
        }
        break;
}

echo $OUTPUT->header();

// Get all charts.
$charts = chart_manager::get_charts_for_context($context->id, $tsort, $tdir);

// Display import form.
echo $OUTPUT->heading(get_string('importchartfromcsv', 'qtype_buchungssatz'), 3);
$importform->display();

// Create default SKR03 button.
echo '<div class="mb-4">';
$defaulturl = new moodle_url($PAGE->url, ['action' => 'createdefault', 'sesskey' => sesskey()]);
echo $OUTPUT->single_button($defaulturl, get_string('createdefaultskr03', 'qtype_buchungssatz'), 'get');
echo '</div>';

echo '<hr class="my-4">';

// Display existing charts.
echo $OUTPUT->heading(get_string('managecharts', 'qtype_buchungssatz'), 3);

if (empty($charts)) {
    echo $OUTPUT->notification(get_string('nocharts', 'qtype_buchungssatz'), 'info');
} else {
    // Build sortable column header links.
    $sortablecolumns = ['name', 'timecreated'];
    $headers = [];
    foreach (['name' => 'chartname', 'timecreated' => 'importdate'] as $col => $stringkey) {
        $label = get_string($stringkey, 'qtype_buchungssatz');
        if ($tsort === $col) {
            $newdir = ($tdir === 'asc') ? 'desc' : 'asc';
            $arrow = ($tdir === 'asc') ? ' ▲' : ' ▼';
        } else {
            $newdir = 'asc';
            $arrow = '';
        }
        $sorturl = new moodle_url('/question/type/buchungssatz/manage_charts.php', ['tsort' => $col, 'tdir' => $newdir]);
        $headers[$col] = html_writer::link($sorturl, $label . $arrow, ['class' => 'buchungssatz-sort-link']);
    }

    $table = new html_table();
    $table->head = [
        $headers['name'],
        get_string('accounts', 'qtype_buchungssatz'),
        $headers['timecreated'],
        get_string('actions'),
    ];

    foreach ($charts as $chart) {
        $accounts = chart_manager::get_accounts($chart->id);
        $accountcount = count($accounts);

        $actions = [];

        // Edit link.
        $editurl = new moodle_url('/question/type/buchungssatz/edit_chart.php', ['id' => $chart->id]);
        $actions[] = html_writer::link($editurl, get_string('editaccounts', 'qtype_buchungssatz'),
            ['class' => 'btn btn-sm btn-primary']);

        // Export link.
        $exporturl = new moodle_url($PAGE->url, ['action' => 'export', 'chartid' => $chart->id]);
        $actions[] = html_writer::link($exporturl, get_string('exportaccounts', 'qtype_buchungssatz'),
            ['class' => 'btn btn-sm btn-secondary']);

        // Delete link.
        $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'chartid' => $chart->id, 'sesskey' => sesskey()]);
        $actions[] = html_writer::link($deleteurl, get_string('deletechart', 'qtype_buchungssatz'),
            ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm("' . get_string('confirmdelete', 'qtype_buchungssatz') . '")']);

        $table->data[] = [
            s($chart->name),
            $accountcount,
            userdate($chart->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            implode(' ', $actions),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
