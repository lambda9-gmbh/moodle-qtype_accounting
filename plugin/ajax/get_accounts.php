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
 * AJAX endpoint to get accounts for all charts.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $DB;

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use qtype_buchungssatz\chart_manager;

require_login();
require_sesskey();

$context = context_system::instance();

// Get all charts.
$charts = $DB->get_records('qtype_buchungssatz_charts', null, 'name ASC');

$result = [];
foreach ($charts as $chart) {
    $accounts = chart_manager::get_accounts($chart->id);
    $result[$chart->id] = [];
    foreach ($accounts as $account) {
        $result[$chart->id][$account->accountnumber] = $account->accountnumber . ' - ' . $account->accountname;
    }
}

header('Content-Type: application/json');
echo json_encode($result);
