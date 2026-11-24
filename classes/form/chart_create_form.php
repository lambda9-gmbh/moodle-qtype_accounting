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
 * Form for creating a chart of accounts without a CSV file.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_create_form extends \moodleform {
    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'chartname', get_string('chartname', 'qtype_accounting'), ['maxlength' => 255]);
        $mform->setType('chartname', PARAM_TEXT);
        $mform->addRule('chartname', null, 'required', null, 'client');
        $mform->addRule('chartname', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');
        $mform->addHelpButton('chartname', 'createchart', 'qtype_accounting');

        $this->add_action_buttons(false, get_string('createchart', 'qtype_accounting'));
    }

    /**
     * Validate the chart name, including whitespace-only submissions.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['chartname']) === '') {
            $errors['chartname'] = get_string('required');
        }
        return $errors;
    }
}
