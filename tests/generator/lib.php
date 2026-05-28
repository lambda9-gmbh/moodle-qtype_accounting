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
 * Buchungssatz question type data generator.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Buchungssatz question type data generator.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_accounting_generator extends component_generator_base {
    /**
     * Generate a default data set for a Buchungssatz question.
     *
     * @return array The default data.
     */
    public function get_default_question_data(): array {
        return [
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 0,
            'maxentries' => 5,
            'debitaccount' => ['1200 Bank'],
            'debitamount' => [1000.00],
            'creditaccount' => ['8400 Erlöse 19% USt'],
            'creditamount' => [1000.00],
            'grade' => [100],
            'explanation' => [''],
        ];
    }

    /**
     * Create a Buchungssatz question from Behat data.
     *
     * This method is called by the question generator when creating
     * questions via Behat's "the following questions exist" step.
     *
     * @param array $data The question data from Behat.
     * @return stdClass The question form data.
     */
    public function get_test_question_data(array $data): stdClass {
        $questiondata = new stdClass();

        // Set defaults.
        $questiondata->chartofaccountsid = $data['chartofaccountsid'] ?? 0;
        $questiondata->allowmultipleentries = $data['allowmultipleentries'] ?? 0;
        $questiondata->maxentries = $data['maxentries'] ?? 5;

        // Parse entries from data.
        // Entries can be specified as comma-separated values in single fields.
        $questiondata->debitaccount = $this->parse_array_field($data, 'debitaccount');
        $questiondata->debitamount = $this->parse_array_field($data, 'debitamount', true);
        $questiondata->creditaccount = $this->parse_array_field($data, 'creditaccount');
        $questiondata->creditamount = $this->parse_array_field($data, 'creditamount', true);
        $questiondata->grade = $this->parse_array_field($data, 'grade', true);
        $questiondata->explanation = $this->parse_array_field($data, 'explanation');

        return $questiondata;
    }

    /**
     * Parse a field that may contain comma-separated values into an array.
     *
     * @param array $data The data array.
     * @param string $field The field name.
     * @param bool $numeric Whether values should be cast to float.
     * @return array The parsed array.
     */
    protected function parse_array_field(array $data, string $field, bool $numeric = false): array {
        if (!isset($data[$field])) {
            return $numeric ? [0] : [''];
        }

        $value = $data[$field];

        // If already an array, return it.
        if (is_array($value)) {
            return $value;
        }

        // Split by comma if string contains commas.
        if (strpos($value, ',') !== false) {
            $values = array_map('trim', explode(',', $value));
        } else {
            $values = [$value];
        }

        if ($numeric) {
            $values = array_map('floatval', $values);
        }

        return $values;
    }
}
