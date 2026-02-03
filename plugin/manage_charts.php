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

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/question/type/buchungssatz/manage_charts.php'));
$PAGE->set_title(get_string('managecharts', 'qtype_buchungssatz'));
$PAGE->set_heading(get_string('managecharts', 'qtype_buchungssatz'));

// Handle actions.
switch ($action) {
    case 'create':
        if (data_submitted() && confirm_sesskey()) {
            // CSV file is required.
            if (empty($_FILES['csvfile']['tmp_name']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
                redirect($PAGE->url, get_string('csvfilerequired', 'qtype_buchungssatz'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }

            $csvdata = file_get_contents($_FILES['csvfile']['tmp_name']);
            if (empty($csvdata)) {
                redirect($PAGE->url, get_string('csvempty', 'qtype_buchungssatz'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }

            // Parse CSV to get chart name first.
            try {
                $parsed = \qtype_buchungssatz\import_helper::parse_csv($csvdata);
                $chartname = $parsed['chartname'];
            } catch (Exception $e) {
                redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
            }

            // Check if chart with this name already exists.
            $existingchart = chart_manager::get_chart_by_name($chartname, $context->id);
            $override = optional_param('override', 0, PARAM_INT);

            if ($existingchart && !$override) {
                // Store CSV data in session for the confirmation step.
                $_SESSION['qtype_buchungssatz_csv'] = $csvdata;
                $_SESSION['qtype_buchungssatz_chartname'] = $chartname;
                redirect(new moodle_url($PAGE->url, ['action' => 'confirmoverride', 'sesskey' => sesskey()]));
            }

            // If override requested, delete the existing chart first.
            if ($existingchart && $override) {
                chart_manager::delete_chart($existingchart->id);
            }

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

    case 'confirmoverride':
        // Show confirmation page for overriding existing chart.
        if (!isset($_SESSION['qtype_buchungssatz_csv']) || !isset($_SESSION['qtype_buchungssatz_chartname'])) {
            redirect($PAGE->url);
        }
        // Will be handled after the header output.
        break;

    case 'dooverride':
        if (data_submitted() && confirm_sesskey()) {
            if (!isset($_SESSION['qtype_buchungssatz_csv']) || !isset($_SESSION['qtype_buchungssatz_chartname'])) {
                redirect($PAGE->url, get_string('chartimportfailed', 'qtype_buchungssatz'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }

            $csvdata = $_SESSION['qtype_buchungssatz_csv'];
            $chartname = $_SESSION['qtype_buchungssatz_chartname'];

            // Clear session data.
            unset($_SESSION['qtype_buchungssatz_csv']);
            unset($_SESSION['qtype_buchungssatz_chartname']);

            // Delete existing chart.
            $existingchart = chart_manager::get_chart_by_name($chartname, $context->id);
            if ($existingchart) {
                chart_manager::delete_chart($existingchart->id);
            }

            // Import new chart.
            $result = chart_manager::import_chart_from_csv($csvdata, $context->id);
            if ($result['chartid'] > 0) {
                $message = get_string('chartimportsuccess', 'qtype_buchungssatz', $result['imported']);
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

// Handle confirmation page for overriding existing chart.
if ($action === 'confirmoverride' && isset($_SESSION['qtype_buchungssatz_chartname'])) {
    $chartname = $_SESSION['qtype_buchungssatz_chartname'];

    echo $OUTPUT->heading(get_string('confirmoverride', 'qtype_buchungssatz'), 3);
    echo '<div class="alert alert-warning">';
    echo get_string('chartexistswarning', 'qtype_buchungssatz', s($chartname));
    echo '</div>';

    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="dooverride">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<button type="submit" class="btn btn-danger mr-2">' . get_string('overridechart', 'qtype_buchungssatz') . '</button>';
    echo '<a href="' . $PAGE->url->out() . '" class="btn btn-secondary">' . get_string('cancel') . '</a>';
    echo '</form>';

    echo $OUTPUT->footer();
    exit;
}

// Get all charts.
$charts = chart_manager::get_charts_for_context($context->id);

// Display import form.
echo $OUTPUT->heading(get_string('importchartfromcsv', 'qtype_buchungssatz'), 3);
echo '<form method="post" class="mb-4" enctype="multipart/form-data">';
echo '<input type="hidden" name="action" value="create">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<div class="form-group row fitem">';
echo '<div class="col-md-3 col-form-label d-flex pb-0 pr-md-0">';
echo '<label>' . get_string('csvfile', 'qtype_buchungssatz') . ' *</label>';
echo '</div>';
echo '<div class="col-md-9 form-inline align-items-start felement">';
echo '<div class="custom-file-input-wrapper" style="display: flex; align-items: center; gap: 10px;">';
echo '<label for="csvfile" class="btn btn-outline-secondary mb-0" style="cursor: pointer;">';
echo get_string('choosefile', 'qtype_buchungssatz');
echo '</label>';
echo '<span id="csv-filename" style="color: #6c757d; font-size: 0.9em;">' . get_string('nofileselected', 'qtype_buchungssatz') . '</span>';
echo '<input type="file" name="csvfile" id="csvfile" accept=".csv,.txt" style="display: none;" required>';
echo '</div>';
echo '<small class="form-text text-muted d-block mt-2">' . get_string('csvfilehelp', 'qtype_buchungssatz') . '</small>';
echo '</div>';
echo '</div>';
echo '<div class="form-group row fitem">';
echo '<div class="col-md-3"></div>';
echo '<div class="col-md-9">';
echo '<button type="submit" class="btn btn-primary">' . get_string('importchart', 'qtype_buchungssatz') . '</button>';
echo '</div>';
echo '</div>';
echo '</form>';

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
    $table = new html_table();
    $table->head = [
        get_string('chartname', 'qtype_buchungssatz'),
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
            $accountcount,
            implode(' ', $actions),
        ];
    }

    echo html_writer::table($table);
}

// JavaScript to update filename display when file is selected.
$jscode = 'document.getElementById("csvfile").addEventListener("change", function() {
    var filename = this.files.length > 0 ? this.files[0].name : "' .
    addslashes_js(get_string('nofileselected', 'qtype_buchungssatz')) . '";
    document.getElementById("csv-filename").textContent = filename;
});';
$PAGE->requires->js_init_code($jscode, true);

echo $OUTPUT->footer();
