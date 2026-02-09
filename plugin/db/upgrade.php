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
 * Buchungssatz question type upgrade code.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the qtype_buchungssatz plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_qtype_buchungssatz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024010107) {
        // Add explanation field to qtype_buchungssatz_entries table.
        $table = new xmldb_table('qtype_buchungssatz_entries');
        $field = new xmldb_field('explanation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'fraction');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010107, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010108) {
        // Schema change: Replace accounttype with accountclass, remove description.

        // Step 1: Add accountclass field to accounts table.
        $table = new xmldb_table('qtype_buchungssatz_accounts');
        $field = new xmldb_field('accountclass', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'accountname');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Step 2: Migrate accounttype to accountclass (derive from first digit of account number).
        // Since data can be discarded, we just infer from accountnumber.
        $accounts = $DB->get_records('qtype_buchungssatz_accounts');
        foreach ($accounts as $account) {
            $firstdigit = substr($account->accountnumber, 0, 1);
            $accountclass = is_numeric($firstdigit) ? (int)$firstdigit : 0;
            // Clamp to 0-5 range.
            if ($accountclass > 5) {
                $accountclass = 0;
            }
            $DB->set_field('qtype_buchungssatz_accounts', 'accountclass', $accountclass, ['id' => $account->id]);
        }

        // Step 3: Drop accounttype field from accounts table.
        $field = new xmldb_field('accounttype');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Step 4: Drop description field from charts table.
        $table = new xmldb_table('qtype_buchungssatz_charts');
        $field = new xmldb_field('description');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010108, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010109) {
        // Add accountsindropdown field to options table.
        $table = new xmldb_table('qtype_buchungssatz_options');
        $field = new xmldb_field('accountsindropdown', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'chartofaccountsid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010109, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010110) {
        // Add numberformat field to options table.
        $table = new xmldb_table('qtype_buchungssatz_options');
        $field = new xmldb_field('numberformat', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'de', 'accountsindropdown');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010110, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010111) {
        // Add currency_symbol field to options table.
        $table = new xmldb_table('qtype_buchungssatz_options');
        $field = new xmldb_field('currency_symbol', XMLDB_TYPE_CHAR, '5', null, null, null, '€', 'numberformat');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010111, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010112) {
        // Add decimalplaces field to options table.
        $table = new xmldb_table('qtype_buchungssatz_options');
        $field = new xmldb_field('decimalplaces', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2', 'currency_symbol');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010112, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010114) {
        // Add extraentrydeduction field to options table.
        $table = new xmldb_table('qtype_buchungssatz_options');
        $field = new xmldb_field('extraentrydeduction', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'decimalplaces');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010114, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010115) {
        // Add allornothinggrading field to options table.
        $table = new xmldb_table('qtype_buchungssatz_options');
        $field = new xmldb_field('allornothinggrading', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'extraentrydeduction');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010115, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010116) {
        // Replace single fraction field with 4 weight fields for granular grading.
        $table = new xmldb_table('qtype_buchungssatz_entries');

        // Add weight_sollkonto field.
        $field = new xmldb_field('weight_sollkonto', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1', 'habenbetrag');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add weight_sollbetrag field.
        $field = new xmldb_field('weight_sollbetrag', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1', 'weight_sollkonto');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add weight_habenkonto field.
        $field = new xmldb_field('weight_habenkonto', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1', 'weight_sollbetrag');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add weight_habenbetrag field.
        $field = new xmldb_field('weight_habenbetrag', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '1', 'weight_habenkonto');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Drop the old fraction field.
        $field = new xmldb_field('fraction');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010116, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010119) {
        $table = new xmldb_table('qtype_buchungssatz_accounts');
        $field = new xmldb_field('accountclass', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'accountname');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024010119, 'qtype', 'buchungssatz');
    }

    if ($oldversion < 2024010121) {
        $table = new xmldb_table('qtype_buchungssatz_accounts');
        $field = new xmldb_field('accountclass', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field, 10);
        }

        upgrade_plugin_savepoint(true, 2024010121, 'qtype', 'buchungssatz');
    }

    return true;
}
