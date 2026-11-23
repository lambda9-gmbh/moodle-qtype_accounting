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
 * Shared page setup of the pages that manage the site-wide chart of accounts templates.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_page {
    /**
     * Check access and set up an admin page for the templates.
     *
     * @param \moodle_url $url The page URL.
     * @param string $title The page title.
     * @return \context The system context, which the templates belong to.
     */
    public static function setup(\moodle_url $url, string $title): \context {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/adminlib.php');
        admin_externalpage_setup('qtype_accounting_sitecharts', '', $url->params(), $url);
        $PAGE->set_title($title);
        return \context_system::instance();
    }

    /**
     * Get the URL of the plugin settings page, which lists the templates.
     *
     * @return \moodle_url The URL.
     */
    public static function settings_url(): \moodle_url {
        return new \moodle_url('/admin/settings.php', ['section' => 'qtypesettingaccounting']);
    }

    /**
     * Get a template and make sure it is a site-wide template.
     *
     * @param int $chartid The chart ID.
     * @return \stdClass The chart record.
     */
    public static function get_template(int $chartid): \stdClass {
        $chart = chart_manager::get_chart($chartid);
        if (!$chart || !chart_manager::is_site_chart($chart)) {
            throw new \moodle_exception('chartnotfound', 'qtype_accounting');
        }
        return $chart;
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
