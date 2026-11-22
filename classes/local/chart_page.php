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

namespace qtype_accounting\local;

use qtype_accounting\chart_manager;

/**
 * Shared page setup of the chart management pages.
 *
 * Charts are managed in a question bank context or, as site charts, in the system context.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_page {
    /**
     * Get the chart context requested by the optional contextid parameter.
     *
     * @return \context|null The question bank context, or null for the site charts.
     */
    public static function get_requested_context(): ?\context {
        $contextid = optional_param('contextid', 0, PARAM_INT);
        if (!$contextid) {
            return null;
        }
        $context = chart_manager::get_chart_context(\context::instance_by_id($contextid));
        return $context->contextlevel == CONTEXT_SYSTEM ? null : $context;
    }

    /**
     * Get the URL parameters that identify the chart context.
     *
     * @param \context|null $context The question bank context, or null for the site charts.
     * @return array The URL parameters.
     */
    public static function url_params(?\context $context): array {
        return $context ? ['contextid' => $context->id] : [];
    }

    /**
     * Check access and set up the page.
     *
     * @param \context|null $context The question bank context, or null for the site charts.
     * @param \moodle_url $url The page URL.
     * @param string $title The page title.
     * @param bool $embedded Whether the page is shown inside the question editor's overlay.
     * @return \context The context the charts belong to.
     */
    public static function setup(?\context $context, \moodle_url $url, string $title, bool $embedded): \context {
        global $CFG, $PAGE;

        if (!$context) {
            require_once($CFG->libdir . '/adminlib.php');
            admin_externalpage_setup('qtype_accounting_sitecharts', '', $url->params(), $url);
            $PAGE->set_title($title);
            return \context_system::instance();
        }

        [, $course, $cm] = get_context_info_array($context->id);
        require_login($course, false, $cm);
        require_capability('qtype/accounting:managecharts', $context);
        $PAGE->set_url($url);
        $PAGE->set_context($context);
        $PAGE->set_pagelayout($embedded ? 'embedded' : 'incourse');
        if ($embedded) {
            $PAGE->add_body_class('qtype_accounting-chart-embedded');
        }
        $PAGE->set_title($title);
        $heading = $course ? format_string($course->fullname, true, ['context' => $context]) : $context->get_context_name();
        $PAGE->set_heading($heading);
        return $context;
    }

    /**
     * Redirect after an import and report the number of imported accounts and any errors.
     *
     * @param \moodle_url $url The URL to return to.
     * @param array $result The import result with the keys 'imported' and 'errors'.
     */
    public static function redirect_import_result(\moodle_url $url, array $result): void {
        if ($result['imported'] > 0) {
            $msg = get_string('imported', 'qtype_accounting', $result['imported']);
            if (!empty($result['errors'])) {
                $msg .= ' ' . get_string('witherrors', 'qtype_accounting') . ': ' . implode(', ', $result['errors']);
            }
            redirect($url, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
        }
        $msg = get_string('chartimportfailed', 'qtype_accounting');
        if (!empty($result['errors'])) {
            $msg .= ': ' . implode(', ', $result['errors']);
        }
        redirect($url, $msg, null, \core\output\notification::NOTIFY_ERROR);
    }

    /**
     * Send a chart as a download with one account name per line, the format the imports read.
     *
     * @param \stdClass $chart The chart record.
     */
    public static function send_chart_file(\stdClass $chart): void {
        send_file(
            chart_manager::export_to_csv((int)$chart->id),
            clean_filename($chart->name) . '.csv',
            0,
            0,
            true,
            true,
            'text/csv'
        );
    }
}
