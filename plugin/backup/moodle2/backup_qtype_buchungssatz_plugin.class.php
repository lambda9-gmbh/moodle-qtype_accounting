<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Backup routines for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to backup Buchungssatz questions.
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

        // Define each element.
        $options = new backup_nested_element('buchungssatz_options',
            null, ['chartofaccountsid', 'allowmultipleentries', 'maxentries']);

        $entries = new backup_nested_element('buchungssatz_entries');
        $entry = new backup_nested_element('entry', ['id'],
            ['sortorder', 'sollkonto', 'sollbetrag', 'habenkonto', 'habenbetrag', 'fraction']);

        // Build the tree.
        $pluginwrapper->add_child($options);
        $pluginwrapper->add_child($entries);
        $entries->add_child($entry);

        // Set source to populate the data.
        $options->set_source_table('qtype_buchungssatz_options',
            ['questionid' => backup::VAR_PARENTID]);
        $entry->set_source_table('qtype_buchungssatz_entries',
            ['questionid' => backup::VAR_PARENTID], 'sortorder ASC');

        // Don't need to annotate ids nor files.
        return $plugin;
    }
}
