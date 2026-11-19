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
require_once($CFG->dirroot . '/question/type/accounting/tests/helper.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting_renderer::class)]
/**
 * Rendering honours Moodle's review visibility settings.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting_renderer
 */
final class renderer_test extends \advanced_testcase {
    /**
     * Field colours depend on correctness visibility independently of specific feedback.
     */
    public function test_review_colours_respect_correctness_visibility(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = \context_system::instance();
        $PAGE->set_context($context);
        $PAGE->set_url('/question/preview.php');
        $question = \test_question_maker::make_question('accounting', 'simple_debit_credit');
        $question->contextid = $context->id;
        $usage = \question_engine::make_questions_usage_by_activity('core_question_preview', $context);
        $usage->set_preferred_behaviour('deferredfeedback');
        $slot = $usage->add_question($question);
        $usage->start_all_questions();
        $response = $question->get_correct_response();
        $response['debitamount_0'] = '12,50';
        $usage->process_action($slot, $response);
        $usage->finish_all_questions();
        $qa = $usage->get_question_attempt($slot);
        $renderer = $PAGE->get_renderer('qtype_accounting');

        foreach ([false, true] as $readonly) {
            foreach ([\question_display_options::HIDDEN, \question_display_options::VISIBLE] as $correctness) {
                foreach ([\question_display_options::HIDDEN, \question_display_options::VISIBLE] as $feedback) {
                    $options = new \question_display_options();
                    $options->readonly = $readonly;
                    $options->correctness = $correctness;
                    $options->feedback = $feedback;
                    $options->rightanswer = \question_display_options::HIDDEN;
                    $html = $renderer->formulation_and_controls($qa, $options);
                    if ($readonly && $correctness) {
                        $this->assertStringContainsString('qtype_accounting-correct', $html);
                        $this->assertStringContainsString('qtype_accounting-incorrect', $html);
                    } else {
                        $this->assertStringNotContainsString('qtype_accounting-correct', $html);
                        $this->assertStringNotContainsString('qtype_accounting-incorrect', $html);
                    }
                }
            }
        }
    }
}
