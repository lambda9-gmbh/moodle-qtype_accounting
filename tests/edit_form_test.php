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
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/accounting/edit_accounting_form.php');

/**
 * Tests for the accounting editing form's account data.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting_edit_form::get_all_accounts_by_chart
 * @covers     \qtype_accounting_edit_form::validate_available_accounts
 * @covers     \qtype_accounting_edit_form::definition_after_data
 * @covers     \qtype_accounting_edit_form::add_display_options_section
 * @covers     \qtype_accounting\entry_validator
 * @covers     \qtype_accounting\entries_table_builder
 */
final class edit_form_test extends \advanced_testcase {
    /**
     * Missing amounts must produce separate errors for each affected control.
     */
    public function test_missing_amount_errors_are_visible(): void {
        $errors = (new entry_validator())->validate([
            'debitaccount' => [0 => '12', 3 => '13'],
            'creditaccount' => [0 => '14'],
            'debitamount' => [0 => '', 3 => ''],
            'creditamount' => [0 => ''],
        ], []);
        $this->assertSame(get_string('err_debitamountrequired', 'qtype_accounting'), $errors['debitamount[0]']);
        $this->assertSame(get_string('err_creditamountrequired', 'qtype_accounting'), $errors['creditamount[0]']);
        $this->assertArrayNotHasKey('balancevalidation', $errors);
    }

    /**
     * Valid split entries use normalised amounts even when displayed in German format.
     */
    public function test_balanced_split_entries_are_valid(): void {
        $this->assertSame([], (new entry_validator())->validate([
            'numberformat' => 'de',
            'debitaccount' => ['12', '13'],
            'creditaccount' => ['14', ''],
            'debitamount' => ['10.50', '0.50'],
            'creditamount' => ['11', ''],
        ], []));
    }

    /**
     * Rendering saved one-sided entries must not add an amount on the absent side.
     */
    public function test_saved_one_sided_entry_keeps_absent_amount_empty(): void {
        $html = (new entries_table_builder())->build_table(
            ['' => 'Select', 12 => 'Bank'],
            ['' => 'Select', 14 => 'Cash'],
            1,
            [(object)['debitaccountid' => 12, 'debitamount' => '12.5000', 'creditaccountid' => 0, 'creditamount' => '0.0000']],
            'de'
        );
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame('12,50', $xpath->evaluate('string(//input[@name="debitamount_display[0]"]/@value)'));
        $this->assertSame('', $xpath->evaluate('string(//input[@name="creditamount_display[0]"]/@value)'));
    }

