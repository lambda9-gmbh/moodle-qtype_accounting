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

namespace qtype_accounting\external;

use core_external\external_api;
use qtype_accounting\account_manager;
use qtype_accounting\chart_manager;

#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting\external\get_charts::class)]
/**
 * Tests for manually refreshing charts in the question editor.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\external\get_charts
 */
final class get_charts_test extends \advanced_testcase {
    /**
     * Get the question bank context ID of a course, which owns the course's charts.
     *
     * @param \stdClass $course The course.
     * @return int The context ID.
     */
    private function bank(\stdClass $course): int {
        return (int)chart_manager::get_chart_context(\context_course::instance($course->id))->id;
    }

    /**
     * Return empty charts and fresh accounts, without exposing another question bank's data.
     */
    public function test_fresh_course_options(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context::instance_by_id($this->bank($course));
        $emptyid = chart_manager::create_chart('Empty', $context->id);
        $chartid = chart_manager::create_chart('Main', $context->id);
        $accountid = account_manager::add($chartid, 'Bank');
        $othercourse = $this->getDataGenerator()->create_course();
        chart_manager::create_chart('Outside', $this->bank($othercourse));

        $result = external_api::clean_returnvalue(get_charts::execute_returns(), get_charts::execute($this->bank($course)));
        $this->assertSame([
            ['id' => (int)$emptyid, 'name' => 'Empty', 'accounts' => []],
            ['id' => (int)$chartid, 'name' => 'Main', 'accounts' => [
                ['id' => (int)$accountid, 'name' => 'Bank'],
            ]],
        ], $result);

        chart_manager::delete_chart($emptyid);
        chart_manager::update_chart($chartid, 'Renamed');
        account_manager::delete($accountid);
        $newaccountid = account_manager::add($chartid, 'Cash');
        $result = external_api::clean_returnvalue(get_charts::execute_returns(), get_charts::execute($this->bank($course)));
        $this->assertSame([
            ['id' => (int)$chartid, 'name' => 'Renamed', 'accounts' => [
                ['id' => (int)$newaccountid, 'name' => 'Cash'],
            ]],
        ], $result);
        chart_manager::delete_chart($chartid);
        $this->assertSame([], get_charts::execute($this->bank($course)));

        // Site charts are offered after the course charts and marked as such.
        $sitechartid = chart_manager::create_chart('Standard', \context_system::instance()->id);
        $siteaccountid = account_manager::add($sitechartid, 'Bank');
        $result = external_api::clean_returnvalue(get_charts::execute_returns(), get_charts::execute($this->bank($course)));
        $this->assertSame([
            ['id' => (int)$sitechartid, 'name' => get_string('sitechart_suffix', 'qtype_accounting', 'Standard'), 'accounts' => [
                ['id' => (int)$siteaccountid, 'name' => 'Bank'],
            ]],
        ], $result);
    }

    /**
     * Students must not read chart management data.
     */
    public function test_requires_chart_management_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $bank = $this->bank($course);
        $this->setUser($student);
        $this->assert_access_denied($bank);
    }

    /**
     * Management permission in one course does not grant access to another.
     */
    public function test_course_permission_is_checked(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $othercourse->id, 'student');
        $bank = $this->bank($course);
        $otherbank = $this->bank($othercourse);
        $this->setUser($teacher);
        $this->assertSame([], get_charts::execute($bank));
        $this->assert_access_denied($otherbank);
    }

    /**
     * Names are returned as plain text, as the question editor sets them as option text.
     */
    public function test_names_are_plain_text(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $chartid = chart_manager::create_chart('Soll & Haben', $this->bank($course));
        account_manager::add($chartid, 'Forderungen a. LL & sonstige');

        $result = external_api::clean_returnvalue(get_charts::execute_returns(), get_charts::execute($this->bank($course)));
        $this->assertSame('Soll & Haben', $result[0]['name']);
        $this->assertSame('Forderungen a. LL & sonstige', $result[0]['accounts'][0]['name']);
    }

    /**
     * Assert that the current user cannot read the charts of a question bank.
     *
     * From Moodle 5.0 the default question bank of a course is a hidden module, so users who may not
     * see hidden activities are rejected by the login check before the capability check.
     *
     * @param int $contextid The question bank context ID.
     */
    private function assert_access_denied(int $contextid): void {
        try {
            get_charts::execute($contextid);
            $this->fail('Access to the charts was not denied.');
        } catch (\core\exception\required_capability_exception | \core\exception\require_login_exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }
}
