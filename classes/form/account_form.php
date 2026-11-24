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

use qtype_accounting\account_manager;
use qtype_accounting\chart_manager;
use qtype_accounting\local\chart_page;

/**
 * Modal form that adds an account to a chart of accounts template.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account_form extends \core_form\dynamic_form {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'chartid');
        $mform->setType('chartid', PARAM_INT);
        $mform->addElement('text', 'accountname', get_string('accountname', 'qtype_accounting'), ['size' => 40]);
        $mform->setType('accountname', PARAM_TEXT);
        $mform->addRule('accountname', null, 'required', null, 'client');
        $mform->addRule('accountname', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    }

    /**
     * Reject blank and duplicate names.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by field name.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        $name = trim($data['accountname'] ?? '');
        if ($name === '') {
            $errors['accountname'] = get_string('required');
        } else if (\core_text::strlen($name) > 255) {
            $errors['accountname'] = get_string('maximumchars', '', 255);
        } else if ($DB->record_exists('qtype_accounting_accounts', ['chartid' => (int)$data['chartid'], 'accountname' => $name])) {
            $errors['accountname'] = get_string('err_duplicateaccount', 'qtype_accounting');
        }
        return $errors;
    }

    /**
     * Get the context of the templates.
     *
     * @return \context The system context.
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Check that the user may manage templates and that the chart is a template.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/site:config', \context_system::instance());
        chart_page::get_template($this->optional_param('chartid', 0, PARAM_INT));
    }

    /**
     * Add the account at the end of the chart.
     *
     * @return array The new account ID.
     */
    public function process_dynamic_submission() {
        global $DB;
        $data = $this->get_data();
        $chartid = (int)$data->chartid;
        $sortorder = (int)$DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {qtype_accounting_accounts} WHERE chartid = ?',
            [$chartid]
        );
        $accountid = account_manager::add($chartid, trim($data->accountname), $sortorder + 1);
        chart_manager::accounts_changed($chartid);
        return ['id' => $accountid];
    }

    /**
     * Set the chart ID passed to the modal.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(['chartid' => $this->optional_param('chartid', 0, PARAM_INT)]);
    }

    /**
     * Get the page of the chart, used when the form is shown without JavaScript.
     *
     * @return \moodle_url The URL.
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/question/type/accounting/edit_chart.php', [
            'chartid' => $this->optional_param('chartid', 0, PARAM_INT),
        ]);
    }
}
