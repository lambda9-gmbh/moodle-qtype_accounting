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

#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'get_all_accounts_by_chart')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'add_chart_section')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'validate_available_accounts')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'definition_after_data')]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting\entry_validator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting\entries_table_builder::class)]
/**
 * Tests for the accounting editing form's account data.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting_edit_form::get_all_accounts_by_chart
 * @covers     \qtype_accounting_edit_form::add_chart_section
 * @covers     \qtype_accounting_edit_form::validate_available_accounts
 * @covers     \qtype_accounting_edit_form::definition_after_data
 * @covers     \qtype_accounting\entry_validator
 * @covers     \qtype_accounting\entries_table_builder
 */
final class edit_form_test extends \advanced_testcase {
    /**
     * Balance validation sums cents and detects a one-cent difference.
     */
    public function test_balance_uses_integer_cents(): void {
        $validator = new entry_validator();
        $data = [
            'debitaccount' => [101, 101], 'debitamount' => ['0.10', '0.20'],
            'creditaccount' => [201], 'creditamount' => ['0.30'],
        ];
        $this->assertSame([], $validator->validate($data, []));
        $data['creditamount'][0] = '0.31';
        $this->assertArrayHasKey('balancevalidation', $validator->validate($data, []));
        $data['creditamount'][0] = '0.304';
        $this->assertSame([], $validator->validate($data, []));
        $data['creditamount'][0] = '0.305';
        $this->assertArrayHasKey('balancevalidation', $validator->validate($data, []));
    }

    /**
     * The translated label is displayed for zero and round-trips as a numeric option.
     *
     * @dataProvider account_count_provider
     * @param string $lang Form language.
     * @param int $savedcount Existing stored count.
     * @param string|null $input Submitted count, or null for the current language's all label.
     * @param int|null $expected Expected stored count, or null for invalid input.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('account_count_provider')]
    public function test_account_count_label(string $lang, int $savedcount, ?string $input, ?int $expected): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        force_current_language($lang);
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $questioncontext = \context::instance_by_id($category->contextid);
        $chartid = chart_manager::create_chart('Main', $context->id);
        $account = account_manager::add($chartid, 'Bank');
        $saved = $generator->create_question('accounting', null, [
            'category' => $category->id, 'chartofaccountsid' => $chartid,
            'accountsindropdown' => $savedcount,
            'debitaccount' => [$account], 'creditaccount' => [$account],
            'debitamount' => [100], 'creditamount' => [100],
        ]);
        $question = \question_bank::load_question_data($saved->id);
        $question->contextid = $questioncontext->id;
        $question->beingcopied = false;
        $question->formoptions = (object)[
            'mustbeusable' => false, 'repeatelements' => true,
            'canedit' => true, 'canmove' => false, 'cansaveasnew' => false,
        ];
        $url = new \moodle_url('/question/bank/editquestion/question.php');
        $contexts = new \core_question\local\bank\question_edit_contexts($questioncontext);
        $form = new \qtype_accounting_edit_form($url, $question, $category, $contexts);
        $form->set_data(clone $question);
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(
            get_string('all'),
            $xpath->evaluate('string(//input[@name="accountsindropdown"]/@placeholder)')
        );
        $this->assertSame(
            $savedcount === 0 ? get_string('all') : (string)$savedcount,
            $xpath->evaluate('string(//input[@name="accountsindropdown"]/@value)')
        );

        \qtype_accounting_edit_form::mock_submit([
            'category' => $category->id . ',' . $questioncontext->id,
            'name' => 'Account count',
            'questiontext' => ['text' => 'Record the transaction.', 'format' => FORMAT_HTML],
            'defaultmark' => 1, 'penalty' => 0.3333333,
            'chartofaccountsid' => $chartid, 'numberformat' => 'de',
            'accountsindropdown' => $input ?? get_string('all'),
            'accounting_js' => 1,
            'debitaccount' => [$account], 'creditaccount' => [$account],
            'debitamount' => [100], 'creditamount' => [100],
        ]);
        $form = new \qtype_accounting_edit_form($url, $question, $category, $contexts);
        $form->set_data(clone $question);
        if ($expected === 0) {
            $this->assertSame(get_string('all'), $form->get_submitted_data()->accountsindropdown);
        }
        $data = $form->get_data();
        if ($expected === null) {
            $this->assertEmpty($data);
            $this->assertFalse($form->is_validated());
            return;
        }
        $this->assertNotFalse($data);
        $this->assertSame($expected, $data->accountsindropdown);
        $data->id = $saved->id;
        $data->context = $context;
        \question_bank::get_qtype('accounting')->save_question_options($data);
        $this->assertEquals($expected, $DB->get_field('qtype_accounting_options', 'accountsindropdown', [
            'questionid' => $saved->id,
        ]));
    }

    /**
     * Cover translated labels, existing numeric values and invalid input.
     *
     * @return array Account-count cases.
     */
    public static function account_count_provider(): array {
        return [
            'English all' => ['en', 0, null, 0],
            'German all' => ['de', 0, null, 0],
            'numeric limit' => ['en', 3, '5', 5],
            'limit to all' => ['de', 3, null, 0],
            'legacy zero' => ['en', 0, '0', 0],
            'blank' => ['en', 0, '', 0],
            'whitespace' => ['de', 0, '   ', 0],
            'padded zero' => ['de', 0, ' 00 ', 0],
            'lowercase label' => ['en', 0, 'all', 0],
            'negative' => ['en', 0, '-1', null],
            'invalid text' => ['en', 0, 'invalid', null],
            'fraction' => ['en', 0, '1.5', null],
        ];
    }

