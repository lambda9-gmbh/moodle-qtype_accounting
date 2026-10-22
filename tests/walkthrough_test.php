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

namespace qtype_accounting;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/accounting/tests/helper.php');

/**
 * Walkthrough tests for the rendered accounting question.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting_renderer
 * @covers     \qtype_accounting\answer_renderer
 */
final class walkthrough_test extends \qbehaviour_walkthrough_test_base {
    /**
     * Make the simple test question with the version info that the question renderer needs.
     *
     * @return \qtype_accounting_question
     */
    protected function make_simple_question(): \qtype_accounting_question {
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $question->version = 1;
        return $question;
    }

    /**
     * The question's currency symbol follows every amount input, including the row template.
     */
    public function test_currency_symbol_after_amount_inputs(): void {
        $this->resetAfterTest();
        $question = $this->make_simple_question();
        $question->currency = 'USD';
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);
        $this->render();
        // One debit and one credit input in the visible row and in the template.
        $this->assertEquals(4, substr_count($this->currentoutput, '<span class="qtype_accounting-currency" aria-hidden="true">$</span>'));
        $this->assertStringContainsString('data-currencysymbol="$"', $this->currentoutput);
    }

    /**
     * Without a currency symbol the amount inputs are rendered without the wrapper.
     */
    public function test_no_currency_symbol(): void {
        $this->resetAfterTest();
        $question = $this->make_simple_question();
        $question->currency = currency_helper::NONE;
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);
        $this->render();
        $this->assertStringNotContainsString('qtype_accounting-currency', $this->currentoutput);
        $this->assertStringNotContainsString('qtype_accounting-amount-group', $this->currentoutput);
    }

    /**
     * An empty currency uses the site default; the review shows the symbol after the student's amounts.
     */
    public function test_review_shows_site_default_symbol(): void {
        $this->resetAfterTest();
        set_config('defaultcurrency', 'GBP', 'qtype_accounting');
        $question = $this->make_simple_question();
        $question->currency = null;
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);
        $this->process_submission(\qtype_accounting_test_helper::make_response(
            \qtype_accounting_test_helper::ACCOUNT_BANK,
            1000,
            \qtype_accounting_test_helper::ACCOUNT_ERLOESE,
            1000
        ));
        $this->finish();
        $this->displayoptions->rightanswer = \question_display_options::VISIBLE;
        $this->render();
        // Student amounts (debit and credit) and the correct answer (debit and credit).
        $this->assertEquals(4, substr_count($this->currentoutput, "1.000,00\u{00A0}£"));
        $this->assertStringNotContainsString('qtype_accounting-amount-group', $this->currentoutput);
    }
}
