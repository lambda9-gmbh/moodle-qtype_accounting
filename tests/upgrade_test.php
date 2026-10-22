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
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/question/type/accounting/db/upgrade.php');

/**
 * Tests for the legacy account-name migration.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_qtype_accounting_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Add the legacy columns to the isolated test table.
     *
     * @return \xmldb_table The entries table.
     */
    private function add_legacy_columns(): \xmldb_table {
        global $DB;
        $table = new \xmldb_table('qtype_accounting_entries');
        foreach (['debitaccount', 'creditaccount'] as $name) {
            $DB->get_manager()->add_field($table, new \xmldb_field($name, XMLDB_TYPE_CHAR, '255'));
        }
        set_config('version', 2024010129, 'qtype_accounting');
        return $table;
    }

    /**
     * Always restore the installed schema, including if a migration assertion fails.
     *
     * @param \xmldb_table $table The entries table.
     */
    private function remove_legacy_columns(\xmldb_table $table): void {
        global $DB;
        foreach (['debitaccount', 'creditaccount'] as $name) {
            $field = new \xmldb_field($name);
            if ($DB->get_manager()->field_exists($table, $field)) {
                $DB->get_manager()->drop_field($table, $field);
            }
        }
    }

    /**
     * Match within each question's chart; retain empty/unmatched IDs and skip questions without a chart.
     */
    public function test_migrate_legacy_account_names(): void {
        global $DB;
        $this->resetAfterTest();
        $chartid = chart_manager::create_chart('First', \context_system::instance()->id);
        $bankid = account_manager::add($chartid, 'Bank');
        $salesid = account_manager::add($chartid, 'Sales');
        account_manager::add($chartid, '0');
        $otherchartid = chart_manager::create_chart('Second', \context_system::instance()->id);
        $otherbankid = account_manager::add($otherchartid, 'Bank');
        foreach ([101 => $chartid, 102 => $otherchartid, 103 => 0] as $questionid => $questionchart) {
            $DB->insert_record('qtype_accounting_options', (object) [
                'questionid' => $questionid, 'chartofaccountsid' => $questionchart,
            ]);
        }
        // Use the database's own comparison as the expectation for case/collation differences between engines.
        $casematch = $DB->get_record('qtype_accounting_accounts', ['chartid' => $chartid, 'accountname' => 'bank']);
        $cases = [
            [101, 'Bank', 'Sales', null, null, $bankid, $salesid],
            [101, 'Bank', 'Sales', $salesid, $bankid, $bankid, $salesid],
            [102, 'Bank', 'Missing', null, null, $otherbankid, null],
            [101, 'Missing', '', $bankid, $salesid, $bankid, $salesid],
            [101, '', '0', null, null, null, null],
            [103, 'Bank', 'Sales', null, null, null, null],
            [104, 'Bank', 'Sales', null, null, null, null],
            [101, 'bank', 'Sales', null, null, $casematch ? (int) $casematch->id : null, $salesid],
        ];
        $table = $this->add_legacy_columns();
        try {
            $expected = [];
            foreach ($cases as [$questionid, $debit, $credit, $olddebit, $oldcredit, $newdebit, $newcredit]) {
                $id = $DB->insert_record('qtype_accounting_entries', (object) [
                    'questionid' => $questionid,
                    'debitaccount' => $debit, 'creditaccount' => $credit,
                    'debitaccountid' => $olddebit, 'creditaccountid' => $oldcredit,
                    'debitamount' => 123.45, 'creditamount' => 123.45,
                    'weight_debitaccount' => 3, 'weight_creditamount' => 2,
                    'explanation' => 'Preserved explanation',
                ]);
                $expected[$id] = [$newdebit, $newcredit];
            }

            $this->assertTrue(xmldb_qtype_accounting_upgrade(2024010129));
            foreach ($expected as $id => [$debitid, $creditid]) {
                $entry = $DB->get_record('qtype_accounting_entries', ['id' => $id], '*', MUST_EXIST);
                $this->assertSame($debitid, $entry->debitaccountid === null ? null : (int) $entry->debitaccountid);
                $this->assertSame($creditid, $entry->creditaccountid === null ? null : (int) $entry->creditaccountid);
                $this->assertEquals(123.45, $entry->debitamount);
                $this->assertEquals(123.45, $entry->creditamount);
                $this->assertEquals(3, $entry->weight_debitaccount);
                $this->assertEquals(2, $entry->weight_creditamount);
                $this->assertSame('Preserved explanation', $entry->explanation);
            }
            $this->assertFalse($DB->get_manager()->field_exists($table, new \xmldb_field('debitaccount')));
            $this->assertFalse($DB->get_manager()->field_exists($table, new \xmldb_field('creditaccount')));
            // Later upgrade steps run as well, so the final savepoint is at least the migration's one.
            $this->assertGreaterThanOrEqual(2024010130, (int) get_config('qtype_accounting', 'version'));
            $this->assertTrue($DB->get_manager()->field_exists('qtype_accounting_options', 'gradingmode'));
        } finally {
            $this->remove_legacy_columns($table);
        }
    }
}
