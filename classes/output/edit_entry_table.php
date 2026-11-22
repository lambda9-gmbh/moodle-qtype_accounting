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

namespace qtype_accounting\output;

use qtype_accounting\amount_helper;
use qtype_accounting\entry_helper;

/**
 * The table of correct entries in the question editor.
 *
 * Rendered by the template qtype_accounting/edit_entry_table. The display controls are synchronised
 * with hidden form fields by qtype_accounting/editform, which clones the last row, a <template> with
 * the placeholder __INDEX__, for new entries.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_entry_table implements \renderable, \templatable {
    /**
     * Create the table.
     *
     * @param array $debitoptions Options of the debit account selects, keyed by account ID.
     * @param array $creditoptions Options of the credit account selects, keyed by account ID.
     * @param int $entrycount Number of rows to show.
     * @param array $entries Saved or submitted entries, indexed 0..N-1.
     * @param string $numberformat 'de' or 'us'.
     */
    public function __construct(
        /** @var array Options of the debit account selects. */
        protected array $debitoptions,
        /** @var array Options of the credit account selects. */
        protected array $creditoptions,
        /** @var int Number of rows to show. */
        protected int $entrycount,
        /** @var array Saved or submitted entries. */
        protected array $entries,
        /** @var string 'de' or 'us'. */
        protected string $numberformat = 'de'
    ) {
    }

    /**
     * Export the table for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array The template context.
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = [];
        for ($i = 0; $i < $this->entrycount; $i++) {
            $rows[] = $this->export_row($i, $this->entries[$i] ?? null);
        }
        // The row template is rendered like a row, inside a <template> element.
        $rows[] = ['istemplate' => true] + $this->export_row('__INDEX__', null);
        return [
            'hideper' => (string)get_config('qtype_accounting', 'showpercolumn') === '0',
            'rows' => $rows,
            'weighttooltip' => nl2br(s(get_string('weight_tooltip', 'qtype_accounting'))),
        ];
    }

    /**
     * Export one entry: its data row and its weight row.
     *
     * @param int|string $index The row index, or the placeholder __INDEX__ for the row template.
     * @param object|null $entry The entry, if any.
     * @return array The row context.
     */
    protected function export_row($index, ?object $entry): array {
        $entrytype = entry_helper::determine_entry_type($entry->debitaccountid ?? '', $entry->creditaccountid ?? '');
        $hidden = entry_helper::get_hidden_sides($entrytype);
        $row = [
            'index' => $index,
            'entrytype' => $entrytype,
            'istemplate' => false,
            'placeholder' => $this->numberformat === 'us' ? '0.00' : '0,00',
            'sides' => [],
        ];
        foreach (['debit', 'credit'] as $side) {
            $error = (string)($entry->{$side . 'amounterror'} ?? '');
            $row['sides'][] = [
                'isdebit' => $side === 'debit',
                'hidden' => $hidden[$side],
                'options' => entry_helper::account_options(
                    $side === 'debit' ? $this->debitoptions : $this->creditoptions,
                    (string)($entry->{$side . 'accountid'} ?? '')
                ),
                'amount' => $this->format_amount($entry, $side),
                'side' => $side,
                'accountlabel' => get_string($side . 'account', 'qtype_accounting'),
                'amountlabel' => get_string($side . 'amount', 'qtype_accounting'),
                'amountid' => 'id_' . $side . 'amount_display_' . $index,
                'error' => $error,
                'weights' => [
                    // The first row explains the weights next to its first weight select.
                    $this->export_weight($side . 'account', (int)($entry->{'weight_' . $side . 'account'} ?? 1))
                        + ['tooltip' => $index === 0 && $side === 'debit'],
                    $this->export_weight($side . 'amount', (int)($entry->{'weight_' . $side . 'amount'} ?? 1)),
                ],
            ];
        }
        return $row;
    }

    /**
     * Export a weight select.
     *
     * @param string $field debitaccount, debitamount, creditaccount or creditamount.
     * @param int $weight The selected weight; values outside 1 to 3 select 1.
     * @return array The select context.
     */
    protected function export_weight(string $field, int $weight): array {
        $weight = ($weight >= 1 && $weight <= 3) ? $weight : 1;
        return [
            'field' => $field,
            'label' => get_string('weight', 'qtype_accounting') . ': ' . get_string($field, 'qtype_accounting'),
            'options' => array_map(fn($value) => ['value' => $value, 'selected' => $value === $weight], [1, 2, 3]),
        ];
    }

    /**
     * Format an entry amount for its display control; the absent side of a saved one-sided entry stays empty.
     *
     * @param object|null $entry The entry, if any.
     * @param string $side 'debit' or 'credit'.
     * @return string The amount for the display control.
     */
    protected function format_amount(?object $entry, string $side): string {
        $amount = $entry->{$side . 'amount'} ?? '';
        $accountid = $entry->{$side . 'accountid'} ?? 0;
        // Saved one-sided entries store zero for the absent side.
        if ($amount === '' || (!$accountid && amount_helper::parse_cents((string)$amount, 'us') === 0)) {
            return '';
        }
        return amount_helper::format_amount($amount, $this->numberformat);
    }
}
