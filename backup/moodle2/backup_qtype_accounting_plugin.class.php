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

        // The question's own accounts.
        $accounts = new backup_nested_element('accounting_accounts');
        $account = new backup_nested_element('account', null, ['accountkey', 'accountname', 'sortorder']);

        $options = new backup_nested_element(
            'accounting_options',
            null,
            ['accountsindropdown', 'numberformat', 'extraentrydeduction', 'allornothinggrading',
            'allowmultipleentries', 'maxentries', 'gradingmode', 'currency']
        );

        $entries = new backup_nested_element('accounting_entries');
        $entry = new backup_nested_element(
            'entry',
            ['id'],
            ['sortorder', 'debitaccountid', 'debitamount', 'creditaccountid', 'creditamount',
             'weight_debitaccount', 'weight_debitamount', 'weight_creditaccount', 'weight_creditamount',
            'explanation']
        );

        // The accounts come first, so restore knows them when it processes the options.
        $pluginwrapper->add_child($accounts);
        $accounts->add_child($account);
        $pluginwrapper->add_child($options);
        $pluginwrapper->add_child($entries);
        $entries->add_child($entry);

        $account->set_source_table(
            'qtype_accounting_qaccounts',
            ['questionid' => backup::VAR_PARENTID],
            'sortorder ASC'
        );
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
