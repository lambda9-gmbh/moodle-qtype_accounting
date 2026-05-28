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
 * AJAX endpoint to get accounts for charts in a course context.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use qtype_accounting\chart_manager;
use qtype_accounting\account_manager;

require_login();
require_sesskey();

$contextid = required_param('courseid', PARAM_INT);

// Verify the user has access to this context.
$context = \context::instance_by_id($contextid, MUST_EXIST);
require_capability('moodle/question:add', $context);

// Get charts scoped to the course context.
global $DB;
$charts = $DB->get_records(
    'qtype_accounting_charts',
    ['contextid' => $contextid],
    'name ASC'
);

$result = [];
foreach ($charts as $chart) {
    $accounts = account_manager::get_for_chart($chart->id);
    $result[$chart->id] = [];
    foreach ($accounts as $account) {
        $result[$chart->id][$account->id] = $account->accountname;
    }
}

header('Content-Type: application/json');
echo json_encode($result);
