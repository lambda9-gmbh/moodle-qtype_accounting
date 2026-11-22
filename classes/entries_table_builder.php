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
 * Entries-table renderer used by the Buchungssatz edit form.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Reads the entries submitted by the question editor and renders its entries table.
 *
 * The table itself is the renderable {@see output\edit_entry_table}.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entries_table_builder {
    /**
     * Convert submitted hidden fields to the entries used by the custom table.
     *
     * Amounts in hidden fields have already been normalised by JavaScript.
     *
     * @param array $data Submitted form values.
     * @param array $errors Validation errors keyed by the original submitted field names.
     * @return array Entries in submitted order, omitting deleted or unused rows.
     */
    public function get_submitted_entries(array $data, array $errors = []): array {
        $indices = array_unique(array_merge(
            array_keys($data['debitaccount'] ?? []),
            array_keys($data['creditaccount'] ?? [])
        ));
        $entries = [];
        foreach ($indices as $index) {
            $entry = new \stdClass();
            foreach (['debit', 'credit'] as $side) {
                $entry->{$side . 'accountid'} = (int)($data[$side . 'account'][$index] ?? 0);
                $entry->{$side . 'amount'} = $data[$side . 'amount'][$index] ?? '';
                $entry->{$side . 'amounterror'} = $errors[$side . 'amount[' . $index . ']'] ?? '';
                foreach (['account', 'amount'] as $field) {
                    $name = 'weight_' . $side . $field;
                    $entry->$name = (int)($data[$name][$index] ?? 1);
                }
            }
            if (
                $entry->debitaccountid || $entry->creditaccountid ||
                $entry->debitamount !== '' || $entry->creditamount !== ''
            ) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /**
     * Render the entries table of the question editor.
     *
     * @param array $debitaccountoptions Options for debit account select.
     * @param array $creditaccountoptions Options for credit account select.
     * @param int $entrycount Number of pre-rendered entry rows.
     * @param array $existingentries Pre-loaded entry data, indexed 0..N-1.
     * @param string $numberformat 'de' or 'us'.
     * @return string The HTML for the table and its row template.
     */
    public function build_table(
        array $debitaccountoptions,
        array $creditaccountoptions,
        int $entrycount,
        array $existingentries,
        string $numberformat = 'de'
    ): string {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $table = new output\edit_entry_table(
            $debitaccountoptions,
            $creditaccountoptions,
            $entrycount,
            $existingentries,
            $numberformat
        );
        return $output->render_from_template('qtype_accounting/edit_entry_table', $table->export_for_template($output));
    }
}
