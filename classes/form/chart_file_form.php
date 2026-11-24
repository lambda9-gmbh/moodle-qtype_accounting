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

namespace qtype_accounting\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for uploading a CSV, text or ODS file with account names.
 *
 * Used to create a chart from a file and to import accounts into an existing chart.
 * The custom data 'submitlabel' sets the label of the submit button.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_file_form extends \moodleform {
    /** @var int Maximum upload size in bytes. */
    public const MAX_BYTES = 2097152;

    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'filepicker',
            'chartcsvfile',
            get_string('csvfile', 'qtype_accounting'),
            null,
            ['maxbytes' => self::MAX_BYTES, 'accepted_types' => ['.csv', '.txt', '.ods']]
        );
        $mform->addHelpButton('chartcsvfile', 'csvfile', 'qtype_accounting');

        $this->add_action_buttons(false, $this->_customdata['submitlabel']);
    }

    /**
     * Get the uploaded file.
     *
     * @return \stored_file|null The file, or null if the draft area is empty.
     */
    public function get_uploaded_file(): ?\stored_file {
        global $USER;
        $data = $this->get_data();
        if (!$data) {
            return null;
        }
        $files = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $data->chartcsvfile,
            'id DESC',
            false
        );
        return $files ? reset($files) : null;
    }
}
