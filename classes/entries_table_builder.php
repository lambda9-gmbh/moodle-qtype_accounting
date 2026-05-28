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
 * Builds the editable entries table (account/amount/weight rows + JS row template).
 *
 * Extracted from {@see \qtype_accounting_edit_form} so the edit form class can stay
 * focused on the form definition + persistence. The class is stateless and reads only
 * its arguments.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entries_table_builder {
    /**
     * Build the entries table HTML (header + body + footer controls + clone template).
     *
     * @param array $debitaccountoptions Options for debit account select.
     * @param array $creditaccountoptions Options for credit account select.
     * @param int $entrycount Number of pre-rendered entry rows.
     * @param array $existingentries Pre-loaded entry data, indexed 0..N-1.
     * @param string $numberformat 'de' or 'us'.
     * @return string The HTML for the table.
     */
    public function build_table(
        array $debitaccountoptions,
        array $creditaccountoptions,
        int $entrycount,
        array $existingentries,
        string $numberformat = 'de'
    ): string {
        $html = '<table class="table table-bordered qtype_accounting-edit-table" id="qtype_accounting-entries-table">';
        $html .= $this->build_thead();
        $html .= '<tbody id="qtype_accounting-entries-body">';
        for ($i = 0; $i < $entrycount; $i++) {
            $entry = $existingentries[$i] ?? null;
            $html .= $this->build_entry_rows(
                $i,
                $debitaccountoptions,
                $creditaccountoptions,
                $entry,
                $i === 0,
                $numberformat
            );
        }
        $html .= '</tbody>';
        $html .= $this->build_footer();
        $html .= '</table>';

        // Template for new rows (hidden, used by JavaScript).
        $html .= '<template id="qtype_accounting-entry-template">';
        $html .= $this->build_entry_rows(
            '__INDEX__',
            $debitaccountoptions,
            $creditaccountoptions,
            null,
            false,
            $numberformat
        );
        $html .= '</template>';
        return $html;
    }

    /**
     * Render the two-row thead.
     *
     * @return string The HTML for thead.
     */
    protected function build_thead(): string {
        $perstr = get_string('per', 'qtype_accounting');
        $anstr = get_string('an', 'qtype_accounting');
        $debitstr = get_string('debit', 'qtype_accounting');
        $creditstr = get_string('credit', 'qtype_accounting');
        $accountstr = get_string('account', 'qtype_accounting');
        $amountstr = get_string('amount', 'qtype_accounting');
        $html = '<thead>';
        $html .= '<tr>';
        $html .= '<th class="qtype_accounting-edit-label"></th>';
        $html .= '<th colspan="2" class="text-center">' . $debitstr . '</th>';
        $html .= '<th class="qtype_accounting-edit-label"></th>';
        $html .= '<th colspan="2" class="text-center">' . $creditstr . '</th>';
        $html .= '<th class="qtype_accounting-edit-actions"></th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th class="qtype_accounting-edit-label">' . $perstr . '</th>';
        $html .= '<th>' . $accountstr . '</th>';
        $html .= '<th>' . $amountstr . '</th>';
        $html .= '<th class="qtype_accounting-edit-label">' . $anstr . '</th>';
        $html .= '<th>' . $accountstr . '</th>';
        $html .= '<th>' . $amountstr . '</th>';
        $html .= '<th></th>';
        $html .= '</tr>';
        $html .= '</thead>';
        return $html;
    }

    /**
     * Render the table footer with the "Add debit" / "Add credit" buttons.
     *
     * @return string The HTML for tfoot.
     */
    protected function build_footer(): string {
        $adddebitentrystr = get_string('adddebitentry', 'qtype_accounting');
        $addcreditentrystr = get_string('addcreditentry', 'qtype_accounting');
        $html = '<tfoot class="qtype_accounting-controls-footer">';
        $html .= '<tr>';
        $html .= '<td></td>'; // Per label column.
        $html .= '<td><button type="button" class="btn btn-secondary btn-sm qtype_accounting-add-debit-entry"'
            . ' id="qtype_accounting-add-debit-entry">+ ' . $adddebitentrystr . '</button></td>';
        $html .= '<td></td>'; // Debit Amount column.
        $html .= '<td></td>'; // The 'an' label column.
        $html .= '<td><button type="button" class="btn btn-secondary btn-sm qtype_accounting-add-credit-entry"'
            . ' id="qtype_accounting-add-credit-entry">+ ' . $addcreditentrystr . '</button></td>';
        $html .= '<td></td>'; // Credit Amount column.
        $html .= '<td></td>'; // Actions column.
        $html .= '</tr>';
        $html .= '</tfoot>';
        return $html;
    }

    /**
     * Build the HTML for one entry: data row + weight row.
     *
     * @param int|string $index Entry index or placeholder.
     * @param array $debitaccountoptions Options for debit account select.
     * @param array $creditaccountoptions Options for credit account select.
     * @param object|null $entry Existing entry data.
     * @param bool $isfirst Whether this is the first entry (gets the weight tooltip).
     * @param string $numberformat 'de' or 'us'.
     * @return string The HTML for both rows.
     */
    protected function build_entry_rows(
        $index,
        array $debitaccountoptions,
        array $creditaccountoptions,
        $entry,
        bool $isfirst,
        string $numberformat
    ): string {
        $debitaccount = $entry->debitaccountid ?? '';
        $creditaccount = $entry->creditaccountid ?? '';
        $entrytype = entry_helper::determine_entry_type($debitaccount, $creditaccount);
        $hiddenclasses = entry_helper::get_hidden_classes($entrytype);
        return $this->build_data_row(
            $index,
            $debitaccountoptions,
            $creditaccountoptions,
            $entry,
            $entrytype,
            $hiddenclasses,
            $numberformat
        ) . $this->build_weight_row($index, $entry, $isfirst, $hiddenclasses);
    }

    /**
     * Build the data row (account dropdowns, amount inputs, delete buttons).
     *
     * @param int|string $index Entry row index.
     * @param array $debitaccountoptions Options for debit account select.
     * @param array $creditaccountoptions Options for credit account select.
     * @param object|null $entry Existing entry data.
     * @param string $entrytype 'both', 'debit', or 'credit'.
     * @param array $hiddenclasses Per-side hidden classes.
     * @param string $numberformat 'de' or 'us'.
     * @return string The HTML for the data row.
     */
    protected function build_data_row(
        $index,
        array $debitaccountoptions,
        array $creditaccountoptions,
        $entry,
        string $entrytype,
        array $hiddenclasses,
        string $numberformat
    ): string {
        $debithidden = $hiddenclasses['debit'];
        $credithidden = $hiddenclasses['credit'];
        $debitaccount = $entry->debitaccountid ?? '';
        $creditaccount = $entry->creditaccountid ?? '';
        $debitamount = $this->format_amount((float)($entry->debitamount ?? 0), $numberformat);
        $creditamount = $this->format_amount((float)($entry->creditamount ?? 0), $numberformat);
        $placeholder = ($numberformat === 'us') ? '0.00' : '0,00';

        $debitselecthtml = '<select name="debitaccount_display[' . $index . ']"'
            . ' class="form-control qtype_accounting-debitaccount" data-index="' . $index . '">';
        $debitselecthtml .= entry_helper::build_account_options($debitaccountoptions, (string)$debitaccount, '');
        $debitselecthtml .= '</select>';
        $creditselecthtml = '<select name="creditaccount_display[' . $index . ']"'
            . ' class="form-control qtype_accounting-creditaccount" data-index="' . $index . '">';
        $creditselecthtml .= entry_helper::build_account_options($creditaccountoptions, (string)$creditaccount, '');
        $creditselecthtml .= '</select>';

        $html = '<tr class="qtype_accounting-entry-row" data-entry-index="'
            . $index . '" data-entry-type="' . $entrytype . '">';
        $html .= '<td class="qtype_accounting-edit-label' . $debithidden . '"></td>';
        $html .= '<td class="qtype_accounting-edit-data' . $debithidden . '">' . $debitselecthtml . '</td>';
        $html .= '<td class="qtype_accounting-edit-data' . $debithidden . '">';
        $html .= '<input type="text" name="debitamount_display[' . $index . ']" value="' . s($debitamount) . '" ';
        $html .= 'class="form-control qtype_accounting-debitamount" data-index="' . $index
            . '" placeholder="' . $placeholder . '">';
        $html .= '</td>';
        $html .= '<td class="qtype_accounting-edit-label' . $debithidden . '">';
        $html .= entry_helper::render_delete_button('debit', $index, 'data-index');
        $html .= '</td>';
        $html .= '<td class="qtype_accounting-edit-data' . $credithidden . '">' . $creditselecthtml . '</td>';
        $html .= '<td class="qtype_accounting-edit-data' . $credithidden . '">';
        $html .= '<input type="text" name="creditamount_display[' . $index . ']" value="' . s($creditamount) . '" ';
        $html .= 'class="form-control qtype_accounting-creditamount" data-index="' . $index
            . '" placeholder="' . $placeholder . '">';
        $html .= '</td>';
        $html .= '<td class="qtype_accounting-edit-actions' . $credithidden . '">';
        $html .= entry_helper::render_delete_button('credit', $index, 'data-index');
        $html .= '</td>';
        $html .= '</tr>';
        return $html;
    }

    /**
     * Build the weight row (4 weight selects + tooltip on the first row).
     *
     * @param int|string $index Entry row index.
     * @param object|null $entry Existing entry data.
     * @param bool $isfirst True for the first entry row.
     * @param array $hiddenclasses Per-side hidden classes.
     * @return string The HTML for the weight row.
     */
    protected function build_weight_row($index, $entry, bool $isfirst, array $hiddenclasses): string {
        $debithidden = $hiddenclasses['debit'];
        $credithidden = $hiddenclasses['credit'];
        $weightstr = get_string('weight', 'qtype_accounting');
        $weights = [
            'debitaccount' => (int)($entry->weight_debitaccount ?? 1),
            'debitamount' => (int)($entry->weight_debitamount ?? 1),
            'creditaccount' => (int)($entry->weight_creditaccount ?? 1),
            'creditamount' => (int)($entry->weight_creditamount ?? 1),
        ];

        $html = '<tr class="qtype_accounting-weight-row" data-entry-index="' . $index . '">';
        $html .= '<td class="' . trim($debithidden) . '"></td>';
        $html .= '<td class="qtype_accounting-weight-cell' . $debithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_debitaccount_display[' . $index . ']',
            $weights['debitaccount'],
            $index,
            'debitaccount'
        );
        if ($isfirst) {
            $html .= $this->build_weight_tooltip($weightstr);
        }
        $html .= '</td>';
        $html .= '<td class="qtype_accounting-weight-cell' . $debithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_debitamount_display[' . $index . ']',
            $weights['debitamount'],
            $index,
            'debitamount'
        );
        $html .= '</td>';
        $html .= '<td class="' . trim($debithidden) . '"></td>';
        $html .= '<td class="qtype_accounting-weight-cell' . $credithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_creditaccount_display[' . $index . ']',
            $weights['creditaccount'],
            $index,
            'creditaccount'
        );
        $html .= '</td>';
        $html .= '<td class="qtype_accounting-weight-cell' . $credithidden . '">';
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_creditamount_display[' . $index . ']',
            $weights['creditamount'],
            $index,
            'creditamount'
        );
        $html .= '</td>';
        $html .= '<td class="' . trim($credithidden) . '"></td>';
        $html .= '</tr>';
        return $html;
    }

    /**
     * Build the Bootstrap popover tooltip rendered next to the weight selector on the first row.
     *
     * @param string $weightstr The localised "Weight" label, used as the icon's title.
     * @return string The HTML for the tooltip anchor + icon.
     */
    protected function build_weight_tooltip(string $weightstr): string {
        $tooltiphtml = nl2br(s(get_string('weight_tooltip', 'qtype_accounting')));
        $html = ' <a class="btn btn-link p-0" role="button" data-container="body" ';
        $html .= 'data-toggle="popover" data-placement="right" data-trigger="click" ';
        $html .= 'data-html="true" data-content="' . s($tooltiphtml) . '" tabindex="0">';
        $html .= '<i class="icon fa fa-question-circle text-info fa-fw" ';
        $html .= 'title="' . s($weightstr) . '"></i></a>';
        return $html;
    }

    /**
     * Build a weight select dropdown with options 1, 2, and 3.
     *
     * @param string $name The form field name.
     * @param int $selectedvalue The currently selected value (1, 2, or 3).
     * @param int|string $index The entry index.
     * @param string $field The field name (debitaccount, debitamount, creditaccount, creditamount).
     * @return string The HTML for the select dropdown.
     */
    protected function build_weight_select(string $name, int $selectedvalue, $index, string $field): string {
        if ($selectedvalue < 1 || $selectedvalue > 3) {
            $selectedvalue = 1;
        }
        $html = '<select name="' . $name . '" class="form-control qtype_accounting-weight" ';
        $html .= 'data-index="' . $index . '" data-field="' . $field . '" style="width: auto; display: inline-block;">';
        for ($i = 1; $i <= 3; $i++) {
            $selected = ($i === $selectedvalue) ? ' selected' : '';
            $html .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * Format a float amount for display inside the edit form.
     *
     * @param float $amount The amount value.
     * @param string $numberformat 'de' or 'us'.
     * @return string The formatted amount (empty for values close to zero).
     */
    protected function format_amount(float $amount, string $numberformat = 'de'): string {
        if (abs($amount) < 0.001) {
            return '';
        }
        if ($numberformat === 'us') {
            return number_format($amount, 2, '.', ',');
        }
        return number_format($amount, 2, ',', '.');
    }
}
