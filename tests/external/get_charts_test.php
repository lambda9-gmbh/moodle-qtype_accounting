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
     * Return empty charts and fresh accounts, without exposing another course's data.
     */
    public function test_fresh_course_options(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $emptyid = chart_manager::create_chart('Empty', $context->id);
        $chartid = chart_manager::create_chart('Main', $context->id);
        $accountid = account_manager::add($chartid, 'Bank');
        $othercourse = $this->getDataGenerator()->create_course();
        chart_manager::create_chart('Outside', \context_course::instance($othercourse->id)->id);

        $result = external_api::clean_returnvalue(get_charts::execute_returns(), get_charts::execute($course->id));
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
        $result = external_api::clean_returnvalue(get_charts::execute_returns(), get_charts::execute($course->id));
        $this->assertSame([
            ['id' => (int)$chartid, 'name' => 'Renamed', 'accounts' => [
                ['id' => (int)$newaccountid, 'name' => 'Cash'],
            ]],
        ], $result);
        chart_manager::delete_chart($chartid);
        $this->assertSame([], get_charts::execute($course->id));

        // Site charts are offered after the course charts and marked as such.
        $sitechartid = chart_manager::create_chart('Standard', \context_system::instance()->id);
        $siteaccountid = account_manager::add($sitechartid, 'Bank');
        $result = external_api::clean_returnvalue(get_charts::execute_returns(), get_charts::execute($course->id));
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
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        get_charts::execute($course->id);
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
        $this->setUser($teacher);
        $this->assertSame([], get_charts::execute($course->id));
        $this->expectException(\required_capability_exception::class);
        get_charts::execute($othercourse->id);
    }
}
