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

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use qtype_buchungssatz\chart_manager;

require_login();

$context = context_system::instance();
require_capability('qtype/buchungssatz:managecharts', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$chartid = optional_param('chartid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/question/type/buchungssatz/manage_charts.php'));
$PAGE->set_title(get_string('managecharts', 'qtype_buchungssatz'));
$PAGE->set_heading(get_string('managecharts', 'qtype_buchungssatz'));

// Handle actions.
switch ($action) {
    case 'create':
        if (data_submitted() && confirm_sesskey()) {
            $name = required_param('name', PARAM_TEXT);
            $description = optional_param('description', '', PARAM_TEXT);
            chart_manager::create_chart($name, $description, $context->id);
            redirect($PAGE->url, get_string('chartcreated', 'qtype_buchungssatz'), null,
                \core\output\notification::NOTIFY_SUCCESS);
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

    case 'import':
        if ($chartid && data_submitted() && confirm_sesskey()) {
            $csvdata = optional_param('csvdata', '', PARAM_RAW);
            if (!empty($csvdata)) {
                $result = chart_manager::import_from_csv($chartid, $csvdata);
                $message = get_string('imported', 'qtype_buchungssatz', $result['imported']);
                if (!empty($result['errors'])) {
                    $message .= ' ' . implode(', ', $result['errors']);
                }
                redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_INFO);
            }
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
$charts = chart_manager::get_charts_for_context($context->id);

// Display create form.
echo $OUTPUT->heading(get_string('addchart', 'qtype_buchungssatz'), 3);
echo '<form method="post" class="mb-4">';
echo '<input type="hidden" name="action" value="create">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<div class="form-group">';
echo '<label for="name">' . get_string('chartname', 'qtype_buchungssatz') . '</label>';
echo '<input type="text" class="form-control" name="name" id="name" required>';
echo '</div>';
echo '<div class="form-group">';
echo '<label for="description">' . get_string('chartdescription', 'qtype_buchungssatz') . '</label>';
echo '<textarea class="form-control" name="description" id="description" rows="2"></textarea>';
echo '</div>';
echo '<button type="submit" class="btn btn-primary">' . get_string('addchart', 'qtype_buchungssatz') . '</button>';
echo '</form>';

// Create default SKR03 button.
echo '<div class="mb-4">';
$defaulturl = new moodle_url($PAGE->url, ['action' => 'createdefault', 'sesskey' => sesskey()]);
echo $OUTPUT->single_button($defaulturl, 'SKR03 Standardkontenplan erstellen', 'get');
echo '</div>';

// Display existing charts.
echo $OUTPUT->heading(get_string('managecharts', 'qtype_buchungssatz'), 3);

if (empty($charts)) {
    echo $OUTPUT->notification(get_string('nocharts', 'qtype_buchungssatz'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('chartname', 'qtype_buchungssatz'),
        get_string('chartdescription', 'qtype_buchungssatz'),
        get_string('accounts', 'qtype_buchungssatz'),
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
            s($chart->description),
            $accountcount,
            implode(' ', $actions),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
