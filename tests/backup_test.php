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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/tests/backup_restore_base_testcase.php');
require_once($CFG->dirroot . '/question/type/accounting/tests/helper.php');

/**
 * Regression tests for accounting question backup and restore.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_qtype_accounting_plugin
 * @covers     \restore_qtype_accounting_plugin
 */
final class backup_test extends \core_backup_backup_restore_base_testcase {
    /**
     * Restored entries must reference accounts in the target chart, including when reusing a chart.
     *
     * @dataProvider chart_reuse_provider
     * @param bool $reusechart Whether to create a matching chart before restore.
     */
    public function test_course_restore_preserves_accounts(bool $reusechart): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $chartid = chart_manager::create_chart('Backup chart', $context->id);
        $bankid = account_manager::add($chartid, '1200 Bank', 0);
        $salesid = account_manager::add($chartid, '8400 Sales', 1);
        account_manager::add($chartid, '1000 Cash', 2);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $bankcontext = \qtype_accounting_test_helper::get_question_bank_context($course);
        $category = $generator->create_question_category(['contextid' => $bankcontext->id]);
        // Two questions share the chart; the second contains one-sided compound booking rows.
        foreach (['Sale', 'Compound sale'] as $name) {
            $compound = $name === 'Compound sale';
            $generator->create_question('accounting', null, [
                'category' => $category->id,
                'name' => $name,
                'chartofaccountsid' => $chartid,
                'debitaccount' => $compound ? [$bankid, 0] : [$bankid],
                'creditaccount' => $compound ? [0, $salesid] : [$salesid],
                'debitamount' => $compound ? [123.45, 0] : [123.45],
                'creditamount' => $compound ? [0, 123.45] : [123.45],
                'weight_debitaccount' => [2, 1],
                'weight_creditaccount' => [3, 2],
            ]);
        }

        // Restore twice in one process to ensure chart reuse stays within each target context.
        for ($i = 0; $i < 2; $i++) {
            $target = $this->getDataGenerator()->create_course();
            $targetcontext = \context_course::instance($target->id);
            $existingchartid = null;
            if ($reusechart) {
                $existingchartid = chart_manager::create_chart('Backup chart', $targetcontext->id);
                // Reverse insertion order so source IDs cannot accidentally match the target accounts.
                account_manager::add($existingchartid, '8400 Sales', 1);
                account_manager::add($existingchartid, '1200 Bank', 0);
                account_manager::add($existingchartid, '1000 Cash', 2);
            }

            // The core helper selects the oldest backup-area record, so remove the previous archive first.
            get_file_storage()->delete_area_files($context->id, 'backup', 'course');
            $backupid = $this->perform_backup($course);
            $this->perform_restore($backupid, $target);

            $charts = $DB->get_records('qtype_accounting_charts', ['contextid' => $targetcontext->id]);
            $this->assertCount(1, $charts);
            $restoredchart = reset($charts);
            $this->assertNotEquals($chartid, $restoredchart->id);
            if ($reusechart) {
                $this->assertEquals($existingchartid, $restoredchart->id);
            }
            $accounts = account_manager::get_for_chart($restoredchart->id);
            $this->assertCount(3, $accounts);
            $accountsbyname = [];
            foreach ($accounts as $account) {
                $accountsbyname[$account->accountname] = (int) $account->id;
            }

            $questions = $DB->get_records_sql(
                "SELECT q.*
                   FROM {question} q
                   JOIN {question_versions} v ON v.questionid = q.id
                   JOIN {question_bank_entries} b ON b.id = v.questionbankentryid
                   JOIN {question_categories} c ON c.id = b.questioncategoryid
                  JOIN {context} ctx ON ctx.id = c.contextid
                  WHERE (ctx.id = ? OR ctx.path LIKE ?) AND q.qtype = ?",
                [$targetcontext->id, $targetcontext->path . '/%', 'accounting']
            );
            $this->assertCount(2, $questions);
            foreach ($questions as $question) {
                $loaded = \question_bank::load_question($question->id);
                $this->assertEquals($restoredchart->id, $loaded->chartofaccountsid);
                $this->assertSame($accountsbyname['1200 Bank'], $loaded->entries[0]['debitaccountid']);
                $this->assertEquals(123.45, $loaded->entries[0]['debitamount']);
                $this->assertEquals(2, $loaded->entries[0]['weight_debitaccount']);
                if ($question->name === 'Compound sale') {
                    $this->assertCount(2, $loaded->entries);
                    $this->assertNull($loaded->entries[0]['creditaccountid']);
                    $this->assertNull($loaded->entries[1]['debitaccountid']);
                    $creditentry = $loaded->entries[1];
                } else {
                    $this->assertCount(1, $loaded->entries);
                    $creditentry = $loaded->entries[0];
                }
                $this->assertSame($accountsbyname['8400 Sales'], $creditentry['creditaccountid']);
                $this->assertEquals(123.45, $creditentry['creditamount']);
                $this->assertSame('1200 Bank', $loaded->accountsmap[$loaded->entries[0]['debitaccountid']]);
                $this->assertSame('8400 Sales', $loaded->accountsmap[$creditentry['creditaccountid']]);
            }
        }
    }

    /**
     * Cases for creating or reusing the target chart.
     *
     * @return array
     */
    public static function chart_reuse_provider(): array {
        return ['new chart' => [false], 'existing chart' => [true]];
    }
}
