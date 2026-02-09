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
 * Chart of accounts import form.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use qtype_buchungssatz\import_helper;
use qtype_buchungssatz\chart_manager;

/**
 * Form for importing a chart of accounts from CSV file.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_import_form extends moodleform {

    /**
     * Parsed CSV data from validation.
     *
     * @var array|null
     */
    private $_parsed_data = null;

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        // CSV file picker.
        $mform->addElement('filepicker', 'csvfile',
            get_string('csvfile', 'qtype_buchungssatz'), null,
            [
                'maxbytes' => 2097152, // 2MB.
                'accepted_types' => ['.csv', '.txt'],
            ]
        );
        $mform->addHelpButton('csvfile', 'csvfile', 'qtype_buchungssatz');

        // Override existing chart checkbox.
        $mform->addElement('advcheckbox', 'overrideexisting',
            get_string('overrideexisting', 'qtype_buchungssatz'),
            get_string('overrideexistingdesc', 'qtype_buchungssatz'));
        $mform->addHelpButton('overrideexisting', 'overrideexisting', 'qtype_buchungssatz');

        // Hidden context ID.
        $mform->addElement('hidden', 'contextid', $customdata['contextid']);
        $mform->setType('contextid', PARAM_INT);

        // Action buttons.
        $this->add_action_buttons(false, get_string('importchart', 'qtype_buchungssatz'));
    }

    /**
     * Validate form data.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Get CSV content from draft file area.
        $csvdata = $this->get_draft_file_content($data['csvfile']);

        // Check if file is empty.
        if (empty($csvdata)) {
            $errors['csvfile'] = get_string('csvempty', 'qtype_buchungssatz');
            return $errors;
        }

        // Parse CSV using import_helper.
        try {
            $parsed = import_helper::parse_csv($csvdata);
            // Store parsed data for reuse in processing.
            $this->_parsed_data = $parsed;
        } catch (Exception $e) {
            $errors['csvfile'] = $e->getMessage();
            return $errors;
        }

        // Check if chart with this name already exists.
        $chartname = $parsed['chartname'];
        $contextid = $data['contextid'];
        $existingchart = chart_manager::get_chart_by_name($chartname, $contextid);

        // If chart exists and override not checked, add error.
        if ($existingchart && empty($data['overrideexisting'])) {
            $errors['overrideexisting'] = get_string('chartexists_enableoverride', 'qtype_buchungssatz', $chartname);
        }

        return $errors;
    }

    /**
     * Get file content from draft file area.
     *
     * @param int $draftitemid Draft item ID from filepicker.
     * @return string File content or empty string if no file found.
     */
    private function get_draft_file_content($draftitemid) {
        if (empty($draftitemid)) {
            return '';
        }

        $fs = get_file_storage();
        $usercontext = context_user::instance($GLOBALS['USER']->id);

        // Get files from the draft area.
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);

        if (empty($files)) {
            return '';
        }

        // Get the first file.
        $file = reset($files);
        return $file->get_content();
    }

    /**
     * Get parsed CSV data from validation.
     *
     * @return array|null Parsed CSV data or null if not validated yet.
     */
    public function get_parsed_data() {
        return $this->_parsed_data;
    }

    /**
     * Get CSV file content from the submitted form data.
     *
     * @return string CSV file content.
     */
    public function get_csv_content() {
        $data = $this->get_data();
        if (empty($data) || empty($data->csvfile)) {
            return '';
        }
        return $this->get_draft_file_content($data->csvfile);
    }
}
