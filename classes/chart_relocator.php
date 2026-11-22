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

namespace qtype_accounting;

/**
 * Keeps charts of accounts in the question bank of the questions that use them.
 *
 * A question that moves to another question bank gets a chart in that bank: an identical chart
 * there is reused, otherwise the chart is copied. The question's options, correct entries and
 * saved attempts are switched to the account IDs of that chart. A chart in a context that can no
 * longer hold questions (a course context from Moodle 5.0) moves along instead.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_relocator {
    /**
     * Make sure a question uses a chart that belongs to the given question bank context.
     *
     * @param int $questionid The question ID.
     * @param \context $targetcontext The question category context the question moves to.
     */
    public static function relocate_question(int $questionid, \context $targetcontext): void {
        global $DB;

        $chartid = (int)$DB->get_field('qtype_accounting_options', 'chartofaccountsid', ['questionid' => $questionid]);
        $chart = $chartid ? chart_manager::get_chart($chartid) : false;
        if (!$chart || chart_manager::is_site_chart($chart)) {
            return;
        }
        $targetcontext = chart_manager::get_chart_context($targetcontext);
        if ((int)$chart->contextid === (int)$targetcontext->id) {
            return;
        }

        $accounts = account_manager::get_for_chart($chartid);
        $byname = [];
        foreach ($accounts as $account) {
            $byname[$account->accountname] = ['accountname' => $account->accountname, 'sortorder' => $account->sortorder];
        }
        $newchartid = chart_manager::find_matching_chart_in_context($chart->name, $targetcontext->id, $byname, true);
        if (!$newchartid && !self::is_usable_context((int)$chart->contextid)) {
            // The chart's own context can no longer hold questions, so the chart moves along.
            $DB->set_field('qtype_accounting_charts', 'contextid', $targetcontext->id, ['id' => $chartid]);
            self::move_unused_charts((int)$chart->contextid, $targetcontext);
            return;
        }
        $newchartid = $newchartid ?: chart_manager::duplicate_chart($chartid, $targetcontext->id);
        self::switch_question_chart($questionid, $chartid, $newchartid);
    }

    /**
     * Move every chart out of contexts that can no longer hold questions.
     *
     * Used by the upgrade: a chart moves to the question bank of most of its questions, other questions
     * get a chart in their own bank. Unused charts of a course move to the course's default question bank.
     * Charts of deleted contexts are kept for their questions or removed if unused.
     */
    public static function relocate_all(): void {
        global $DB;

        chart_manager::delete_orphaned_charts();
        $charts = $DB->get_records('qtype_accounting_charts', null, 'id');
        foreach ($charts as $chart) {
            if (self::is_usable_context((int)$chart->contextid)) {
                continue;
            }
            $questions = self::get_question_contexts((int)$chart->id);
            if (!$questions) {
                $context = \context::instance_by_id($chart->contextid, IGNORE_MISSING);
                if ($context) {
                    $target = chart_manager::get_chart_context($context);
                    $DB->set_field('qtype_accounting_charts', 'contextid', $target->id, ['id' => $chart->id]);
                }
                continue;
            }
            // The chart moves to the bank of most of its questions; the others are relocated from there.
            $counts = array_count_values($questions);
            arsort($counts);
            $home = \context::instance_by_id(array_key_first($counts));
            $DB->set_field('qtype_accounting_charts', 'contextid', $home->id, ['id' => $chart->id]);
            foreach ($questions as $questionid => $contextid) {
                if ((int)$contextid !== (int)$home->id) {
                    self::relocate_question($questionid, \context::instance_by_id($contextid));
                }
            }
        }
    }

    /**
     * Whether a chart in this context is reachable from the question editor.
     *
     * @param int $contextid The chart's context ID.
     * @return bool False for deleted contexts and contexts that cannot hold questions.
     */
    protected static function is_usable_context(int $contextid): bool {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        return $context && chart_manager::is_chart_context($context);
    }

    /**
     * Move the unused charts of a context that can no longer hold questions into a question bank.
     *
     * @param int $oldcontextid The old context ID.
     * @param \context $targetcontext The question bank context.
     */
    protected static function move_unused_charts(int $oldcontextid, \context $targetcontext): void {
        global $DB;
        $DB->execute(
            "UPDATE {qtype_accounting_charts}
                SET contextid = :target
              WHERE contextid = :old
                AND NOT EXISTS (
                    SELECT 1 FROM {qtype_accounting_options} o WHERE o.chartofaccountsid = {qtype_accounting_charts}.id
                )",
            ['target' => $targetcontext->id, 'old' => $oldcontextid]
        );
    }

    /**
     * Get the chart contexts of the questions that use a chart.
     *
     * @param int $chartid The chart ID.
     * @return array Chart context IDs keyed by question ID.
     */
    protected static function get_question_contexts(int $chartid): array {
        global $DB;
        $records = $DB->get_records_sql_menu(
            "SELECT o.questionid, qc.contextid
               FROM {qtype_accounting_options} o
               JOIN {question_versions} qv ON qv.questionid = o.questionid
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE o.chartofaccountsid = ?",
            [$chartid]
        );
        $result = [];
        foreach ($records as $questionid => $contextid) {
            $context = \context::instance_by_id($contextid, IGNORE_MISSING);
            if ($context) {
                $result[$questionid] = (int)chart_manager::get_chart_context($context)->id;
            }
        }
        return $result;
    }

    /**
     * Switch a question, its correct entries and its saved attempts to the accounts of another chart.
     *
     * Accounts are matched by name; both charts contain the same account names.
     *
     * @param int $questionid The question ID.
     * @param int $oldchartid The current chart ID.
     * @param int $newchartid The new chart ID.
     */
    protected static function switch_question_chart(int $questionid, int $oldchartid, int $newchartid): void {
        global $DB;

        $newids = [];
        foreach (account_manager::get_for_chart($newchartid) as $account) {
            $newids[$account->accountname] = (int)$account->id;
        }
        $mapping = [];
        foreach (account_manager::get_for_chart($oldchartid) as $account) {
            if (isset($newids[$account->accountname])) {
                $mapping[(int)$account->id] = $newids[$account->accountname];
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('qtype_accounting_options', 'chartofaccountsid', $newchartid, ['questionid' => $questionid]);
        foreach ($DB->get_records('qtype_accounting_entries', ['questionid' => $questionid]) as $entry) {
            $DB->update_record('qtype_accounting_entries', (object)[
                'id' => $entry->id,
                'debitaccountid' => $mapping[(int)$entry->debitaccountid] ?? $entry->debitaccountid,
                'creditaccountid' => $mapping[(int)$entry->creditaccountid] ?? $entry->creditaccountid,
            ]);
        }
        self::switch_attempt_accounts($questionid, $mapping);
        $transaction->allow_commit();

        \question_bank::notify_question_edited($questionid);
    }

    /**
     * Switch the account IDs stored in the attempts of a question.
     *
     * @param int $questionid The question ID.
     * @param int[] $mapping New account IDs keyed by old account IDs.
     */
    protected static function switch_attempt_accounts(int $questionid, array $mapping): void {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT d.id, d.name, d.value
               FROM {question_attempt_step_data} d
               JOIN {question_attempt_steps} s ON s.id = d.attemptstepid
               JOIN {question_attempts} qa ON qa.id = s.questionattemptid
              WHERE qa.questionid = :questionid
                AND (" . $DB->sql_like('d.name', ':debit') . " OR " . $DB->sql_like('d.name', ':credit') . ")",
            ['questionid' => $questionid, 'debit' => 'debitaccount\_%', 'credit' => 'creditaccount\_%']
        );
        foreach ($rows as $row) {
            if (preg_match('/^(debit|credit)account_\d+$/D', $row->name) && isset($mapping[(int)$row->value])) {
                $DB->set_field('question_attempt_step_data', 'value', $mapping[(int)$row->value], ['id' => $row->id]);
            }
        }
    }
}
