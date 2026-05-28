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
 * Backup routines for qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup Buchungssatz questions.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_accounting_plugin extends backup_qtype_plugin {
    /**
     * Returns the qtype information to attach to question element.
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure() {
        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'accounting');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Chart of accounts data.
        $chart = new backup_nested_element('accounting_chart', null, ['name']);
        $chartaccounts = new backup_nested_element('chart_accounts');
        $chartaccount = new backup_nested_element(
            'chart_account',
            null,
            ['accountname', 'sortorder']
        );

        // Options with all current fields.
        $options = new backup_nested_element(
            'accounting_options',
            null,
            ['chartofaccountsid', 'accountsindropdown', 'numberformat',
             'extraentrydeduction', 'allornothinggrading',
            'allowmultipleentries',
            'maxentries']
        );

        // Entries with current weight fields and explanation.
        $entries = new backup_nested_element('accounting_entries');
        $entry = new backup_nested_element(
            'entry',
            ['id'],
            ['sortorder', 'debitaccountid', 'debitamount', 'creditaccountid', 'creditamount',
             'weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount',
            'explanation']
        );

        // Build the tree.
        $pluginwrapper->add_child($chart);
        $chart->add_child($chartaccounts);
        $chartaccounts->add_child($chartaccount);
        $pluginwrapper->add_child($options);
        $pluginwrapper->add_child($entries);
        $entries->add_child($entry);

        // Set source for chart data via SQL join through options.
        $chart->set_source_sql(
            "SELECT c.name
               FROM {qtype_accounting_charts} c
               JOIN {qtype_accounting_options} o ON o.chartofaccountsid = c.id
              WHERE o.questionid = ?",
            [backup::VAR_PARENTID]
        );

        $chartaccount->set_source_sql(
            "SELECT a.accountname, a.sortorder
               FROM {qtype_accounting_accounts} a
               JOIN {qtype_accounting_charts} c ON c.id = a.chartid
               JOIN {qtype_accounting_options} o ON o.chartofaccountsid = c.id
              WHERE o.questionid = ?
           ORDER BY a.sortorder, a.accountname",
            [backup::VAR_PARENTID]
        );

        // Set source for options and entries.
        $options->set_source_table(
            'qtype_accounting_options',
            ['questionid' => backup::VAR_PARENTID]
        );
        $entry->set_source_table(
            'qtype_accounting_entries',
            ['questionid' => backup::VAR_PARENTID],
            'sortorder ASC'
        );

        return $plugin;
    }
}
