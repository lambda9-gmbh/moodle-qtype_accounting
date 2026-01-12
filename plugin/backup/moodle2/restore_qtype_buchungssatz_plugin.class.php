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
 * Restore routines for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore plugin class that provides the necessary information to restore Buchungssatz questions.
 */
class restore_qtype_buchungssatz_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level.
     *
     * @return array
     */
    protected function define_question_plugin_structure() {
        $paths = [];

        // Add own qtype paths.
        $elename = 'buchungssatz_options';
        $elepath = $this->get_pathfor('/buchungssatz_options');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'buchungssatz_entry';
        $elepath = $this->get_pathfor('/buchungssatz_entries/entry');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths;
    }

    /**
     * Process the qtype_buchungssatz_options element.
     *
     * @param array $data
     */
    public function process_buchungssatz_options($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id ?? 0;

        // Detect if the question is created or mapped.
        $questioncreated = $this->get_mappingid('question_created',
            $this->get_old_parentid('question')) ? true : false;

        // If the question has been created by restore, insert the options.
        if ($questioncreated) {
            $data->questionid = $this->get_new_parentid('question');
            // Chart of accounts will need to be remapped if available.
            // For now, set to 0 as the chart might not exist in the target system.
            $data->chartofaccountsid = 0;
            $DB->insert_record('qtype_buchungssatz_options', $data);
        }
    }

    /**
     * Process the qtype_buchungssatz_entries entry element.
     *
     * @param array $data
     */
    public function process_buchungssatz_entry($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = $this->get_mappingid('question_created',
            $this->get_old_parentid('question')) ? true : false;

        // If the question has been created by restore, insert the entries.
        if ($questioncreated) {
            $data->questionid = $this->get_new_parentid('question');
            unset($data->id);
            $DB->insert_record('qtype_buchungssatz_entries', $data);
        }
    }
}
