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
 * AJAX endpoint to get accounts for charts in a course context.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use qtype_buchungssatz\chart_manager;

require_login();
require_sesskey();

$contextid = required_param('courseid', PARAM_INT);

// Verify the user has access to this context.
$context = \context::instance_by_id($contextid, MUST_EXIST);
require_capability('moodle/question:add', $context);

// Get charts scoped to the course context.
global $DB;
$charts = $DB->get_records('qtype_buchungssatz_charts',
    ['contextid' => $contextid], 'name ASC');

$result = [];
foreach ($charts as $chart) {
    $accounts = chart_manager::get_accounts($chart->id);
    $result[$chart->id] = [];
    foreach ($accounts as $account) {
        $result[$chart->id][$account->id] = $account->accountname;
    }
}

header('Content-Type: application/json');
echo json_encode($result);
