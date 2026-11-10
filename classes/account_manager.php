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
 * Account manager for charts of accounts in qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * CRUD operations for individual accounts inside a chart of accounts.
 *
 * Chart-level operations live in {@see chart_manager}.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account_manager {
    /**
     * Add an account to a chart.
     *
     * @param int $chartid Chart ID.
     * @param string $accountname Account name.
     * @param int $sortorder Sort order.
     * @return int The new account ID.
     */
    public static function add(int $chartid, string $accountname, int $sortorder = 0): int {
        global $DB;

        self::check_duplicate($chartid, $accountname);

        $account = new \stdClass();
        $account->chartid = $chartid;
        $account->accountname = $accountname;
        $account->sortorder = $sortorder;

        return $DB->insert_record('qtype_accounting_accounts', $account);
    }

    /**
     * Get accounts for a chart.
     *
     * @param int $chartid Chart ID.
     * @param string $sort SQL ORDER BY clause. Defaults to 'accountname'.
     * @return array The account records.
     */
    public static function get_for_chart(int $chartid, string $sort = 'accountname'): array {
        global $DB;
        return $DB->get_records(
            'qtype_accounting_accounts',
            ['chartid' => $chartid],
            $sort
        );
    }

    /**
     * Update an account.
     *
     * @param int $accountid Account ID.
     * @param string $accountname Account name.
     * @return bool True on success.
     */
    public static function update(int $accountid, string $accountname): bool {
        global $DB;

        $account = $DB->get_record('qtype_accounting_accounts', ['id' => $accountid], '*', MUST_EXIST);
        if ($account->accountname === $accountname) {
            return true;
        }
        self::check_duplicate((int)$account->chartid, $accountname);
        $account->accountname = $accountname;

        return $DB->update_record('qtype_accounting_accounts', $account);
    }

    /**
     * Delete an account.
     *
     * @param int $accountid Account ID.
     * @return bool True on success.
     */
    public static function delete(int $accountid): bool {
        global $DB;
        return $DB->delete_records('qtype_accounting_accounts', ['id' => $accountid]);
    }

    /**
     * Find solution and historical-attempt references using bounded database reads.
     *
     * @param array $accountids Account IDs.
     * @return array One entry per question bank entry, keyed by its newest referencing question ID.
     */
    public static function get_using_questions(array $accountids): array {
        global $DB;
        if (!$accountids) {
            return [];
        }
        [$debitsql, $debitparams] = $DB->get_in_or_equal($accountids, SQL_PARAMS_NAMED, 'debit');
        [$creditsql, $creditparams] = $DB->get_in_or_equal($accountids, SQL_PARAMS_NAMED, 'credit');
        $questions = $DB->get_records_sql(
            "SELECT DISTINCT q.id, q.name, v.questionbankentryid, v.version
               FROM {question} q
               JOIN {question_versions} v ON v.questionid = q.id
               JOIN {qtype_accounting_entries} e ON e.questionid = q.id
              WHERE e.debitaccountid $debitsql OR e.creditaccountid $creditsql",
            $debitparams + $creditparams
        );
        [$valuesql, $valueparams] = $DB->get_in_or_equal(array_map('strval', $accountids), SQL_PARAMS_NAMED, 'account');
        $attempts = $DB->get_records_sql(
            "SELECT DISTINCT q.id, q.name, v.questionbankentryid, v.version
               FROM {question} q
               JOIN {question_versions} v ON v.questionid = q.id
               JOIN {question_attempts} a ON a.questionid = q.id
               JOIN {question_attempt_steps} s ON s.questionattemptid = a.id
               JOIN {question_attempt_step_data} d ON d.attemptstepid = s.id
              WHERE q.qtype = :qtype AND d.value $valuesql
                AND (d.name LIKE :debit OR d.name LIKE :credit)",
            $valueparams + ['qtype' => 'accounting',
                'debit' => $DB->sql_like_escape('debitaccount_') . '%',
                'credit' => $DB->sql_like_escape('creditaccount_') . '%']
        );
        // Versions have separate question IDs but belong to one logical question.
        // Keep historical usage while avoiding a repeated warning for each version.
        $byentry = [];
        foreach ($questions + $attempts as $question) {
            $entryid = $question->questionbankentryid;
            if (!isset($byentry[$entryid]) || $question->version > $byentry[$entryid]->version) {
                $byentry[$entryid] = $question;
            }
        }
        return array_column($byentry, null, 'id');
    }

    /**
     * Reject duplicate names before reaching the database unique constraint.
     *
     * @param int $chartid Chart ID.
     * @param string $accountname Proposed account name.
     */
    private static function check_duplicate(int $chartid, string $accountname): void {
        global $DB;
        if (\core_text::strlen($accountname) > 255) {
            throw new \moodle_exception('maximumchars', '', '', 255);
        }
        if ($DB->record_exists('qtype_accounting_accounts', ['chartid' => $chartid, 'accountname' => $accountname])) {
            throw new \moodle_exception('err_duplicateaccount', 'qtype_accounting');
        }
    }

    /**
     * Import accounts from text content into an existing chart.
     *
     * Each line in $content is one account name. Lines whose account name already
     * exists in the chart are skipped (no duplicates created).
     *
     * @param int $chartid Chart ID.
     * @param string $content Text content with one account name per line.
     * @return array ['imported' => int, 'errors' => array]
     */
    public static function import_into_chart(int $chartid, string $content): array {
        global $DB;

        $result = ['imported' => 0, 'errors' => []];

        try {
            $parsed = import_helper::parse_csv($content);
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        // Get existing accounts to avoid duplicates.
        $existing = $DB->get_records_menu(
            'qtype_accounting_accounts',
            ['chartid' => $chartid],
            '',
            'accountname, id'
        );

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($parsed['accounts'] as $accountname => $accountdata) {
                if (isset($existing[$accountname])) {
                    continue;
                }
                self::add($chartid, $accountdata['accountname'], $accountdata['sortorder']);
                $result['imported']++;
            }

            $transaction->allow_commit();
        } catch (\Exception $exception) {
            $transaction->rollback($exception);
        }

        return $result;
    }
}
