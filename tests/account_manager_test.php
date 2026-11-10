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

/**
 * Unit tests for the account_manager class.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\account_manager
 */
final class account_manager_test extends \advanced_testcase {
    /**
     * Warnings list each logical question once, including usage in old attempts.
     */
    public function test_usage_deduplicates_versions_without_merging_same_name_questions(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $chartid = chart_manager::create_chart('Versioned chart', $context->id);
        $bank = account_manager::add($chartid, 'Bank');
        $sales = account_manager::add($chartid, 'Sales');
        $historical = account_manager::add($chartid, 'Historical selection');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        $overrides = [
            'category' => $category->id, 'name' => 'Shared title', 'chartofaccountsid' => $chartid,
            'debitaccount' => [$bank], 'creditaccount' => [$sales],
            'debitamount' => [100], 'creditamount' => [100],
        ];
        $question = $generator->create_question('accounting', null, $overrides);
        for ($i = 0; $i < 2; $i++) {
            $loaded = \question_bank::load_question($question->id);
            $usage = \question_engine::make_questions_usage_by_activity('core_question_preview', $context);
            $usage->set_preferred_behaviour('deferredfeedback');
            $usage->add_question($loaded);
            $usage->start_all_questions();
            $response = $loaded->get_correct_response();
            $response['debitaccount_1'] = $historical;
            $response['debitamount_1'] = '1';
            $usage->process_action(1, $response);
            \question_engine::save_questions_usage_by_activity($usage);
            $question = $generator->update_question($question, null, $overrides);
        }
        $other = $generator->create_question('accounting', null, $overrides);
        $questions = account_manager::get_using_questions([$bank]);
        $this->assertCount(2, $questions);
        $this->assertArrayHasKey($question->id, $questions);
        $this->assertArrayHasKey($other->id, $questions);
        $this->assertCount(1, account_manager::get_using_questions([$historical]));
    }

    /**
     * A failure partway through either import leaves existing data intact.
     */
    public function test_failed_imports_are_atomic(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->resetAfterTest();
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Existing atomic chart', $contextid);
        account_manager::add($chartid, 'Existing account');
        $content = 'New valid account' . "\n" . str_repeat('x', 256);
        $imports = [
            fn() => chart_manager::import_chart_from_csv($content, $contextid, 'Atomic.csv'),
            fn() => account_manager::import_into_chart($chartid, $content),
        ];
        foreach ($imports as $import) {
            try {
                $import();
                $this->fail('Expected the invalid account name to abort the import.');
            } catch (\moodle_exception $exception) {
                $this->assertSame('maximumchars', $exception->errorcode);
            }
            $this->assertFalse($DB->record_exists('qtype_accounting_charts', ['name' => 'Atomic']));
            $this->assertFalse($DB->record_exists('qtype_accounting_accounts', ['accountname' => 'New valid account']));
            $this->assertCount(1, account_manager::get_for_chart($chartid));
        }
    }

