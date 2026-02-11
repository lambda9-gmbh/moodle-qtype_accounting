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
 * AJAX endpoint to upload a chart of accounts from a Moodle draft file.
 *
 * Reads a CSV file from the Moodle draft area, imports it as a chart
 * of accounts scoped to the given course context, and returns the
 * chart data plus refreshed account lists.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use qtype_buchungssatz\chart_manager;
use qtype_buchungssatz\import_helper;

require_login();
require_sesskey();

$draftitemid = required_param('draftitemid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

header('Content-Type: application/json');

try {
    global $USER, $DB;

    // Read the file from the draft area.
    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);

    if (empty($files)) {
        throw new Exception(get_string('csvempty', 'qtype_buchungssatz'));
    }

    $file = reset($files);
    $csvcontent = $file->get_content();
    $filename = $file->get_filename();

    if (empty(trim($csvcontent))) {
        throw new Exception(get_string('csvempty', 'qtype_buchungssatz'));
    }

    // Parse to check for duplicates first.
    $parsed = import_helper::parse_csv($csvcontent, $filename);
    $chartid = import_helper::find_matching_chart($parsed['accounts'], $courseid);

    if ($chartid) {
        // Reuse existing chart.
        $chart = $DB->get_record('qtype_buchungssatz_charts', ['id' => $chartid]);
        $chartname = $chart->name;
    } else {
        // Import new chart into the course context.
        $importresult = chart_manager::import_chart_from_csv($csvcontent, $courseid, $filename);

        if ($importresult['chartid'] === 0) {
            throw new Exception(implode(', ', $importresult['errors']));
        }

        $chartid = $importresult['chartid'];
        $chartname = $importresult['chartname'];
    }

    // Return all charts and accounts for this context so the UI can refresh.
    $allaccounts = [];
    $allcharts = [];
    $charts = $DB->get_records('qtype_buchungssatz_charts',
        ['contextid' => $courseid], 'name ASC');
    foreach ($charts as $c) {
        $allcharts[$c->id] = $c->name;
        $chartaccounts = chart_manager::get_accounts($c->id);
        $allaccounts[$c->id] = [];
        foreach ($chartaccounts as $acc) {
            $allaccounts[$c->id][$acc->accountnumber] = $acc->accountnumber . ' - ' . $acc->accountname;
        }
    }

    echo json_encode([
        'success' => true,
        'chartid' => $chartid,
        'chartname' => $chartname,
        'charts' => $allcharts,
        'accounts' => $allaccounts,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
