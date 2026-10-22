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
     * Both amount columns take their width from the site setting amountminwidth.
     */
    public function test_amount_column_width_follows_setting(): void {
        $this->resetAfterTest();
        $question = $this->make_simple_question();
        $this->start_attempt_at_question($question, 'deferredfeedback', 1);

        $this->render();
        $this->assertEquals(2, substr_count($this->currentoutput, '<col style="width: calc(175px + 1rem + 1px);">'));

        set_config('amountminwidth', 220, 'qtype_accounting');
        $this->render();
        $this->assertEquals(2, substr_count($this->currentoutput, '<col style="width: calc(220px + 1rem + 1px);">'));

        set_config('amountminwidth', 0, 'qtype_accounting');
        $this->render();
        $this->assertEquals(2, substr_count($this->currentoutput, '<col style="width: calc(175px + 1rem + 1px);">'));
    }
}