    /**
     * Opening an existing question warns immediately if its chart has been deleted.
     *
     * @dataProvider chart_state_provider
     * @param string $state Whether the question has no chart, an existing chart, or a deleted chart.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('chart_state_provider')]
    public function test_opening_question_with_deleted_chart(string $state): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $questioncontext = \context::instance_by_id($category->contextid);
        $question = (object)[
            'qtype' => 'accounting',
            'contextid' => $questioncontext->id,
            'createdby' => $USER->id,
            'category' => $category->id,
            'questiontext' => '',
            'generalfeedback' => '',
            'beingcopied' => false,
            'formoptions' => (object)[
                'mustbeusable' => false, 'repeatelements' => true,
                'canedit' => true, 'canmove' => false, 'cansaveasnew' => false,
            ],
        ];
        if ($state !== 'none') {
            $chartid = chart_manager::create_chart('Main', $context->id);
            $bank = account_manager::add($chartid, 'Bank');
            $cash = account_manager::add($chartid, 'Cash');
            $saved = $generator->create_question('accounting', null, [
                'category' => $category->id, 'chartofaccountsid' => $chartid,
                'debitaccount' => [$bank], 'creditaccount' => [$cash],
                'debitamount' => [100], 'creditamount' => [100],
            ]);
            if ($state === 'deleted') {
                chart_manager::delete_chart($chartid);
            }
            $question = (object)array_merge((array)$question, (array)\question_bank::load_question_data($saved->id));
        }
        $form = new \qtype_accounting_edit_form(
            new \moodle_url('/question/bank/editquestion/question.php'),
            $question,
            $category,
            new \core_question\local\bank\question_edit_contexts($questioncontext)
        );
        $html = $form->render();
        $message = get_string('err_chartdeleted', 'qtype_accounting');
        if ($state === 'deleted') {
            $this->assertStringContainsString($message, $html);
            $this->assertStringContainsString('role="alert"', $html);
            $this->assertStringContainsString('name="chartofaccountsid"', $html);
            $this->assertStringContainsString('100,00', $html);
        } else {
            $this->assertStringNotContainsString($message, $html);
        }
    }

    /**
     * Cover new questions, intact charts, and deleted charts.
     *
     * @return array Chart states.
     */
    public static function chart_state_provider(): array {
        return [['none'], ['existing'], ['deleted']];
    }

