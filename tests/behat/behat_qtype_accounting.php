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
 * Behat steps for qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Gherkin\Node\TableNode;

/**
 * Behat steps for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_accounting extends behat_base {
    /**
     * Create questions using real chart account IDs instead of obsolete text answers.
     *
     * @Given /^the following accounting questions exist:$/
     * @param TableNode $table Question data, including account names and amounts.
     */
    public function accounting_questions_exist(TableNode $table): void {
        global $DB;
        $generator = testing_util::get_data_generator()->get_plugin_generator('core_question');
        foreach ($table->getHash() as $data) {
            $category = $DB->get_record('question_categories', ['name' => $data['questioncategory']], '*', MUST_EXIST);
            $chart = \qtype_accounting\chart_manager::get_chart_by_name('Accounting test chart', $category->contextid);
            $chartid = $chart ? $chart->id : \qtype_accounting\chart_manager::create_chart(
                'Accounting test chart',
                $category->contextid
            );
            $names = ['1200', '8400', '9998', '9999', $data['debitaccount'] ?? '1200', $data['creditaccount'] ?? '8400'];
            foreach (array_unique($names) as $name) {
                if (!$DB->record_exists('qtype_accounting_accounts', ['chartid' => $chartid, 'accountname' => $name])) {
                    \qtype_accounting\account_manager::add($chartid, $name);
                }
            }
            $data['category'] = $category->id;
            $data['chartofaccountsid'] = $chartid;
            $data['numberformat'] = $data['numberformat'] ?? 'de';
            if (isset($data['questiontext'])) {
                $data['questiontext'] = ['text' => $data['questiontext'], 'format' => FORMAT_HTML];
            }
            foreach (['debit', 'credit'] as $side) {
                $name = $data[$side . 'account'] ?? ($side === 'debit' ? '1200' : '8400');
                $data[$side . 'account'] = [$DB->get_field('qtype_accounting_accounts', 'id', [
                    'chartid' => $chartid, 'accountname' => $name,
                ], MUST_EXIST)];
                $data[$side . 'amount'] = [(float)($data[$side . 'amount'] ?? 1000)];
            }
            unset($data['questioncategory'], $data['qtype']);
            $generator->create_question('accounting', null, $data);
        }
    }

    /**
     * Check filled answers through visible controls, including the localised amounts.
     *
     * @Then /^the accounting response has debit "([^"]*)" amount "([^"]*)" and credit "([^"]*)" amount "([^"]*)"$/
     * @param string $debit Debit account label.
     * @param string $debitamount Debit amount.
     * @param string $credit Credit account label.
     * @param string $creditamount Credit amount.
     */
    public function accounting_response_matches(string $debit, string $debitamount, string $credit, string $creditamount): void {
        foreach (['debit' => [$debit, $debitamount], 'credit' => [$credit, $creditamount]] as $side => [$account, $amount]) {
            $select = $this->find('xpath', '//select[contains(@name,"' . $side . 'account_0")]');
            $selected = $select->find('css', 'option[value="' . $select->getValue() . '"]');
            $input = $this->find('xpath', '//input[contains(@name,"' . $side . 'amount_0")]');
            if (!$selected || $selected->getText() !== $account || $input->getValue() !== $amount) {
                throw new ExpectationException(
                    'Accounting response differs from the expected accounts or amounts.',
                    $this->getSession()
                );
            }
        }
    }

    /**
     * Create chart options for question editor scenarios.
     *
     * @Given /^the accounting chart "([^"]*)" with accounts "([^"]*)" exists in course "([^"]*)"$/
     * @param string $name Chart name.
     * @param string $accounts Comma-separated account names.
     * @param string $coursename Course full name.
     */
    public function accounting_chart_exists(string $name, string $accounts, string $coursename): void {
        global $DB;
        $course = $DB->get_record('course', ['fullname' => $coursename], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        $chartid = \qtype_accounting\chart_manager::create_chart($name, $context->id);
        foreach (explode(',', $accounts) as $account) {
            \qtype_accounting\account_manager::add($chartid, trim($account));
        }
    }
}
