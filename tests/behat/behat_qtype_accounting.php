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

/**
 * Behat steps for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_accounting extends behat_base {
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

    /**
     * Switch back to the window that shows the question editor.
     *
     * The chart management link opens in a new tab. Selecting the editor by URL avoids
     * relying on the driver's notion of the main window, which differs between Moodle versions.
     *
     * @When /^I switch to the accounting question editor window$/
     * @throws ExpectationException If no window shows the question editor.
     */
    public function i_switch_to_the_accounting_question_editor_window(): void {
        $session = $this->getSession();
        foreach ($session->getWindowNames() as $name) {
            $session->switchToWindow($name);
            if (strpos($session->getCurrentUrl(), '/question/bank/editquestion/question.php') !== false) {
                return;
            }
        }
        throw new ExpectationException('No window shows the question editor.', $session);
    }
}
