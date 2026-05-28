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
 * AJAX endpoint to import chart of accounts from text data.
 *
 * Each line = one account name.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use qtype_buchungssatz\chart_manager;
use qtype_buchungssatz\account_manager;
use qtype_buchungssatz\import_helper;

require_login();
require_sesskey();

$csvdata = required_param('csvdata', PARAM_RAW);
$contextid = required_param('courseid', PARAM_INT);

// Verify the user has access to this context.
$context = \context::instance_by_id($contextid, MUST_EXIST);
require_capability('moodle/question:add', $context);

header('Content-Type: application/json');

try {
    $result = import_chart_from_csv($csvdata, $contextid);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Import chart of accounts from CSV data.
 *
 * @param string $csvdata The raw CSV data.
 * @param int $contextid The course context ID to scope the chart to.
 * @return array Result with chart info and accounts.
 */
function import_chart_from_csv(string $csvdata, int $contextid): array {
    global $DB;

    // Parse CSV to extract chart name and accounts.
    $parsed = import_helper::parse_csv($csvdata);
    $chartname = $parsed['chartname'];
    $accounts = $parsed['accounts'];

    // Check if a chart with matching accounts already exists in this context.
    $chartid = import_helper::find_matching_chart($accounts, $contextid);

    if ($chartid) {
        // Use existing chart.
        $chart = $DB->get_record('qtype_buchungssatz_charts', ['id' => $chartid]);
        $chartname = $chart->name;
    } else {
        // Create a new chart of accounts in the course context.
        $importresult = chart_manager::import_chart_from_csv($csvdata, $contextid);

        if ($importresult['chartid'] === 0) {
            throw new Exception(implode(', ', $importresult['errors']));
        }

        $chartid = $importresult['chartid'];
        $chartname = $importresult['chartname'];
    }

    // Get all charts and accounts for this context for dropdown refresh.
    $allaccounts = [];
    $charts = $DB->get_records(
        'qtype_buchungssatz_charts',
        ['contextid' => $contextid],
        'name ASC'
    );
    foreach ($charts as $chart) {
        $chartaccounts = account_manager::get_for_chart($chart->id);
        $allaccounts[$chart->id] = [];
        foreach ($chartaccounts as $acc) {
            $allaccounts[$chart->id][$acc->id] = $acc->accountname;
        }
    }

    return [
        'success' => true,
        'chartid' => $chartid,
        'chartname' => $chartname,
        'accounts' => $allaccounts,
    ];
}