    /**
     * A failed submission restores the chart, sparse rows, amounts, and weights.
     *
     * @dataProvider failed_submission_provider
     * @param string $format Submitted display format.
     * @param bool $existing Whether the form starts with saved options and entries.
     */
    public function test_failed_submission_preserves_custom_controls(string $format, bool $existing): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);
        $chartid = chart_manager::create_chart('Main', $context->id);
        $bank = account_manager::add($chartid, 'Bank');
        $cash = account_manager::add($chartid, 'Cash');
        $submitted = [
            'category' => $category->id . ',' . $context->id,
            'name' => 'Missing amounts',
            'questiontext' => ['text' => 'Record the transaction.', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'chartofaccountsid' => $chartid,
            'numberformat' => $format,
            'debitaccount' => [0 => $bank, 1 => '', 4 => $bank, 6 => $cash],
            'creditaccount' => [0 => $cash, 1 => '', 4 => '', 6 => $bank],
            'debitamount' => [0 => '', 1 => '', 4 => '12.50', 6 => '0'],
            'creditamount' => [0 => '', 1 => '', 4 => '', 6 => '0'],
            'weight_debitaccount' => [0 => 3, 4 => 2],
        ];
        \qtype_accounting_edit_form::mock_submit($submitted);
        $question = (object)[
            'qtype' => 'accounting',
            'contextid' => $context->id,
            'createdby' => $USER->id,
            'category' => $category->id,
            'questiontext' => '',
            'generalfeedback' => '',
            'formoptions' => (object)['mustbeusable' => false, 'repeatelements' => true],
        ];
        if ($existing) {
            $otherchartid = chart_manager::create_chart('Previous chart', $context->id);
            $otheraccountid = account_manager::add($otherchartid, 'Previous account');
            $question->options = (object)[
                'chartofaccountsid' => $otherchartid,
                'numberformat' => $format === 'de' ? 'us' : 'de',
                'entries' => [(object)[
                    'debitaccountid' => $otheraccountid,
                    'creditaccountid' => $otheraccountid,
                    'debitamount' => 100,
                    'creditamount' => 100,
                ]],
            ];
        }
        $form = new \qtype_accounting_edit_form(
            new \moodle_url('/question/bank/editquestion/question.php'),
            $question,
            $category,
            new \core_question\local\bank\question_edit_contexts($context)
        );
        $this->assertFalse($form->is_validated());
        $html = $form->render();
        $this->assertStringContainsString(get_string('err_debitamountrequired', 'qtype_accounting'), $html);
        $this->assertStringContainsString(get_string('err_creditamountrequired', 'qtype_accounting'), $html);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach (['debit', 'credit'] as $side) {
            $input = $xpath->query('//input[@name="' . $side . 'amount_display[0]"]')->item(0);
            $this->assertStringContainsString('is-invalid', $input->getAttribute('class'));
            $this->assertSame('true', $input->getAttribute('aria-invalid'));
            $feedbackid = $input->getAttribute('aria-describedby');
            $feedback = $xpath->query('//div[@id="' . $feedbackid . '"]')->item(0);
            $this->assertNotNull($feedback);
            $this->assertStringContainsString('form-control-feedback invalid-feedback', $feedback->getAttribute('class'));
            $this->assertSame(get_string('err_' . $side . 'amountrequired', 'qtype_accounting'), $feedback->textContent);
        }
        $this->assertSame('Main', $xpath->evaluate('string(//select[@name="chartofaccountsid"]/option[@selected])'));
        $this->assertSame('Bank', $xpath->evaluate('string(//select[@name="debitaccount_display[0]"]/option[@selected])'));
        $this->assertSame('Cash', $xpath->evaluate('string(//select[@name="creditaccount_display[0]"]/option[@selected])'));
        $this->assertSame('', $xpath->evaluate('string(//input[@name="debitamount_display[0]"]/@value)'));
        $this->assertSame('3', $xpath->evaluate('string(//select[@name="weight_debitaccount_display[0]"]/option[@selected])'));
        $this->assertSame('Bank', $xpath->evaluate('string(//select[@name="debitaccount_display[1]"]/option[@selected])'));
        $amount = $format === 'de' ? '12,50' : '12.50';
        $this->assertSame($amount, $xpath->evaluate('string(//input[@name="debitamount_display[1]"]/@value)'));
        $this->assertSame('2', $xpath->evaluate('string(//select[@name="weight_debitaccount_display[1]"]/option[@selected])'));
        $zero = $format === 'de' ? '0,00' : '0.00';
        $this->assertSame($zero, $xpath->evaluate('string(//input[@name="debitamount_display[2]"]/@value)'));
        $this->assertSame($zero, $xpath->evaluate('string(//input[@name="creditamount_display[2]"]/@value)'));
        $rows = $xpath->query('//tbody[@id="qtype_accounting-entries-body"]/tr[contains(@class, "entry-row")]');
        $this->assertSame(3, $rows->length);
    }

    /**
     * The site setting showaccountsindropdown hides the option but keeps a saved value.
     */
    public function test_show_accounts_in_dropdown_setting(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        if (\core_component::get_component_directory('mod_qbank') === null) {
            $bankcontext = \context_course::instance($course->id);
        } else {
            $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
            $bankcontext = \context_module::instance($qbank->cmid);
        }
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $bankcontext->id]);
        $chartid = chart_manager::create_chart('Main', \context_course::instance($course->id)->id);
        $render = function () use ($bankcontext, $category, $chartid, $USER): \DOMXPath {
            $question = (object)[
                'qtype' => 'accounting',
                'contextid' => $bankcontext->id,
                'createdby' => $USER->id,
                'category' => $category->id,
                'questiontext' => '',
                'generalfeedback' => '',
                'formoptions' => (object)['mustbeusable' => false, 'repeatelements' => true],
                'options' => (object)[
                    'chartofaccountsid' => $chartid,
                    'accountsindropdown' => 3,
                    'numberformat' => 'de',
                    'entries' => [],
                ],
            ];
            $form = new \qtype_accounting_edit_form(
                new \moodle_url('/question/bank/editquestion/question.php'),
                $question,
                $category,
                new \core_question\local\bank\question_edit_contexts($bankcontext)
            );
            $form->set_data($question);
            $dom = new \DOMDocument();
            @$dom->loadHTML($form->render());
            return new \DOMXPath($dom);
        };

        $xpath = $render();
        $this->assertSame('text', $xpath->evaluate('string(//input[@name="accountsindropdown"]/@type)'));
        $this->assertSame('3', $xpath->evaluate('string(//input[@name="accountsindropdown"]/@value)'));

        set_config('showaccountsindropdown', 0, 'qtype_accounting');
        $xpath = $render();
        $this->assertSame('hidden', $xpath->evaluate('string(//input[@name="accountsindropdown"]/@type)'));
        $this->assertSame('3', $xpath->evaluate('string(//input[@name="accountsindropdown"]/@value)'));
        $this->assertStringNotContainsString(
            get_string('accountsindropdown', 'qtype_accounting'),
            $xpath->document->saveHTML()
        );
    }

    /**
     * Cover new and previously populated forms in both display formats.
     *
     * @return array Submission variants.
     */
    public static function failed_submission_provider(): array {
        return [
            'new German' => ['de', false],
            'new US' => ['us', false],
            'existing German' => ['de', true],
            'existing US' => ['us', true],
        ];
    }

    /**
     * Reject stale chart IDs and accounts that no longer belong to the selected chart.
     */
    public function test_stale_account_validation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $chartid = chart_manager::create_chart('Main', $context->id);
        $accountid = account_manager::add($chartid, 'Bank');
        $otherchartid = chart_manager::create_chart('Other', $context->id);
        $otheraccountid = account_manager::add($otherchartid, 'Cash');
        $form = $this->getMockBuilder(\qtype_accounting_edit_form::class)
            ->disableOriginalConstructor()->onlyMethods([])->getMock();
        $form->context = $context;
        $method = new \ReflectionMethod(\qtype_accounting_edit_form::class, 'validate_available_accounts');
        $method->setAccessible(true);
        $data = ['chartofaccountsid' => $chartid, 'debitaccount' => [$accountid]];
        $this->assertSame([], $method->invoke($form, $data, []));
        $data['creditaccount'] = [$otheraccountid];
        $this->assertArrayHasKey('balancevalidation', $method->invoke($form, $data, []));
        unset($data['creditaccount']);
        account_manager::delete($accountid);
        $this->assertArrayHasKey('balancevalidation', $method->invoke($form, $data, []));
        chart_manager::delete_chart($chartid);
        $this->assertArrayHasKey('chartofaccountsid', $method->invoke($form, $data, []));
    }

    /**
     * Read the account data without constructing the unrelated form UI.
     *
     * @param \context $context The form context.
     * @return array Accounts keyed by chart and account IDs.
     */
    private function get_accounts(\context $context): array {
        $form = $this->getMockBuilder(\qtype_accounting_edit_form::class)
            ->disableOriginalConstructor()->onlyMethods([])->getMock();
        $form->context = $context;
        $method = new \ReflectionMethod(\qtype_accounting_edit_form::class, 'get_all_accounts_by_chart');
        $method->setAccessible(true);
        return $method->invoke($form);
    }

    /**
     * Preserve chart order, account-name order, empty charts, IDs, and course isolation.
     */
    public function test_accounts_grouped_and_sorted_in_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $zchart = chart_manager::create_chart('Zulu', $context->id);
        $emptychart = chart_manager::create_chart('Empty', $context->id);
        $achart = chart_manager::create_chart('Alpha', $context->id);
        $zaccount = account_manager::add($achart, 'Zulu account', 0);
        $aaccount = account_manager::add($achart, 'Alpha account', 1);
        $otheraccount = account_manager::add($zchart, 'Alpha account', 0);
        $outsidechart = chart_manager::create_chart('Outside', \context_system::instance()->id);
        account_manager::add($outsidechart, 'Hidden account', 0);

        $this->assertSame([
            $achart => [$aaccount => 'Alpha account', $zaccount => 'Zulu account'],
            $emptychart => [],
            $zchart => [$otheraccount => 'Alpha account'],
        ], $this->get_accounts($context));
    }

    /**
     * No course charts or a context without a course must produce an empty result.
     */
    public function test_no_available_charts(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertSame([], $this->get_accounts(\context_course::instance($course->id)));
        $chartid = chart_manager::create_chart('System chart', \context_system::instance()->id);
        account_manager::add($chartid, 'Bank');
        $this->assertSame([], $this->get_accounts(\context_system::instance()));
    }

    /**
     * Loading accounts must use one chart read and one account read, regardless of chart count.
     */
    public function test_accounts_use_bounded_reads(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        for ($i = 0; $i < 10; $i++) {
            $chartid = chart_manager::create_chart('Chart ' . $i, $context->id);
            account_manager::add($chartid, 'Bank');
        }
        $before = $DB->perf_get_reads();
        $accounts = $this->get_accounts($context);
        $reads = $DB->perf_get_reads() - $before;
        $this->assertCount(10, $accounts);
        $this->assertEquals(2, $reads);
    }
}
