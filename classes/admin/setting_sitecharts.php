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

namespace qtype_accounting\admin;

use moodle_url;
use qtype_accounting\chart_manager;

/**
 * Admin setting that lists the site charts of accounts on the plugin's settings page.
 *
 * It stores nothing; it renders the charts with their actions and links to the pages
 * for creating and uploading charts, which cannot be nested in the settings form.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_sitecharts extends \admin_setting {
    /**
     * Create the setting.
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('qtype_accounting/sitecharts', get_string('sitecharts', 'qtype_accounting'), '', '');
    }

    /**
     * Nothing is stored, but a non-null value keeps the setting out of the "new settings" list.
     *
     * @return bool Always true.
     */
    public function get_setting() {
        return true;
    }

    /**
     * Nothing to store.
     *
     * @param mixed $data Submitted data (ignored).
     * @return string Always an empty string (no error).
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Render the site charts table and the links to create or upload a chart.
     *
     * @param mixed $data Unused.
     * @param string $query Admin search query.
     * @return string HTML.
     */
    public function output_html($data, $query = '') {
        global $DB, $OUTPUT, $PAGE;

        $manageurl = new moodle_url('/question/type/accounting/manage_charts.php');
        $charts = chart_manager::get_charts_for_context(\context_system::instance()->id);
        $counts = [];
        if ($charts) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($charts));
            $counts = $DB->get_records_sql_menu(
                "SELECT chartid, COUNT(1) FROM {qtype_accounting_accounts} WHERE chartid $insql GROUP BY chartid",
                $params
            );
        }
        $rows = [];
        foreach ($charts as $chart) {
            $name = format_string($chart->name, true, ['context' => \context_system::instance()]);
            $rows[] = [
                'name' => $name,
                'editurl' => (new moodle_url('/question/type/accounting/edit_chart.php', ['chartid' => $chart->id]))->out(false),
                'exporturl' => (new moodle_url($manageurl, ['action' => 'export', 'chartid' => $chart->id]))->out(false),
                'deleteurl' => (new moodle_url($manageurl, ['action' => 'delete', 'chartid' => $chart->id]))->out(false),
                'confirmmessage' => get_string('confirmdelete', 'qtype_accounting', $name),
                'accountcount' => (int)($counts[$chart->id] ?? 0),
                'created' => userdate($chart->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            ];
        }
        if ($rows) {
            $PAGE->requires->js_call_amd('qtype_accounting/manage_charts', 'init');
        }
        return $OUTPUT->render_from_template('qtype_accounting/site_charts', [
            'manageurl' => $manageurl->out(false),
            'hascharts' => !empty($rows),
            'charts' => $rows,
        ]);
    }
}
