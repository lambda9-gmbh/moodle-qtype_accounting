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
use qtype_accounting\currency_helper;
use qtype_accounting\entry_helper;

/**
 * The booking table in which students enter or review their entries.
 *
 * Rendered by the template qtype_accounting/entry_table. In edit mode the last row is a
 * <template> with the placeholder __INDEX__, which question.js clones for new entries.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_table implements \renderable, \templatable {
    /**
     * Create the table.
     *
     * @param \question_attempt $qa The question attempt.
     * @param bool $readonly Whether the table is shown for review.
     * @param array $response The student response.
     * @param array $accounts The accounts offered in the account selects, keyed by ID.
     * @param int $rows The number of entry rows to show.
     * @param string $templateid The DOM ID of the row template.
     * @param array $feedback Per-row feedback CSS classes keyed by field name, see feedback_calculator.
     */
    public function __construct(
        /** @var \question_attempt The question attempt. */
        protected \question_attempt $qa,
        /** @var bool Whether the table is shown for review. */
        protected bool $readonly,
        /** @var array The student response. */
        protected array $response,
        /** @var array The accounts offered in the account selects. */
        protected array $accounts,
        /** @var int The number of entry rows to show. */
        protected int $rows,
        /** @var string The DOM ID of the row template. */
        protected string $templateid,
        /** @var array Per-row feedback CSS classes. */
        protected array $feedback = []
    ) {
    }

    /**
     * Export the table for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array The template context.
     */
    public function export_for_template(\renderer_base $output): array {
        $question = $this->qa->get_question();
        $currencysymbol = currency_helper::get_symbol($question->currency ?? null);
        // The amount columns fit an input of the configured minimum width (site setting amountminwidth)
        // and, if shown, the currency symbol with its gap.
        $style = '--qtype-accounting-amount-width: ' . amount_helper::get_min_width() . 'px;';
        if ($currencysymbol !== '') {
            $style .= ' --qtype-accounting-currency-width: calc(' . \core_text::strlen($currencysymbol) . 'ch + 0.5rem);';
        }
        $rows = [];
        for ($i = 0; $i < $this->rows; $i++) {
            $rows[] = $this->export_row($i, $this->response, $this->feedback[$i] ?? []);
        }
        if (!$this->readonly) {
            // The row template is rendered like a row, inside a <template> element.
            $rows[] = ['istemplate' => true] + $this->export_row('__INDEX__', [], []);
        }
        return [
            'hideper' => (string)get_config('qtype_accounting', 'showpercolumn') === '0',
            'style' => $style,
            'editable' => !$this->readonly,
            'rows' => $rows,
            'templateid' => $this->templateid,
        ];
    }

    /**
     * Export one entry row.
     *
     * @param int|string $index The row index, or the placeholder __INDEX__ for the template row.
     * @param array $response The student response.
     * @param array $feedback Feedback CSS classes of this row keyed by field name.
     * @return array The row context.
     */
    protected function export_row($index, array $response, array $feedback): array {
        $debitaccount = (string)($response["debitaccount_{$index}"] ?? '');
        $creditaccount = (string)($response["creditaccount_{$index}"] ?? '');
        $entrytype = entry_helper::determine_entry_type($debitaccount, $creditaccount);
        $hidden = entry_helper::get_hidden_sides($entrytype);
        $row = [
            'index' => $index,
            'entrytype' => $entrytype,
            'istemplate' => false,
            'sides' => [],
        ];
        foreach (['debit', 'credit'] as $side) {
            $account = (string)($response["{$side}account_{$index}"] ?? '');
            $amount = (string)($response["{$side}amount_{$index}"] ?? '');
            $row['sides'][] = [
                'isdebit' => $side === 'debit',
                'hidden' => $hidden[$side],
                'account' => $this->export_account($side, $index, $account, $feedback[$side . 'account'] ?? ''),
                'amount' => $this->export_amount($side, $index, $amount, $feedback[$side . 'amount'] ?? ''),
            ];
        }
        return $row;
    }

    /**
     * Export an account field.
     *
     * @param string $side 'debit' or 'credit'.
     * @param int|string $index The row index.
     * @param string $value The selected account ID.
     * @param string $feedback The feedback CSS class, if any.
     * @return array The field context.
     */
    protected function export_account(string $side, $index, string $value, string $feedback): array {
        $field = $this->field($side . 'account', $index, $value, $feedback);
        if ($this->readonly) {
            $field['display'] = entry_helper::format_account_display_by_id((int)$value, $this->accounts) ?: $value;
        } else if ($this->accounts) {
            $field['options'] = entry_helper::account_options($this->accounts, $value);
        } else {
            $field['textinput'] = true;
        }
        return $field;
    }

    /**
     * Export an amount field.
     *
     * @param string $side 'debit' or 'credit'.
     * @param int|string $index The row index.
     * @param string $value The amount as entered.
     * @param string $feedback The feedback CSS class, if any.
     * @return array The field context.
     */
    protected function export_amount(string $side, $index, string $value, string $feedback): array {
        $question = $this->qa->get_question();
        $numberformat = $question->numberformat ?? 'de';
        $field = $this->field($side . 'amount', $index, $value, $feedback);
        $field['currencysymbol'] = currency_helper::get_symbol($question->currency ?? null);
        $field['placeholder'] = $numberformat === 'us' ? '0.00' : '0,00';
        if ($this->readonly) {
            $cents = amount_helper::parse_cents($value, $numberformat);
            $display = $cents === 0 ? '' : amount_helper::format_cents($cents, $numberformat);
            if ($display !== '' && $field['currencysymbol'] !== '') {
                $display .= "\u{00A0}" . $field['currencysymbol'];
            }
            $field['display'] = $display;
        }
        return $field;
    }

    /**
     * Export the parts shared by all fields.
     *
     * @param string $name The field name without index, e.g. debitaccount.
     * @param int|string $index The row index.
     * @param string $value The submitted value.
     * @param string $feedback The feedback CSS class: qtype_accounting-correct, qtype_accounting-incorrect or ''.
     * @return array The field context.
     */
    protected function field(string $name, $index, string $value, string $feedback): array {
        return [
            'name' => $this->qa->get_qt_field_name("{$name}_{$index}"),
            'value' => $value,
            'readonly' => $this->readonly,
            'feedbackclass' => $feedback,
            'correct' => $feedback === 'qtype_accounting-correct',
            'incorrect' => $feedback === 'qtype_accounting-incorrect',
        ];
    }
}