    /**
     * Usage can be shown in warnings without blocking account changes or chart deletion.
     */
    public function test_referenced_accounts_can_be_renamed_and_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $chartid = chart_manager::create_chart('Protected', \context_course::instance($course->id)->id);
        $bank = account_manager::add($chartid, 'Bank');
        $sales = account_manager::add($chartid, 'Sales');
        $category = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question_category([
            'contextid' => \context_course::instance($course->id)->id,
        ]);
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')->create_question('accounting', null, [
            'category' => $category->id, 'name' => 'Protected question', 'chartofaccountsid' => $chartid,
            'debitaccount' => [$bank], 'creditaccount' => [$sales], 'debitamount' => [100], 'creditamount' => [100],
        ]);
        $wrong = account_manager::add($chartid, 'Only used by a student');
        $loaded = \question_bank::load_question($question->id);
        $usage = \question_engine::make_questions_usage_by_activity(
            'core_question_preview',
            \context_course::instance($course->id)
        );
        $usage->set_preferred_behaviour('deferredfeedback');
        $usage->add_question($loaded);
        $usage->start_all_questions();
        $response = $loaded->get_correct_response();
        $response['debitaccount_1'] = $wrong;
        $response['debitamount_1'] = '1';
        $usage->process_action(1, $response);
        \question_engine::save_questions_usage_by_activity($usage);
        foreach ([$bank, $wrong] as $accountid) {
            $this->assertArrayHasKey($question->id, account_manager::get_using_questions([$accountid]));
        }
        $this->assertTrue(account_manager::update($bank, 'Renamed'));
        $this->assertSame('Renamed', $DB->get_field('qtype_accounting_accounts', 'accountname', ['id' => $bank]));
        $this->assertEquals($bank, $DB->get_field('qtype_accounting_entries', 'debitaccountid', [
            'questionid' => $question->id,
        ]));
        $this->assertEquals(1, $loaded->grade_response($loaded->get_correct_response())[0]);
        try {
            account_manager::add($chartid, 'Renamed');
            $this->fail('Expected a duplicate-name error.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('err_duplicateaccount', $exception->errorcode);
        }
        $this->assertTrue(account_manager::delete($bank));
        $this->assertTrue(account_manager::delete($wrong));
        $this->assertFalse($DB->record_exists('qtype_accounting_accounts', ['id' => $bank]));
        $this->assertFalse($DB->record_exists('qtype_accounting_accounts', ['id' => $wrong]));
        $this->assertTrue(chart_manager::delete_chart($chartid));
        $this->assertFalse(chart_manager::get_chart($chartid));
        $this->assertCount(0, account_manager::get_for_chart($chartid));
        // Confirmed deletion does not silently rewrite the affected question or attempts.
        $this->assertEquals($chartid, $DB->get_field('qtype_accounting_options', 'chartofaccountsid', [
            'questionid' => $question->id,
        ]));
    }

    /**
     * Test adding and retrieving accounts.
     */
    public function test_add_and_get_accounts(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Accounts Test', $contextid);

        $id1 = account_manager::add($chartid, '1200 Bank', 0);
        $id2 = account_manager::add($chartid, '8400 Erlöse 19%', 1);
        $id3 = account_manager::add($chartid, '1000 Kasse', 2);

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertGreaterThan(0, $id3);

        $accounts = account_manager::get_for_chart($chartid);
        $this->assertCount(3, $accounts);

        // Accounts are returned sorted alphabetically by accountname.
        $accountnames = array_map(function ($a) {
            return $a->accountname;
        }, array_values($accounts));
        $this->assertEquals(['1000 Kasse', '1200 Bank', '8400 Erlöse 19%'], $accountnames);
    }

    /**
     * Test updating an account.
     */
    public function test_update_account(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Update Account Test', $contextid);
        $accountid = account_manager::add($chartid, '1200 Bank', 0);

        $result = account_manager::update($accountid, '1201 Bankkonten');
        $this->assertTrue($result);

        $accounts = account_manager::get_for_chart($chartid);
        $account = reset($accounts);
        $this->assertEquals('1201 Bankkonten', $account->accountname);
    }

    /**
     * Test deleting a single account.
     */
    public function test_delete_account(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Delete Account Test', $contextid);
        $id1 = account_manager::add($chartid, '1200 Bank', 0);
        account_manager::add($chartid, '8400 Erlöse', 1);

        $this->assertCount(2, account_manager::get_for_chart($chartid));

        $result = account_manager::delete($id1);
        $this->assertTrue($result);

        $accounts = account_manager::get_for_chart($chartid);
        $this->assertCount(1, $accounts);
        $remaining = reset($accounts);
        $this->assertEquals('8400 Erlöse', $remaining->accountname);
    }

    /**
     * Test that import_into_chart skips duplicate account names.
     */
    public function test_import_into_chart_skips_duplicates(): void {
        $this->resetAfterTest();

        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Dup Test', $contextid);
        account_manager::add($chartid, '1200 Bank', 0);

        $data = "1200 Bank\n8400 Erlöse";

        $result = account_manager::import_into_chart($chartid, $data);

        // Only the new account should be imported.
        $this->assertEquals(1, $result['imported']);
        $accounts = account_manager::get_for_chart($chartid);
        $this->assertCount(2, $accounts);
    }
}