    /**
     * Deleted saved rows must not be filled back in from question defaults on submit.
     *
     * @dataProvider deleted_row_provider
     * @param int $remainingindex Index of the row retained in the editor.
     * @param bool $javascript Submit canonical or no-JavaScript display fields.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deleted_row_provider')]
    public function test_deleted_saved_row_stays_deleted(int $remainingindex, bool $javascript): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $questioncontext = \context::instance_by_id($category->contextid);
        $chartid = chart_manager::create_chart('Main', $context->id);
        $bank = account_manager::add($chartid, 'Bank');
        $cash = account_manager::add($chartid, 'Cash');
        $saved = $generator->create_question('accounting', null, [
            'category' => $category->id,
            'chartofaccountsid' => $chartid,
            'debitaccount' => [$bank, $bank], 'creditaccount' => [$cash, $cash],
            'debitamount' => [100, 200], 'creditamount' => [100, 200],
        ]);
        $question = \question_bank::load_question_data($saved->id);
        $question->contextid = $questioncontext->id;
        $question->beingcopied = false;
        $question->formoptions = (object)[
            'mustbeusable' => false, 'repeatelements' => true,
            'canedit' => true, 'canmove' => false, 'cansaveasnew' => false,
        ];
        $amount = $remainingindex === 0 ? 100 : 200;
        $submission = [
            'category' => $category->id . ',' . $questioncontext->id,
            'name' => 'Deleted row',
            'questiontext' => ['text' => 'Record the transaction.', 'format' => FORMAT_HTML],
            'defaultmark' => 1, 'penalty' => 0.3333333,
            'chartofaccountsid' => $chartid, 'numberformat' => 'de',
            'accounting_js' => (int)$javascript, 'accounting_displayformat' => 'de',
        ];
        foreach (['debit' => $bank, 'credit' => $cash] as $side => $account) {
            $suffix = $javascript ? '' : '_display';
            $submission[$side . 'account' . $suffix] = [$remainingindex => $account];
            $submission[$side . 'amount' . $suffix] = [$remainingindex => $javascript ? $amount : $amount . ',00'];
        }
        \qtype_accounting_edit_form::mock_submit($submission);
        $form = new \qtype_accounting_edit_form(
            new \moodle_url('/question/bank/editquestion/question.php'),
            $question,
            $category,
            new \core_question\local\bank\question_edit_contexts($questioncontext)
        );
        // The real editor supplies saved defaults after constructing the submitted form.
        $form->set_data(clone $question);
        $data = $form->get_data();
        $this->assertNotFalse($data);
        foreach (['debit', 'credit'] as $side) {
            $this->assertEmpty($data->{$side . 'account'}[1 - $remainingindex] ?? '');
            $this->assertEmpty($data->{$side . 'amount'}[1 - $remainingindex] ?? '');
        }
        $data->id = $saved->id;
        $data->context = $context;
        \question_bank::get_qtype('accounting')->save_question_options($data);
        $entries = $DB->get_records('qtype_accounting_entries', ['questionid' => $saved->id]);
        $this->assertCount(1, $entries);
        $entry = reset($entries);
        $this->assertEquals($amount, $entry->debitamount);
        $this->assertEquals($amount, $entry->creditamount);
    }

    /**
     * Cover deletion of either saved row with and without JavaScript.
     *
     * @return array Submission variants.
     */
    public static function deleted_row_provider(): array {
        return [[0, true], [1, true], [0, false], [1, false]];
    }

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
     * @param bool $javascript Whether canonical fields or ordinary display controls are submitted.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('failed_submission_provider')]
    public function test_failed_submission_preserves_custom_controls(
        string $format,
        bool $existing,
        bool $javascript = true
    ): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);
        $questioncontext = \context::instance_by_id($category->contextid);
        $chartid = chart_manager::create_chart('Main', $context->id);
        $bank = account_manager::add($chartid, 'Bank');
        $cash = account_manager::add($chartid, 'Cash');
        $submitted = [
            'category' => $category->id . ',' . $questioncontext->id,
            'name' => 'Missing amounts',
            'accountsindropdown' => $existing ? '0' : '',
            'questiontext' => ['text' => 'Record the transaction.', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'chartofaccountsid' => $chartid,
            'numberformat' => $format,
            'accounting_js' => (int)$javascript,
            'accounting_displayformat' => $format,
            'debitaccount' => [0 => $bank, 1 => '', 24 => $bank, 46 => $cash],
            'creditaccount' => [0 => $cash, 1 => '', 24 => '', 46 => $bank],
            'debitamount' => [0 => '', 1 => '', 24 => '12.50', 46 => '0'],
            'creditamount' => [0 => '', 1 => '', 24 => '', 46 => '0'],
            'weight_debitaccount' => [0 => 3, 24 => 2],
        ];
        if (!$javascript) {
            foreach (['debit', 'credit'] as $side) {
                $submitted[$side . 'account_display'] = $submitted[$side . 'account'];
                $submitted[$side . 'amount_display'] = array_map(static function ($value) use ($format) {
                    return $value === '' ? '' : amount_helper::format_amount((float)$value, $format);
                }, $submitted[$side . 'amount']);
                unset($submitted[$side . 'account'], $submitted[$side . 'amount']);
            }
            $submitted['weight_debitaccount_display'] = $submitted['weight_debitaccount'];
            unset($submitted['weight_debitaccount']);
        }
        \qtype_accounting_edit_form::mock_submit($submitted);
        $question = (object)[
            'qtype' => 'accounting',
            'contextid' => $questioncontext->id,
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
            new \core_question\local\bank\question_edit_contexts($questioncontext)
        );
        $this->assertFalse($form->is_validated());
        $submitteddata = $form->get_submitted_data();
        $this->assertEquals(12.5, $submitteddata->debitamount[24]);
        $this->assertEquals($cash, $submitteddata->debitaccount[46]);
        $html = $form->render();
        $this->assertStringContainsString(get_string('err_debitamountrequired', 'qtype_accounting'), $html);
        $this->assertStringContainsString(get_string('err_creditamountrequired', 'qtype_accounting'), $html);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(
            get_string('all'),
            $xpath->evaluate('string(//input[@name="accountsindropdown"]/@value)')
        );
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
            'no JavaScript German' => ['de', false, false],
            'no JavaScript US' => ['us', true, false],
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
     * Preserve chart order, account-name order, empty charts, IDs, and course isolation; site charts follow.
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
        $sitechart = chart_manager::create_chart('Aardvark site chart', \context_system::instance()->id);
        $siteaccount = account_manager::add($sitechart, 'Site account', 0);
        $othercourse = $this->getDataGenerator()->create_course();
        $outsidechart = chart_manager::create_chart('Outside', \context_course::instance($othercourse->id)->id);
        account_manager::add($outsidechart, 'Hidden account', 0);

        $this->assertSame([
            $achart => [$aaccount => 'Alpha account', $zaccount => 'Zulu account'],
            $emptychart => [],
            $zchart => [$otheraccount => 'Alpha account'],
            $sitechart => [$siteaccount => 'Site account'],
        ], $this->get_accounts($context));
    }

    /**
     * Without course or site charts the result is empty; site charts are offered everywhere.
     */
    public function test_no_available_charts(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertSame([], $this->get_accounts(\context_course::instance($course->id)));
        $this->assertSame([], $this->get_accounts(\context_system::instance()));
        $chartid = chart_manager::create_chart('System chart', \context_system::instance()->id);
        $accountid = account_manager::add($chartid, 'Bank');
        $this->assertSame([$chartid => [$accountid => 'Bank']], $this->get_accounts(\context_system::instance()));
        $this->assertSame([$chartid => [$accountid => 'Bank']], $this->get_accounts(\context_course::instance($course->id)));
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
