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
 * Each question carries its own accounts. Backups of earlier versions carry the shared chart of the
 * question instead; its account IDs become the account keys, which the entries and responses in
 * those backups already store, so no response needs recoding.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_accounting_plugin extends restore_qtype_plugin {
    /** @var array Accounts (accountkey, sortorder) keyed by account name, collected for the current question. */
    protected $accounts = [];

    /** @var int|null Source question owning the collected accounts. */
    protected $accountsquestionid = null;

    /**
     * Convert custom backup elements into question data for restore identity comparison.
     *
     * @param array $backupdata Parsed question backup data.
     * @return \stdClass Question data in the structure of {@see qtype_accounting::get_question_options()}.
     */
    public static function convert_backup_to_questiondata(array $backupdata): \stdClass {
        $questiondata = parent::convert_backup_to_questiondata($backupdata);
        $plugin = $backupdata['plugin_qtype_accounting_question'] ?? [];
        $options = (object) ($plugin['accounting_options'][0] ?? []);
        unset($options->chartofaccountsid);
        $records = $plugin['accounting_accounts']['account'] ?? [];
        if (!$records) {
            // Backups of earlier versions store the question's chart; account IDs serve as keys.
            $records = array_map(fn($account) => [
                'accountkey' => $account['id'] ?? 0,
                'accountname' => $account['accountname'] ?? '',
                'sortorder' => $account['sortorder'] ?? 0,
            ], $plugin['accounting_chart'][0]['chart_accounts']['chart_account'] ?? []);
        }
        $options->accounts = [];
        $keysbyname = [];
        foreach ($records as $record) {
            $options->accounts[(int)$record['accountkey']] = (object)[
                'accountkey' => (int)$record['accountkey'],
                'accountname' => $record['accountname'],
                'sortorder' => (int)$record['sortorder'],
            ];
            $keysbyname[$record['accountname']] = (int)$record['accountkey'];
        }
        $options->entries = array_map(function ($entry) use ($keysbyname) {
            $entry = (object) $entry;
            self::normalise_entry($entry, $keysbyname);
            return $entry;
        }, $plugin['accounting_entries']['entry'] ?? []);
        $questiondata->options = $options;
        return $questiondata;
    }

    /**
     * Exclude internal IDs from question identity comparison.
     *
     * @return array Paths to fields omitted from the identity hash.
     */
    protected function define_excluded_identity_hash_fields(): array {
        return ['/options/chartofaccountsid', '/options/entries/id', '/options/entries/questionid'];
    }

    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return restore_path_element[] The restore path elements.
     */
    protected function define_question_plugin_structure() {
        return [
            new restore_path_element('accounting_account', $this->get_pathfor('/accounting_accounts/account')),
            // Backups of earlier versions.
            new restore_path_element('accounting_chart', $this->get_pathfor('/accounting_chart')),
            new restore_path_element(
                'accounting_chart_account',
                $this->get_pathfor('/accounting_chart/chart_accounts/chart_account')
            ),
            new restore_path_element('accounting_options', $this->get_pathfor('/accounting_options')),
            new restore_path_element('accounting_entry', $this->get_pathfor('/accounting_entries/entry')),
        ];
    }

    /**
     * Start collecting the accounts of another question.
     */
    protected function reset_question_state(): void {
        $questionid = $this->get_old_parentid('question');
        if ($this->accountsquestionid === $questionid) {
            return;
        }
        $this->accountsquestionid = $questionid;
        $this->accounts = [];
    }

    /**
     * Collect an account of the question.
     *
     * @param array $data The account data from backup.
     */
    public function process_accounting_account($data) {
        $this->reset_question_state();
        $this->accounts[$data['accountname']] = ['accountkey' => (int)$data['accountkey'], 'sortorder' => (int)$data['sortorder']];
    }

    /**
     * Start collecting the accounts of a chart from a backup of an earlier version.
     *
     * @param array $data The chart data from backup.
     */
    public function process_accounting_chart($data) {
        $this->reset_question_state();
    }

    /**
     * Collect an account of a chart from a backup of an earlier version; its ID becomes the account key.
     *
     * @param array $data The account data from backup.
     */
    public function process_accounting_chart_account($data) {
        $this->reset_question_state();
        $this->accounts[$data['accountname']] = ['accountkey' => (int)$data['id'], 'sortorder' => (int)($data['sortorder'] ?? 0)];
    }

    /**
     * Insert the options and the accounts of a newly created question.
     *
     * @param array $data The options data from backup.
     */
    public function process_accounting_options($data) {
        global $DB;

        $this->reset_question_state();
        if (!$this->question_was_newly_created()) {
            return;
        }
        $data = (object) $data;
        $data->questionid = $this->get_new_parentid('question');
        unset($data->id, $data->chartofaccountsid);
        $DB->insert_record('qtype_accounting_options', $data);
        // Keep the backed-up sort order: it is part of the identity of the question on later restores.
        $records = [];
        foreach ($this->accounts as $name => $account) {
            $records[] = ['questionid' => $data->questionid, 'accountname' => $name] + $account;
        }
        if ($records) {
            $DB->insert_records(\qtype_accounting\question_accounts::TABLE, $records);
        }
    }

    /**
     * Insert an entry of a newly created question.
     *
     * @param array $data The entry data from backup.
     */
    public function process_accounting_entry($data) {
        global $DB;

        if (!$this->question_was_newly_created()) {
            return;
        }
        $data = (object) $data;
        $data->questionid = $this->get_new_parentid('question');
        unset($data->id);
        self::normalise_entry($data, array_map(fn($account) => $account['accountkey'], $this->accounts));
        $DB->insert_record('qtype_accounting_entries', $data);
    }

    /**
     * Check whether the current question was newly created by this restore.
     *
     * @return bool True when the parent question is a new mapping; false if an existing question is reused.
     */
    protected function question_was_newly_created(): bool {
        return (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));
    }

    /**
     * Bring an entry of any backup version into the current structure.
     *
     * Backups before weighted scoring have no weights; very old backups reference accounts by name.
     *
     * @param \stdClass $entry Entry data, modified in place.
     * @param array $keysbyname Account keys keyed by account name.
     */
    protected static function normalise_entry(\stdClass $entry, array $keysbyname): void {
        foreach (['weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount'] as $field) {
            $entry->$field = $entry->$field ?? 1;
        }
        foreach (['debitaccount', 'creditaccount'] as $field) {
            $keyfield = $field . 'id';
            if (isset($entry->$field) && !isset($entry->$keyfield)) {
                $entry->$keyfield = $keysbyname[$entry->$field] ?? null;
            }
            unset($entry->$field);
        }
        $entry->explanation = $entry->explanation ?? '';
        unset($entry->fraction);
    }
}
