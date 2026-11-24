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
 * Buchungssatz question renderer class.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generates the output for Buchungssatz questions.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_accounting_renderer extends qtype_renderer {
    /** @var \qtype_accounting\feedback_calculator Lazily-constructed feedback calculator. */
    protected $feedbackcalculator;

    /**
     * Get the feedback calculator instance, constructing it on first use.
     *
     * @return \qtype_accounting\feedback_calculator
     */
    protected function feedback_calculator(): \qtype_accounting\feedback_calculator {
        if ($this->feedbackcalculator === null) {
            $this->feedbackcalculator = new \qtype_accounting\feedback_calculator();
        }
        return $this->feedbackcalculator;
    }

    /**
     * Generate the display of the formulation part of the question.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options The display options.
     * @return string The HTML output.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $containerid = 'qtype_accounting-container-' . $qa->get_slot();
        $templateid = 'qtype_accounting-entry-template-' . $qa->get_slot();
        $visiblerows = $this->count_visible_rows($response);
        $accounts = (new \qtype_accounting\account_provider())
            ->build_filtered_accounts($question, $response, (bool)$options->readonly);
        $feedback = $options->readonly && $options->correctness
            ? $this->feedback_calculator()->build_cell_feedback_map($question, $response)
            : [];
        $table = new \qtype_accounting\output\entry_table(
            $qa,
            (bool)$options->readonly,
            $response,
            $accounts,
            $visiblerows,
            $templateid,
            $feedback
        );

        $this->register_js_strings_and_module($containerid);
        // Data attributes configure qtype_accounting/question (avoiding js_call_amd size limits) and the Moodle App.
        return $this->render_from_template('qtype_accounting/formulation', [
            'questiontext' => $question->format_questiontext($qa),
            'allornothing' => !empty($question->allornothinggrading),
            'containerid' => $containerid,
            'accountsjson' => json_encode(array_values($accounts)),
            'allowedit' => $options->readonly ? 0 : 1,
            'numberformat' => $question->numberformat ?? 'de',
            'currencysymbol' => \qtype_accounting\currency_helper::get_symbol($question->currency ?? null),
            'nextindex' => $visiblerows,
            'maxrows' => count($question->get_expected_data()) / 4,
            'templateid' => $templateid,
            'table' => $table->export_for_template($this),
        ]);
    }

    /**
     * Determine how many entry rows to render based on populated response keys.
     *
     * @param array $response The student response data.
     * @return int At least 1; one more than the highest populated index found in the response.
     */
    protected function count_visible_rows(array $response): int {
        $visiblerows = 1;
        foreach (array_keys($response) as $key) {
            if (preg_match('/^(?:debitaccount|creditaccount)_(\d+)$/', $key, $matches)) {
                $idx = (int)$matches[1];
                if (!empty($response[$key]) && $idx >= $visiblerows) {
                    $visiblerows = $idx + 1;
                }
            }
        }
        return $visiblerows;
    }

    /**
     * Register the JS language strings the student-side AMD module needs and load the module.
     *
     * @param string $containerid The DOM id of the question container.
     */
    protected function register_js_strings_and_module(string $containerid): void {
        $strings = [
            'selectaccount', 'debitentries', 'creditentries', 'adddebitentry', 'addcreditentry',
            'account', 'amount', 'debit', 'credit', 'deletedebit', 'deletecredit',
        ];
        foreach ($strings as $key) {
            $this->page->requires->string_for_js($key, 'qtype_accounting');
        }
        $this->page->requires->js_call_amd('qtype_accounting/question', 'init', [$containerid]);
    }

    /**
     * Generate the specific feedback for the question.
     *
     * Compares the student's response against the correct answer using aggregation
     * and shows whether debit/credit sides are correct, partial, or incorrect.
     *
     * @param question_attempt $qa The question attempt object.
     * @return string The HTML output.
     */
    public function specific_feedback(question_attempt $qa): string {
        $feedback = $this->feedback_calculator()->calculate_aggregated_feedback(
            $qa->get_question(),
            $qa->get_last_qt_data()
        );
        return $this->render_from_template(
            'qtype_accounting/feedback_summary',
            (new \qtype_accounting\output\feedback_summary($feedback))->export_for_template($this)
        );
    }

    /**
     * Generate the correct response for display.
     *
     * @param question_attempt $qa The question attempt object.
     * @return string The HTML output.
     */
    public function correct_response(question_attempt $qa): string {
        $question = $qa->get_question();
        if (empty($question->entries)) {
            return '';
        }
        return $this->render_from_template(
            'qtype_accounting/answer_table',
            (new \qtype_accounting\output\answer_table($question, $question->accounts))->export_for_template($this)
        );
    }
}
