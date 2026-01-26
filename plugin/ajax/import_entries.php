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
 * AJAX endpoint to import chart of accounts from CSV.
 *
 * Expected CSV format: Liste;Kontokl;Kontonr;Name
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

$csvdata = required_param('csvdata', PARAM_RAW);

header('Content-Type: application/json');

try {
    $result = import_chart_from_csv($csvdata);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Import chart of accounts from CSV data.
 *
 * @param string $csvdata The raw CSV data.
 * @return array Result with chart info and accounts.
 */
function import_chart_from_csv(string $csvdata): array {
    global $DB;

    // Parse CSV to extract chart name and accounts.
    $parsed = import_helper::parse_csv($csvdata);
    $chartname = $parsed['chartname'];
    $accounts = $parsed['accounts'];

    // Check if a chart with the same accounts already exists.
    $chartid = import_helper::find_matching_chart($accounts);

    if ($chartid) {
        // Use existing chart.
        $chart = $DB->get_record('qtype_buchungssatz_charts', ['id' => $chartid]);
        $chartname = $chart->name;
    } else {
        // Create a new chart of accounts.
        $contextid = context_system::instance()->id;
        $importresult = chart_manager::import_chart_from_csv($csvdata, $contextid);

        if ($importresult['chartid'] === 0) {
            throw new Exception(implode(', ', $importresult['errors']));
        }

        $chartid = $importresult['chartid'];
        $chartname = $importresult['chartname'];
    }

    // Get all charts and accounts for dropdown refresh.
    $allaccounts = [];
    $charts = $DB->get_records('qtype_buchungssatz_charts', null, 'name ASC');
    foreach ($charts as $chart) {
        $chartaccounts = chart_manager::get_accounts($chart->id);
        $allaccounts[$chart->id] = [];
        foreach ($chartaccounts as $acc) {
            $allaccounts[$chart->id][$acc->accountnumber] = $acc->accountnumber . ' - ' . $acc->accountname;
        }
    }

    return [
        'success' => true,
        'chartid' => $chartid,
        'chartname' => $chartname,
        'accounts' => $allaccounts,
    ];
}
