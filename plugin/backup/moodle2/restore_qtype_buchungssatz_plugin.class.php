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
 * Restore routines for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore plugin class that provides the necessary information to restore Buchungssatz questions.
 *
 * Collects chart data in instance variables, then creates everything in
 * process_buchungssatz_options() which runs after chart elements due to XML ordering.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_buchungssatz_plugin extends restore_qtype_plugin {

    /** @var string Chart name from the backup XML. */
    protected $chartname = '';

    /** @var array Account data collected from backup XML. */
    protected $chartaccounts = [];

    /** @var array Cache of chart name => chart ID to avoid duplicates across questions. */
    protected static $chartidcache = [];

    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return array
     */
    protected function define_question_plugin_structure() {
        $paths = [];

        // Chart data (processed first in XML order).
        $elepath = $this->get_pathfor('/buchungssatz_chart');
        $paths[] = new restore_path_element('buchungssatz_chart', $elepath);

        $elepath = $this->get_pathfor('/buchungssatz_chart/chart_accounts/chart_account');
        $paths[] = new restore_path_element('buchungssatz_chart_account', $elepath);

        // Options (processed after chart data).
        $elepath = $this->get_pathfor('/buchungssatz_options');
        $paths[] = new restore_path_element('buchungssatz_options', $elepath);

        // Entries.
        $elepath = $this->get_pathfor('/buchungssatz_entries/entry');
        $paths[] = new restore_path_element('buchungssatz_entry', $elepath);

        return $paths;
    }

    /**
     * Process the chart element - store chart name and reset accounts array.
     *
     * @param array $data The chart data from backup.
     */
    public function process_buchungssatz_chart($data) {
        $data = (object) $data;
        $this->chartname = $data->name ?? '';
        $this->chartaccounts = [];
    }

    /**
     * Process a chart account element - append to the accounts collection.
     *
     * @param array $data The account data from backup.
     */
    public function process_buchungssatz_chart_account($data) {
        $data = (object) $data;
        $this->chartaccounts[] = $data;
    }

    /**
     * Process the options element.
     *
     * Resolves the chart (find existing or create new in target course context),
     * then inserts the options record with the resolved chartofaccountsid.
     *
     * @param array $data The options data from backup.
     */
    public function process_buchungssatz_options($data) {
        global $DB;

        $data = (object) $data;

        // Only process if the question was newly created by restore.
        $questioncreated = $this->get_mappingid('question_created',
            $this->get_old_parentid('question')) ? true : false;

        if (!$questioncreated) {
            return;
        }

        $data->questionid = $this->get_new_parentid('question');

        // Resolve chart of accounts.
        $data->chartofaccountsid = $this->resolve_chart();

        unset($data->id);
        $DB->insert_record('qtype_buchungssatz_options', $data);
    }

    /**
     * Process an entry element.
     *
     * Handles backward compatibility: defaults weight fields to 1 if missing,
     * removes old fraction field if present.
     *
     * @param array $data The entry data from backup.
     */
    public function process_buchungssatz_entry($data) {
        global $DB;

        $data = (object) $data;

        // Only process if the question was newly created by restore.
        $questioncreated = $this->get_mappingid('question_created',
            $this->get_old_parentid('question')) ? true : false;

        if (!$questioncreated) {
            return;
        }

        $data->questionid = $this->get_new_parentid('question');
        unset($data->id);

        // Backward compatibility: default weight fields to 1 if not present.
        if (!isset($data->weight_sollkonto)) {
            $data->weight_sollkonto = 1;
        }
        if (!isset($data->weight_sollbetrag)) {
            $data->weight_sollbetrag = 1;
        }
        if (!isset($data->weight_habenkonto)) {
            $data->weight_habenkonto = 1;
        }
        if (!isset($data->weight_habenbetrag)) {
            $data->weight_habenbetrag = 1;
        }

        // Backward compatibility: default explanation if not present.
        if (!isset($data->explanation)) {
            $data->explanation = '';
        }

        // Remove old fraction field if present (no longer used).
        unset($data->fraction);

        $DB->insert_record('qtype_buchungssatz_entries', $data);
    }

    /**
     * Resolve the chart of accounts for the current question.
     *
     * 1. If no chart data in backup, return 0.
     * 2. Check cache for already-resolved chart with same name.
     * 3. Find existing match in target course context.
     * 4. If no match, create new chart + accounts.
     * 5. Cache result.
     *
     * @return int The resolved chart ID, or 0 if no chart data.
     */
    protected function resolve_chart(): int {
        if (empty($this->chartname)) {
            return 0;
        }

        // Check cache first.
        $cachekey = $this->chartname;
        if (isset(self::$chartidcache[$cachekey])) {
            return self::$chartidcache[$cachekey];
        }

        // Determine target course context.
        $courseid = $this->step->get_task()->get_courseid();
        $coursecontext = context_course::instance($courseid);
        $contextid = $coursecontext->id;

        // Build accounts array keyed by account number for matching.
        $accountsbynum = [];
        foreach ($this->chartaccounts as $acc) {
            $accountsbynum[$acc->accountnumber] = [
                'accountnumber' => $acc->accountnumber,
                'accountname' => $acc->accountname,
                'accountclass' => $acc->accountclass ?? 0,
                'sortorder' => $acc->sortorder ?? 0,
            ];
        }

        // Try to find an existing matching chart in the target course context.
        $chartid = \qtype_buchungssatz\chart_manager::find_matching_chart_in_context(
            $this->chartname, $contextid, $accountsbynum
        );

        if (!$chartid) {
            // Create new chart + accounts.
            $chartid = \qtype_buchungssatz\chart_manager::create_chart($this->chartname, $contextid);
            foreach ($this->chartaccounts as $acc) {
                \qtype_buchungssatz\chart_manager::add_account(
                    $chartid,
                    $acc->accountnumber,
                    $acc->accountname,
                    $acc->accountclass ?? 0,
                    $acc->sortorder ?? 0
                );
            }
        }

        // Cache for subsequent questions in the same restore.
        self::$chartidcache[$cachekey] = $chartid;

        return $chartid;
    }
}
