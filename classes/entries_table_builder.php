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
        $hideper = (string)get_config('qtype_accounting', 'showpercolumn') === '0' ? ' qtype_accounting-hideper' : '';
        $html = \html_writer::start_tag(
            'table',
            [
                'class' => 'table table-bordered qtype_accounting-edit-table' . $hideper,
                'id' => 'qtype_accounting-entries-table',
            ]
        );
        $html .= $this->build_thead();
        $html .= \html_writer::start_tag('tbody', ['id' => 'qtype_accounting-entries-body']);
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
        $html .= \html_writer::end_tag('tbody');
        $html .= $this->build_footer();
        $html .= \html_writer::end_tag('table');

        // Template for new rows (hidden, used by JavaScript).
        $html .= \html_writer::start_tag('template', ['id' => 'qtype_accounting-entry-template']);
        $html .= $this->build_entry_rows(
            '__INDEX__',
            $debitaccountoptions,
            $creditaccountoptions,
            null,
            false,
            $numberformat
        );
        $html .= \html_writer::end_tag('template');
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
        $html = \html_writer::start_tag('thead');
        $html .= \html_writer::start_tag('tr');
        $html .= \html_writer::start_tag('th', ['class' => 'qtype_accounting-edit-label']) . \html_writer::end_tag('th');
        $html .= \html_writer::tag('th', $debitstr, ['colspan' => '2', 'class' => 'text-center']);
        $html .= \html_writer::start_tag('th', ['class' => 'qtype_accounting-edit-label']) . \html_writer::end_tag('th');
        $html .= \html_writer::tag('th', $creditstr, ['colspan' => '2', 'class' => 'text-center']);
        $html .= \html_writer::start_tag('th', ['class' => 'qtype_accounting-edit-actions']) . \html_writer::end_tag('th');
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::start_tag('tr');
        $html .= \html_writer::tag('th', $perstr, ['class' => 'qtype_accounting-edit-label']);
        $html .= \html_writer::tag('th', $accountstr);
        $html .= \html_writer::tag('th', $amountstr);
        $html .= \html_writer::tag('th', $anstr, ['class' => 'qtype_accounting-edit-label']);
        $html .= \html_writer::tag('th', $accountstr);
        $html .= \html_writer::tag('th', $amountstr);
        $html .= \html_writer::start_tag('th') . \html_writer::end_tag('th');
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::end_tag('thead');
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
        $html = \html_writer::start_tag('tfoot', ['class' => 'qtype_accounting-controls-footer']);
        $html .= \html_writer::start_tag('tr');
        $html .= \html_writer::start_tag('td') . \html_writer::end_tag('td'); // Per label column.
        $html .= \html_writer::tag('td', \html_writer::tag('button', '+ ' . $adddebitentrystr, [
            'type' => 'button', 'class' => 'btn btn-secondary btn-sm qtype_accounting-add-debit-entry',
            'id' => 'qtype_accounting-add-debit-entry',
        ]));
        $html .= \html_writer::start_tag('td') . \html_writer::end_tag('td'); // Debit Amount column.
        $html .= \html_writer::start_tag('td') . \html_writer::end_tag('td'); // The 'an' label column.
        $html .= \html_writer::tag('td', \html_writer::tag('button', '+ ' . $addcreditentrystr, [
            'type' => 'button', 'class' => 'btn btn-secondary btn-sm qtype_accounting-add-credit-entry',
            'id' => 'qtype_accounting-add-credit-entry',
        ]));
        $html .= \html_writer::start_tag('td') . \html_writer::end_tag('td'); // Credit Amount column.
        $html .= \html_writer::start_tag('td') . \html_writer::end_tag('td'); // Actions column.
        $html .= \html_writer::end_tag('tr');
        $html .= \html_writer::end_tag('tfoot');
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
        $debitamount = $this->format_entry_amount($entry, 'debit', $numberformat);
        $creditamount = $this->format_entry_amount($entry, 'credit', $numberformat);
        $placeholder = ($numberformat === 'us') ? '0.00' : '0,00';

        $debitselecthtml = \html_writer::tag(
            'select',
            entry_helper::build_account_options($debitaccountoptions, (string)$debitaccount, ''),
            ['name' => 'debitaccount_display[' . $index . ']', 'data-index' => $index,
                'class' => 'form-control qtype_accounting-debitaccount',
                'aria-label' => get_string('debitaccount', 'qtype_accounting')]
        );
        $creditselecthtml = \html_writer::tag(
            'select',
            entry_helper::build_account_options($creditaccountoptions, (string)$creditaccount, ''),
            ['name' => 'creditaccount_display[' . $index . ']', 'data-index' => $index,
                'class' => 'form-control qtype_accounting-creditaccount',
                'aria-label' => get_string('creditaccount', 'qtype_accounting')]
        );

        $html = \html_writer::start_tag('tr', [
            'class' => 'qtype_accounting-entry-row', 'data-entry-index' => $index, 'data-entry-type' => $entrytype,
        ]);
        $html .= \html_writer::tag('td', '', ['class' => 'qtype_accounting-edit-label' . $debithidden]);
        $html .= \html_writer::tag('td', $debitselecthtml, ['class' => 'qtype_accounting-edit-data' . $debithidden]);
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-edit-data' . $debithidden]);
        $html .= $this->build_amount_input($index, 'debit', $debitamount, $placeholder, $entry->debitamounterror ?? '');
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-edit-label' . $debithidden]);
        $html .= entry_helper::render_delete_button('debit', $index, 'data-index');
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::tag('td', $creditselecthtml, ['class' => 'qtype_accounting-edit-data' . $credithidden]);
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-edit-data' . $credithidden]);
        $html .= $this->build_amount_input($index, 'credit', $creditamount, $placeholder, $entry->creditamounterror ?? '');
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-edit-actions' . $credithidden]);
        $html .= entry_helper::render_delete_button('credit', $index, 'data-index');
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::end_tag('tr');
        return $html;
    }

    /**
     * Render an amount control with the same feedback markup as Moodle text elements.
     *
     * @param int|string $index Row index.
     * @param string $side Debit or credit.
     * @param string $amount Display amount.
     * @param string $placeholder Display format hint.
     * @param string $error Field validation message.
     * @return string Input and inline feedback.
     */
    protected function build_amount_input($index, string $side, string $amount, string $placeholder, string $error): string {
        $id = 'id_' . $side . 'amount_display_' . $index;
        $attributes = [
            'type' => 'text',
            'name' => $side . 'amount_display[' . $index . ']',
            'id' => $id,
            'value' => $amount,
            'class' => 'form-control qtype_accounting-' . $side . 'amount' . ($error !== '' ? ' is-invalid' : ''),
            'data-index' => $index,
            'placeholder' => $placeholder,
            'aria-label' => get_string($side . 'amount', 'qtype_accounting'),
        ];
        if ($error !== '') {
            $attributes['aria-invalid'] = 'true';
            $attributes['aria-describedby'] = $id . '_error';
        }
        $html = \html_writer::empty_tag('input', $attributes);
        if ($error !== '') {
            $html .= \html_writer::div(
                s($error),
                'qtype_accounting-account-error form-control-feedback invalid-feedback d-block',
                ['id' => $id . '_error']
            );
        }
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

        $html = \html_writer::start_tag('tr', ['class' => 'qtype_accounting-weight-row', 'data-entry-index' => $index]);
        $html .= \html_writer::tag('td', '', ['class' => '' . trim($debithidden)]);
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-weight-cell' . $debithidden]);
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_debitaccount_display[' . $index . ']',
            $weights['debitaccount'],
            $index,
            'debitaccount'
        );
        if ($isfirst) {
            $html .= $this->build_weight_tooltip($weightstr);
        }
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-weight-cell' . $debithidden]);
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_debitamount_display[' . $index . ']',
            $weights['debitamount'],
            $index,
            'debitamount'
        );
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::tag('td', '', ['class' => '' . trim($debithidden)]);
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-weight-cell' . $credithidden]);
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_creditaccount_display[' . $index . ']',
            $weights['creditaccount'],
            $index,
            'creditaccount'
        );
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::start_tag('td', ['class' => 'qtype_accounting-weight-cell' . $credithidden]);
        $html .= $weightstr . ': ' . $this->build_weight_select(
            'weight_creditamount_display[' . $index . ']',
            $weights['creditamount'],
            $index,
            'creditamount'
        );
        $html .= \html_writer::end_tag('td');
        $html .= \html_writer::tag('td', '', ['class' => '' . trim($credithidden)]);
        $html .= \html_writer::end_tag('tr');
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
        return ' ' . \html_writer::tag(
            'a',
            \html_writer::tag('i', '', ['class' => 'icon fa fa-question-circle text-info fa-fw', 'title' => $weightstr]),
            [
                'class' => 'btn btn-link p-0', 'role' => 'button', 'data-container' => 'body',
                'data-toggle' => 'popover', 'data-placement' => 'right', 'data-trigger' => 'click',
                'data-html' => 'true', 'data-content' => $tooltiphtml, 'tabindex' => '0',
            ]
        );
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
        $options = '';
        for ($i = 1; $i <= 3; $i++) {
            $options .= \html_writer::tag('option', (string)$i, [
                'value' => $i, 'selected' => $i === $selectedvalue ? 'selected' : null,
            ]);
        }
        return \html_writer::tag('select', $options, [
            'name' => $name, 'class' => 'form-control qtype_accounting-weight',
            'data-index' => $index, 'data-field' => $field,
            'aria-label' => get_string('weight', 'qtype_accounting') . ': ' . get_string($field, 'qtype_accounting'),
        ]);
    }

    /**
     * Preserve missing amounts and keep the absent side of saved entries empty.
     *
     * @param object|null $entry Existing or submitted entry.
     * @param string $side Debit or credit.
     * @param string $numberformat The display format.
     * @return string The amount for the display control.
     */
    protected function format_entry_amount(?object $entry, string $side, string $numberformat): string {
        $amount = $entry->{$side . 'amount'} ?? '';
        $accountid = $entry->{$side . 'accountid'} ?? 0;
        // Saved one-sided entries store zero for the absent side.
        if ($amount === '' || (!$accountid && amount_helper::parse_cents((string)$amount, 'us') === 0)) {
            return '';
        }
        return $this->format_amount($amount, $numberformat);
    }

    /**
     * Format a decimal amount for display inside the edit form.
     *
     * @param string|int|float $amount Canonical decimal amount.
     * @param string $numberformat 'de' or 'us'.
     * @return string The formatted amount.
     */
    protected function format_amount($amount, string $numberformat = 'de'): string {
        return amount_helper::format_amount($amount, $numberformat);
    }
}
