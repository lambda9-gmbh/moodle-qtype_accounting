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
 * Buchungssatz question type upgrade code.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the qtype_accounting plugin.
 *
 * Moodle's upgrade API requires a single function with one
 * `if ($oldversion < N) { ... savepoint }` block per version step.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_qtype_accounting_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024010107) {
        // Add explanation field to qtype_accounting_entries table.
        $table = new xmldb_table('qtype_accounting_entries');
        $field = new xmldb_field('explanation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'fraction');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010107, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010108) {
        // Schema change: Replace accounttype with accountclass, remove description.

        // Step 1: Add accountclass field to accounts table.
        $table = new xmldb_table('qtype_accounting_accounts');
        $field = new xmldb_field('accountclass', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'accountname');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Step 2: Migrate accounttype to accountclass (derive from first digit of account number).
        // Since data can be discarded, we just infer from accountnumber.
        $accounts = $DB->get_records('qtype_accounting_accounts');
        foreach ($accounts as $account) {
            $firstdigit = substr($account->accountnumber, 0, 1);
            $accountclass = is_numeric($firstdigit) ? (int)$firstdigit : 0;
            // Clamp to 0-5 range.
            if ($accountclass > 5) {
                $accountclass = 0;
            }
            $DB->set_field('qtype_accounting_accounts', 'accountclass', $accountclass, ['id' => $account->id]);
        }

        // Step 3: Drop accounttype field from accounts table.
        $field = new xmldb_field('accounttype');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Step 4: Drop description field from charts table.
        $table = new xmldb_table('qtype_accounting_charts');
        $field = new xmldb_field('description');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010108, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010109) {
        // Add accountsindropdown field to options table.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field(
            'accountsindropdown',
            XMLDB_TYPE_INTEGER,
            '5',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'chartofaccountsid'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010109, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010110) {
        // Add numberformat field to options table.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field('numberformat', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'de', 'accountsindropdown');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010110, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010111) {
        // Add currency_symbol field to options table.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field('currency_symbol', XMLDB_TYPE_CHAR, '5', null, null, null, '€', 'numberformat');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010111, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010112) {
        // Add decimalplaces field to options table.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field('decimalplaces', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2', 'currency_symbol');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010112, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010114) {
        // Add extraentrydeduction field to options table.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field('extraentrydeduction', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'decimalplaces');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010114, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010115) {
        // Add allornothinggrading field to options table.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field(
            'allornothinggrading',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'extraentrydeduction'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010115, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010116) {
        // Replace single fraction field with 4 weight fields for granular grading.
        $table = new xmldb_table('qtype_accounting_entries');

        // Add weight_debitaccount field.
        $field = new xmldb_field('weight_debitaccount', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1', 'creditamount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add weight_debitamount field.
        $field = new xmldb_field('weight_debitamount', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1', 'weight_debitaccount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add weight_creditaccount field.
        $field = new xmldb_field('weight_creditaccount', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1', 'weight_debitamount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add weight_creditamount field.
        $field = new xmldb_field(
            'weight_creditamount',
            XMLDB_TYPE_INTEGER,
            '5',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'weight_creditaccount'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop the old fraction field.
        $field = new xmldb_field('fraction');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010116, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010119) {
        $table = new xmldb_table('qtype_accounting_accounts');
        $field = new xmldb_field('accountclass', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'accountname');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010119, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010121) {
        $table = new xmldb_table('qtype_accounting_accounts');
        $field = new xmldb_field('accountclass', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field, 10);
        }

        upgrade_plugin_savepoint(true, 2024010121, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010126) {
        // Remove accountnumber and accountclass from accounts table, simplify to name-only.

        $table = new xmldb_table('qtype_accounting_accounts');

        // Step 1: Drop the old unique index on chartid-accountnumber.
        $index = new xmldb_index('chartid-accountnumber', XMLDB_INDEX_UNIQUE, ['chartid', 'accountnumber']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Step 2: Bake accountnumber into accountname for existing data (e.g. "1200" + "Bank" => "1200 Bank").
        $accounts = $DB->get_records('qtype_accounting_accounts');
        foreach ($accounts as $account) {
            if (!empty($account->accountnumber) && $account->accountnumber !== $account->accountname) {
                $newname = $account->accountnumber . ' ' . $account->accountname;
                $DB->set_field('qtype_accounting_accounts', 'accountname', $newname, ['id' => $account->id]);
            }
        }

        // Step 3: Drop accountnumber column.
        $field = new xmldb_field('accountnumber');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Step 4: Drop accountclass column.
        $field = new xmldb_field('accountclass');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Step 5: Add unique index on chartid-accountname.
        $index = new xmldb_index('chartid-accountname', XMLDB_INDEX_UNIQUE, ['chartid', 'accountname']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Step 6: Widen debitaccount and creditaccount in entries table to char(255).
        $table = new xmldb_table('qtype_accounting_entries');

        $field = new xmldb_field('debitaccount', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'sortorder');
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('creditaccount', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'debitamount');
        $dbman->change_field_precision($table, $field);

        upgrade_plugin_savepoint(true, 2024010126, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010127) {
        // Convert extraentrydeduction from percentage (0-100) to fraction (0.0-1.0).
        $DB->execute(
            "UPDATE {qtype_accounting_options} SET extraentrydeduction = extraentrydeduction / 100 WHERE extraentrydeduction > 1"
        );

        // Increase precision to support fractional values like 0.1666667.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field('extraentrydeduction', XMLDB_TYPE_NUMBER, '10, 7', null, null, null, null, 'currency_symbol');
        $dbman->change_field_precision($table, $field);

        upgrade_plugin_savepoint(true, 2024010127, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010128) {
        // Remove decimalplaces field - always use 2 decimal places.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field('decimalplaces');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010128, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010129) {
        // Remove currency_symbol field.
        $table = new xmldb_table('qtype_accounting_options');
        $field = new xmldb_field('currency_symbol');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010129, 'qtype', 'accounting');
    }

    if ($oldversion < 2024010130) {
        // Refactor: Replace name-based account references with ID-based references.
        $table = new xmldb_table('qtype_accounting_entries');

        // Step 1: Add new integer ID columns.
        $field = new xmldb_field('debitaccountid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('creditaccountid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'debitamount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Step 2: Migrate existing name-based data to IDs.
        $entries = $DB->get_records('qtype_accounting_entries');
        foreach ($entries as $entry) {
            $options = $DB->get_record('qtype_accounting_options', ['questionid' => $entry->questionid]);
            if (!$options || !$options->chartofaccountsid) {
                continue;
            }
            $chartid = $options->chartofaccountsid;

            if (!empty($entry->debitaccount)) {
                $acc = $DB->get_record(
                    'qtype_accounting_accounts',
                    ['chartid' => $chartid, 'accountname' => $entry->debitaccount]
                );
                if ($acc) {
                    $entry->debitaccountid = $acc->id;
                }
            }

            if (!empty($entry->creditaccount)) {
                $acc = $DB->get_record(
                    'qtype_accounting_accounts',
                    ['chartid' => $chartid, 'accountname' => $entry->creditaccount]
                );
                if ($acc) {
                    $entry->creditaccountid = $acc->id;
                }
            }

            $DB->update_record('qtype_accounting_entries', $entry);
        }

        // Step 3: Drop old name-based columns.
        $field = new xmldb_field('debitaccount');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('creditaccount');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010130, 'qtype', 'accounting');
    }

    return true;
}
