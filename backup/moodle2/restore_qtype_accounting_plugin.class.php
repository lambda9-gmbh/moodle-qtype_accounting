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
 * Restore routines for qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restore plugin class that provides the necessary information to restore Buchungssatz questions.
 *
 * Collects chart data in instance variables, then creates everything in
 * process_accounting_options() which runs after chart elements due to XML ordering.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_accounting_plugin extends restore_qtype_plugin {
    /** @var string Chart name from the backup XML. */
    protected $chartname = '';

    /** @var array Account data collected from backup XML. */
    protected $chartaccounts = [];

    /** @var array Map of source account IDs to IDs in the current question's restored chart. */
    protected $accountidmap = [];

    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return array
     */
    protected function define_question_plugin_structure() {
        $paths = [];

        // Chart data (processed first in XML order).
        $elepath = $this->get_pathfor('/accounting_chart');
        $paths[] = new restore_path_element('accounting_chart', $elepath);

        $elepath = $this->get_pathfor('/accounting_chart/chart_accounts/chart_account');
        $paths[] = new restore_path_element('accounting_chart_account', $elepath);

        // Options (processed after chart data).
        $elepath = $this->get_pathfor('/accounting_options');
        $paths[] = new restore_path_element('accounting_options', $elepath);

        // Entries.
        $elepath = $this->get_pathfor('/accounting_entries/entry');
        $paths[] = new restore_path_element('accounting_entry', $elepath);

        return $paths;
    }

    /**
     * Process the chart element - store chart name and reset accounts array.
     *
     * @param array $data The chart data from backup.
     */
    public function process_accounting_chart($data) {
        $data = (object) $data;
        $this->chartname = $data->name ?? '';
        $this->chartaccounts = [];
        $this->accountidmap = [];
    }

    /**
     * Process a chart account element - append to the accounts collection.
     *
     * @param array $data The account data from backup.
     */
    public function process_accounting_chart_account($data) {
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
    public function process_accounting_options($data) {
        global $DB;

        $data = (object) $data;

        // Only process if the question was newly created by restore.
        $questioncreated = $this->get_mappingid(
            'question_created',
            $this->get_old_parentid('question')
        ) ? true : false;

        if (!$questioncreated) {
            return;
        }

        $data->questionid = $this->get_new_parentid('question');

        // Resolve chart of accounts.
        $data->chartofaccountsid = $this->resolve_chart();
        $this->map_chart_account_ids($data->chartofaccountsid);

        unset($data->id);
        $DB->insert_record('qtype_accounting_options', $data);
    }

    /**
     * Process an entry element.
     *
     * Handles backward compatibility: defaults weight fields to 1 if missing,
     * removes old fraction field if present.
     *
     * @param array $data The entry data from backup.
     */
    public function process_accounting_entry($data) {
        global $DB;

        $data = (object) $data;
        if (!$this->question_was_newly_created()) {
            return;
        }

        $data->questionid = $this->get_new_parentid('question');
        unset($data->id);

        $this->default_entry_weights($data);
        $this->normalize_legacy_entry_fields($data);
        $this->resolve_entry_account_ids($data);

        $DB->insert_record('qtype_accounting_entries', $data);
    }

    /**
     * Check whether the question this entry belongs to was newly created by the current restore.
     *
     * @return bool True when the parent question is a new mapping; false if it already existed.
     */
    protected function question_was_newly_created(): bool {
        return (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));
    }

    /**
     * Apply weight=1 defaults to the four weight fields, for backups that predate weighted scoring.
     *
     * @param \stdClass $data Entry data, modified in place.
     */
    protected function default_entry_weights(\stdClass $data): void {
        foreach (['weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount'] as $field) {
            if (!isset($data->$field)) {
                $data->$field = 1;
            }
        }
    }

    /**
     * Normalise legacy fields: default explanation and strip the obsolete fraction field.
     *
     * @param \stdClass $data Entry data, modified in place.
     */
    protected function normalize_legacy_entry_fields(\stdClass $data): void {
        if (!isset($data->explanation)) {
            $data->explanation = '';
        }
        unset($data->fraction);
    }

    /**
     * Remap account IDs to the restored chart, or resolve legacy name-based account fields.
     *
     * @param \stdClass $data Entry data, modified in place.
     */
    protected function resolve_entry_account_ids(\stdClass $data): void {
        foreach (['debitaccount', 'creditaccount'] as $field) {
            $idfield = $field . 'id';
            if (isset($data->$field) && !isset($data->$idfield)) {
                $data->$idfield = $this->resolve_account_name_to_id($data->$field, $data->questionid);
            } else {
                // Never retain source database IDs, which may refer to unrelated accounts on the target.
                $data->$idfield = $this->accountidmap[$data->$idfield ?? 0] ?? null;
            }
            unset($data->$field);
        }
    }

    /**
     * Map backup account IDs by name to the accounts in the resolved target chart.
     *
     * This works for both newly created charts and matching existing charts.
     *
     * @param int $chartid The resolved chart ID.
     */
    protected function map_chart_account_ids(int $chartid): void {
        $this->accountidmap = [];
        $accountsbyname = [];
        foreach (\qtype_accounting\account_manager::get_for_chart($chartid) as $account) {
            $accountsbyname[$account->accountname] = (int) $account->id;
        }
        foreach ($this->chartaccounts as $account) {
            if (isset($account->id, $accountsbyname[$account->accountname])) {
                $this->accountidmap[$account->id] = $accountsbyname[$account->accountname];
            }
        }
    }

    /**
     * Resolve the chart of accounts for the current question.
     *
     * 1. If no chart data in backup, return 0.
     * 2. Find existing match in target course context, including charts created for earlier questions.
     * 3. If no match, create new chart + accounts.
     *
     * @return int The resolved chart ID, or 0 if no chart data.
     */
    protected function resolve_chart(): int {
        if (empty($this->chartname)) {
            return 0;
        }

        // Determine target course context.
        $courseid = $this->step->get_task()->get_courseid();
        $coursecontext = context_course::instance($courseid);
        $contextid = $coursecontext->id;

        // Build accounts array keyed by account name for matching.
        $accountsbyname = [];
        foreach ($this->chartaccounts as $acc) {
            $accountsbyname[$acc->accountname] = [
                'accountname' => $acc->accountname,
                'sortorder' => $acc->sortorder ?? 0,
            ];
        }

        // Try to find an existing matching chart in the target course context or among the site charts.
        $chartid = \qtype_accounting\chart_manager::find_matching_chart(
            $this->chartname,
            $contextid,
            $accountsbyname
        );

        if (!$chartid) {
            // Create new chart + accounts.
            $chartid = \qtype_accounting\chart_manager::create_chart($this->chartname, $contextid);
            foreach ($this->chartaccounts as $acc) {
                \qtype_accounting\account_manager::add(
                    $chartid,
                    $acc->accountname,
                    $acc->sortorder ?? 0
                );
            }
        }

        return $chartid;
    }

    /**
     * Resolve an account name to its ID within the question's chart.
     *
     * Used for backward compatibility when restoring old backups with name-based entries.
     *
     * @param string $accountname The account name to resolve.
     * @param int $questionid The question ID.
     * @return int|null The account ID, or null if not found.
     */
    protected function resolve_account_name_to_id(string $accountname, int $questionid): ?int {
        global $DB;

        if (empty($accountname)) {
            return null;
        }

        $options = $DB->get_record('qtype_accounting_options', ['questionid' => $questionid]);
        if (!$options || !$options->chartofaccountsid) {
            return null;
        }

        $account = $DB->get_record(
            'qtype_accounting_accounts',
            ['chartid' => $options->chartofaccountsid, 'accountname' => $accountname]
        );

        return $account ? (int) $account->id : null;
    }
}
