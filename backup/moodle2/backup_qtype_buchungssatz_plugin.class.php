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
 * Backup routines for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup Buchungssatz questions.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_buchungssatz_plugin extends backup_qtype_plugin {
    /**
     * Returns the qtype information to attach to question element.
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure() {
        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'buchungssatz');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Chart of accounts data.
        $chart = new backup_nested_element('buchungssatz_chart', null, ['name']);
        $chartaccounts = new backup_nested_element('chart_accounts');
        $chartaccount = new backup_nested_element(
            'chart_account',
            null,
            ['accountname', 'sortorder']
        );

        // Options with all current fields.
        $options = new backup_nested_element(
            'buchungssatz_options',
            null,
            ['chartofaccountsid', 'accountsindropdown', 'numberformat',
             'extraentrydeduction', 'allornothinggrading',
            'allowmultipleentries',
            'maxentries']
        );

        // Entries with current weight fields and explanation.
        $entries = new backup_nested_element('buchungssatz_entries');
        $entry = new backup_nested_element(
            'entry',
            ['id'],
            ['sortorder', 'sollkontoid', 'sollbetrag', 'habenkontoid', 'habenbetrag',
             'weight_sollkonto', 'weight_sollbetrag', 'weight_habenkonto', 'weight_habenbetrag',
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
               FROM {qtype_buchungssatz_charts} c
               JOIN {qtype_buchungssatz_options} o ON o.chartofaccountsid = c.id
              WHERE o.questionid = ?",
            [backup::VAR_PARENTID]
        );

        $chartaccount->set_source_sql(
            "SELECT a.accountname, a.sortorder
               FROM {qtype_buchungssatz_accounts} a
               JOIN {qtype_buchungssatz_charts} c ON c.id = a.chartid
               JOIN {qtype_buchungssatz_options} o ON o.chartofaccountsid = c.id
              WHERE o.questionid = ?
           ORDER BY a.sortorder, a.accountname",
            [backup::VAR_PARENTID]
        );

        // Set source for options and entries.
        $options->set_source_table(
            'qtype_buchungssatz_options',
            ['questionid' => backup::VAR_PARENTID]
        );
        $entry->set_source_table(
            'qtype_buchungssatz_entries',
            ['questionid' => backup::VAR_PARENTID],
            'sortorder ASC'
        );

        return $plugin;
    }
}
