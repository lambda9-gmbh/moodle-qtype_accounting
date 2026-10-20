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

namespace qtype_accounting\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use qtype_accounting\chart_manager;

/**
 * Read fresh chart options without submitting the question form.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_charts extends external_api {
    /**
     * Describe the request parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Get charts and accounts available in the requested course, including the site charts.
     *
     * @param int $courseid Course ID.
     * @return array Chart records with their accounts.
     */
    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $course = get_course($params['courseid']);
        require_login($course);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        require_capability('qtype/accounting:managecharts', $context);

        $charts = chart_manager::get_available_charts((int)$context->id);
        if (!$charts) {
            return [];
        }
        $accounts = $DB->get_records_list(
            'qtype_accounting_accounts',
            'chartid',
            array_keys($charts),
            'accountname',
            'id, chartid, accountname'
        );
        $accountsbychart = [];
        foreach ($accounts as $account) {
            $accountsbychart[$account->chartid][] = ['id' => $account->id, 'name' => $account->accountname];
        }
        $result = [];
        foreach ($charts as $chart) {
            $result[] = [
                'id' => $chart->id,
                'name' => chart_manager::get_display_name($chart, $context),
                'accounts' => $accountsbychart[$chart->id] ?? [],
            ];
        }
        return $result;
    }

    /**
     * Describe the returned chart and account options.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Chart ID'),
            'name' => new external_value(PARAM_RAW, 'Chart name'),
            'accounts' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Account ID'),
                'name' => new external_value(PARAM_RAW, 'Account name'),
            ])),
        ]));
    }
}
