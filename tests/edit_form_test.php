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
require_once($CFG->dirroot . '/question/type/accounting/tests/helper.php');

#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'add_accounts_section')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'get_current_accounts')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'validate_available_accounts')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'definition_after_data')]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting\entry_validator::class)]
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_accounting_edit_form::class, 'add_entries_section')]
/**
 * Tests for the accounting editing form's account data.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting_edit_form::add_accounts_section
 * @covers     \qtype_accounting_edit_form::get_current_accounts
 * @covers     \qtype_accounting_edit_form::validate_available_accounts
 * @covers     \qtype_accounting_edit_form::definition_after_data
 * @covers     \qtype_accounting_edit_form::add_display_options_section
 * @covers     \qtype_accounting\entry_validator
 * @covers     \qtype_accounting_edit_form::add_entries_section
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
        $account = 1;
        $saved = $generator->create_question('accounting', null, [
            'category' => $category->id, 'accounts' => ['Bank'], 'accountkeys' => [],
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
            'accounts' => 'Bank', 'accountkeys' => json_encode(['Bank' => 1]), 'numberformat' => 'de',
            'accountsindropdown' => $input ?? get_string('all'),
            'debitaccount' => [$account], 'creditaccount' => [$account],
            'debitamount' => [100], 'creditamount' => [100],
        ]);
        $form = new \qtype_accounting_edit_form($url, $question, $category, $contexts);
        $form->set_data(clone $question);
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
     * The editor shows the question's own accounts, also after the template they were copied from is deleted.
     */
    public function test_opening_question_lists_its_accounts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $questioncontext = \context::instance_by_id($category->contextid);
        $template = chart_manager::create_chart('Template', \context_system::instance()->id);
        account_manager::add($template, 'Cash');
        $saved = $generator->create_question('accounting', null, [
            'category' => $category->id, 'accounts' => "Cash\nBank", 'accountkeys' => ['Cash' => 4],
            'debitaccount' => [5], 'creditaccount' => [4],
            'debitamount' => [100], 'creditamount' => [100],
        ]);
        chart_manager::delete_chart($template);

        $question = \question_bank::load_question_data($saved->id);
        $question->contextid = $questioncontext->id;
        $question->beingcopied = false;
        $question->formoptions = (object)[
            'mustbeusable' => false, 'repeatelements' => true,
            'canedit' => true, 'canmove' => false, 'cansaveasnew' => false,
        ];
        $form = new \qtype_accounting_edit_form(
            new \moodle_url('/question/bank/editquestion/question.php'),
            $question,
            $category,
            new \core_question\local\bank\question_edit_contexts($questioncontext)
        );
        $form->set_data(clone $question);
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        $xpath = new \DOMXPath($dom);
        $this->assertSame("Cash\nBank", trim($xpath->evaluate('string(//textarea[@name="accounts"])')));
        $this->assertSame('{"Cash":4,"Bank":5}', $xpath->evaluate('string(//input[@name="accountkeys"]/@value)'));
        $this->assertSame('Bank', $xpath->evaluate('string(//select[@name="debitaccount[0]"]/option[@selected])'));
        $this->assertSame('Cash', $xpath->evaluate('string(//select[@name="creditaccount[0]"]/option[@selected])'));
        $this->assertSame('100,00', $xpath->evaluate('string(//input[@name="debitamount[0]"]/@value)'));
    }

    /**
     * A saved entry deleted with its delete button is neither shown nor saved again.
     *
     * @dataProvider deleted_row_provider
     * @param int $remainingindex Index of the entry retained in the editor.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deleted_row_provider')]
    public function test_deleted_saved_row_stays_deleted(int $remainingindex): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $questioncontext = \context::instance_by_id($category->contextid);
        [$bank, $cash] = [1, 2];
        $saved = $generator->create_question('accounting', null, [
            'category' => $category->id,
            'accounts' => ['Bank', 'Cash'], 'accountkeys' => [],
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
        \qtype_accounting_edit_form::mock_submit([
            'category' => $category->id . ',' . $questioncontext->id,
            'name' => 'Deleted row',
            'questiontext' => ['text' => 'Record the transaction.', 'format' => FORMAT_HTML],
            'defaultmark' => 1, 'penalty' => 0.3333333,
            'accounts' => "Bank\nCash", 'accountkeys' => json_encode(['Bank' => 1, 'Cash' => 2]), 'numberformat' => 'de',
            'noentries' => 2,
            'deleteentry-hidden' => [1 - $remainingindex => 1],
            'debitaccount' => [$remainingindex => $bank], 'creditaccount' => [$remainingindex => $cash],
            'debitamount' => [$remainingindex => $amount . ',00'], 'creditamount' => [$remainingindex => $amount . ',00'],
        ]);
        $form = new \qtype_accounting_edit_form(
            new \moodle_url('/question/bank/editquestion/question.php'),
            $question,
            $category,
            new \core_question\local\bank\question_edit_contexts($questioncontext)
        );
        // The real editor supplies saved defaults after constructing the submitted form.
        $form->set_data(clone $question);
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//select[@name="debitaccount[' . (1 - $remainingindex) . ']"]')->length);
        $data = $form->get_data();
        $this->assertNotFalse($data);
        foreach (['debit', 'credit'] as $side) {
            $this->assertArrayNotHasKey(1 - $remainingindex, $data->{$side . 'account'});
            $this->assertSame(amount_helper::decimal_amount($amount * 100), $data->{$side . 'amount'}[$remainingindex]);
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
     * Cover deletion of either saved entry.
     *
     * @return array Submission variants.
     */
    public static function deleted_row_provider(): array {
        return [[0], [1]];
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
     * Create a question category in a new course's question bank.
     *
     * @return \stdClass The question category.
     */
    protected function create_category(): \stdClass {
        global $PAGE;
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $course = $this->getDataGenerator()->create_course();
        $context = \qtype_accounting_test_helper::question_bank_context($course);
        return $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $context->id]);
    }

    /**
     * Create a question form.
     *
     * @param \stdClass $category The question category.
     * @param \stdClass|null $question The question, or null for a new question.
     * @return array The form and the completed question.
     */
    protected function create_form(\stdClass $category, ?\stdClass $question = null): array {
        global $USER;
        $context = \context::instance_by_id($category->contextid);
        $question = (object)((array)($question ?? new \stdClass()) + [
            'qtype' => 'accounting',
            'contextid' => $context->id,
            'createdby' => $USER->id,
            'category' => $category->id,
            'questiontext' => '',
            'generalfeedback' => '',
            'formoptions' => (object)['mustbeusable' => false, 'repeatelements' => true],
        ]);
        $form = new \qtype_accounting_edit_form(
            new \moodle_url('/question/bank/editquestion/question.php'),
            $question,
            $category,
            new \core_question\local\bank\question_edit_contexts($context)
        );
        return [$form, $question];
    }

    /**
     * Render a form and return an XPath for its HTML.
     *
     * @param \qtype_accounting_edit_form $form The form.
     * @return \DOMXPath The XPath.
     */
    protected function render_form(\qtype_accounting_edit_form $form): \DOMXPath {
        $dom = new \DOMDocument();
        @$dom->loadHTML($form->render());
        return new \DOMXPath($dom);
    }

    /**
     * Disabling the site setting showpercolumn replaces the "Dr." and "Per" labels of the entries with the side names.
     */
    public function test_show_per_column_setting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->create_category();
        $labels = fn() => array_map(
            fn($node) => $node->textContent,
            iterator_to_array($this->render_form($this->create_form($category)[0])
                ->query('//span[contains(@class, "qtype_accounting-edit-label")]'))
        );
        $per = get_string('per', 'qtype_accounting');
        $an = get_string('an', 'qtype_accounting');
        // Two entries with a label on each side.
        $this->assertSame([$per, $an, $per, $an], $labels());
        set_config('showpercolumn', 0, 'qtype_accounting');
        $debit = get_string('debit', 'qtype_accounting');
        $credit = get_string('credit', 'qtype_accounting');
        $this->assertSame([$debit, $credit, $debit, $credit], $labels());
    }

    /**
     * Saved amounts are shown in the question's number format; the absent side of a one-sided entry stays empty.
     */
    public function test_saved_one_sided_entry_keeps_absent_amount_empty(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = (object)['options' => (object)[
            'accounts' => [12 => (object)['accountkey' => 12, 'accountname' => 'Bank', 'sortorder' => 0]],
            'numberformat' => 'de',
            'entries' => [(object)[
                'debitaccountid' => 12, 'debitamount' => '1234.5000', 'creditaccountid' => null, 'creditamount' => '0.0000',
                'weight_debitaccount' => 2, 'weight_debitamount' => 1, 'weight_creditaccount' => 1, 'weight_creditamount' => 1,
            ]],
        ]];
        [$form, $question] = $this->create_form($this->create_category(), $question);
        $form->set_data($question);
        $xpath = $this->render_form($form);
        $this->assertSame('1.234,50', $xpath->evaluate('string(//input[@name="debitamount[0]"]/@value)'));
        $this->assertSame('', $xpath->evaluate('string(//input[@name="creditamount[0]"]/@value)'));
        $this->assertSame('2', $xpath->evaluate('string(//select[@name="weight_debitaccount[0]"]/option[@selected]/@value)'));
        // A saved question shows its entries, at least two.
        $this->assertSame(2, $xpath->query('//select[starts-with(@name, "debitaccount[")]')->length);
    }

    /**
     * A failed submission keeps the entered entries and shows the errors at the affected entry.
     *
     * @dataProvider failed_submission_provider
     * @param string $format Submitted number format.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('failed_submission_provider')]
    public function test_failed_submission_preserves_entries(string $format): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $amount = $format === 'de' ? '12,50' : '12.50';
        $category = $this->create_category();
        \qtype_accounting_edit_form::mock_submit([
            'category' => $category->id . ',' . $category->contextid,
            'name' => 'Missing amounts',
            'questiontext' => ['text' => 'Record the transaction.', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'accounts' => "Bank\nCash",
            'accountkeys' => '[]',
            'numberformat' => $format,
            'noentries' => 3,
            'debitaccount' => [1, 1, 2],
            'creditaccount' => [2, 0, 1],
            'debitamount' => ['', $amount, '5'],
            'creditamount' => ['', '', '5'],
            'weight_debitaccount' => [3, 2, 1],
        ]);
        [$form] = $this->create_form($category);
        $this->assertFalse($form->is_validated());
        $xpath = $this->render_form($form);
        $html = $xpath->document->saveHTML();
        $this->assertStringContainsString(get_string('err_debitamountrequired', 'qtype_accounting'), $html);
        $this->assertStringContainsString(get_string('err_creditamountrequired', 'qtype_accounting'), $html);
        $this->assertStringContainsString(get_string('err_balancemismatch', 'qtype_accounting'), $html);
        $this->assertSame("Bank\nCash", trim($xpath->evaluate('string(//textarea[@name="accounts"])')));
        $this->assertSame('Bank', $xpath->evaluate('string(//select[@name="debitaccount[0]"]/option[@selected])'));
        $this->assertSame('Cash', $xpath->evaluate('string(//select[@name="creditaccount[0]"]/option[@selected])'));
        $this->assertSame($amount, $xpath->evaluate('string(//input[@name="debitamount[1]"]/@value)'));
        $this->assertSame('3', $xpath->evaluate('string(//select[@name="weight_debitaccount[0]"]/option[@selected])'));
        $this->assertSame('2', $xpath->evaluate('string(//select[@name="weight_debitaccount[1]"]/option[@selected])'));
        $this->assertSame(3, $xpath->query('//select[starts-with(@name, "debitaccount[")]')->length);
    }

    /**
     * Amounts are read in the submitted number format and saved as decimal numbers.
     */
    public function test_amounts_follow_number_format(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->create_category();
        foreach (['de' => '1.234,50', 'us' => '1,234.50'] as $format => $amount) {
            \qtype_accounting_edit_form::mock_submit([
                'category' => $category->id . ',' . $category->contextid,
                'name' => 'Amounts',
                'questiontext' => ['text' => 'Record the transaction.', 'format' => FORMAT_HTML],
                'defaultmark' => 1,
                'penalty' => 0.3333333,
                'accounts' => "Bank\nCash",
                'accountkeys' => '[]',
                'numberformat' => $format,
                'noentries' => 2,
                'debitaccount' => [1, 0],
                'creditaccount' => [2, 0],
                'debitamount' => [$amount, ''],
                'creditamount' => [$amount, ''],
            ]);
            [$form] = $this->create_form($category);
            $data = $form->get_data();
            $this->assertNotEmpty($data, $format);
            $this->assertSame('1234.50', $data->debitamount[0]);
            $this->assertSame('1234.50', $data->creditamount[0]);
            $this->assertSame('', $data->debitamount[1]);
        }
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
        $render = function () use ($bankcontext, $category, $USER): \DOMXPath {
            $question = (object)[
                'qtype' => 'accounting',
                'contextid' => $bankcontext->id,
                'createdby' => $USER->id,
                'category' => $category->id,
                'questiontext' => '',
                'generalfeedback' => '',
                'formoptions' => (object)['mustbeusable' => false, 'repeatelements' => true],
                'options' => (object)[
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
     * Cover both number formats.
     *
     * @return array Submission variants.
     */
    public static function failed_submission_provider(): array {
        return [
            'German' => ['de'],
            'US' => ['us'],
        ];
    }

    /**
     * The account list must be valid, and entries may only use accounts of the list.
     */
    public function test_account_list_validation(): void {
        $form = $this->getMockBuilder(\qtype_accounting_edit_form::class)
            ->disableOriginalConstructor()->onlyMethods([])->getMock();
        $property = new \ReflectionProperty(\qtype_accounting_edit_form::class, 'question');
        $property->setAccessible(true);
        $property->setValue($form, (object)['options' => (object)['accounts' => [
            4 => (object)['accountkey' => 4, 'accountname' => 'Bank', 'sortorder' => 0],
        ]]]);
        $method = new \ReflectionMethod(\qtype_accounting_edit_form::class, 'validate_available_accounts');
        $method->setAccessible(true);

        // Bank keeps its key 4, Cash gets the next free key.
        $data = ['accounts' => " Bank \n\nCash", 'debitaccount' => [4], 'creditaccount' => [5]];
        $this->assertSame([], $method->invoke($form, $data, []));
        $data['creditaccount'] = [6];
        $this->assertArrayHasKey('balancevalidation', $method->invoke($form, $data, []));
        $data['accounts'] = "Cash";
        $data['creditaccount'] = [5];
        $this->assertArrayHasKey('balancevalidation', $method->invoke($form, $data, []));
        $data['accounts'] = "Bank\nBank";
        $this->assertSame(
            get_string('err_accountduplicate', 'qtype_accounting', 'Bank'),
            $method->invoke($form, $data, [])['accounts']
        );
        $data['accounts'] = str_repeat('x', 256);
        $this->assertArrayHasKey('accounts', $method->invoke($form, $data, []));
    }

    /**
     * Accounts can be copied from a site template or from another question of the same question bank only.
     */
    public function test_copy_accounts(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/question/bank/editquestion/question.php');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $course = $this->getDataGenerator()->create_course();
        $context = \qtype_accounting_test_helper::question_bank_context($course);
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $template = chart_manager::create_chart('Template', \context_system::instance()->id);
        account_manager::add($template, 'Template B', 1);
        account_manager::add($template, 'Template A', 0);
        $sibling = $generator->create_question('accounting', null, [
            'category' => $category->id, 'name' => 'Sibling', 'accounts' => ['Sibling account'], 'accountkeys' => [],
            'debitaccount' => [1], 'creditaccount' => [1],
        ]);
        $othercourse = $this->getDataGenerator()->create_course();
        $othercategory = $generator->create_question_category([
            'contextid' => \qtype_accounting_test_helper::question_bank_context($othercourse)->id,
        ]);
        $outsider = $generator->create_question('accounting', null, [
            'category' => $othercategory->id, 'accounts' => ['Outside account'], 'accountkeys' => [],
            'debitaccount' => [1], 'creditaccount' => [1],
        ]);

        $render = function (string $source) use ($category, $context, $USER): \DOMXPath {
            \qtype_accounting_edit_form::mock_submit([
                'category' => $category->id . ',' . $context->id, 'accounts' => 'Own account',
                'copyaccounts' => 1, 'copyaccountsfrom' => $source,
            ]);
            $form = new \qtype_accounting_edit_form(
                new \moodle_url('/question/bank/editquestion/question.php'),
                (object)[
                    'qtype' => 'accounting', 'contextid' => $context->id, 'createdby' => $USER->id,
                    'category' => $category->id, 'questiontext' => '', 'generalfeedback' => '',
                    'formoptions' => (object)['mustbeusable' => false, 'repeatelements' => true],
                ],
                $category,
                new \core_question\local\bank\question_edit_contexts($context)
            );
            $dom = new \DOMDocument();
            @$dom->loadHTML($form->render());
            return new \DOMXPath($dom);
        };

        $xpath = $render('t-' . $template);
        $this->assertSame("Template A\nTemplate B", trim($xpath->evaluate('string(//textarea[@name="accounts"])')));
        $this->assertSame('Template A', $xpath->evaluate('string(//select[@name="debitaccount[0]"]/option[2])'));
        $this->assertSame('Sibling', trim(explode(':', $xpath->evaluate(
            'string(//select[@name="copyaccountsfrom"]/optgroup[2]/option[1])'
        ))[1] ?? ''));
        $xpath = $render('q-' . $sibling->id);
        $this->assertSame('Sibling account', trim($xpath->evaluate('string(//textarea[@name="accounts"])')));
        $xpath = $render('q-' . $outsider->id);
        $this->assertSame('Own account', trim($xpath->evaluate('string(//textarea[@name="accounts"])')));
    }
}
