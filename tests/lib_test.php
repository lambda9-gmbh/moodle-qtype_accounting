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
require_once($CFG->dirroot . '/question/type/accounting/lib.php');

#[\PHPUnit\Framework\Attributes\CoversFunction('qtype_accounting_inplace_editable')]
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_accounting\form\account_form::class)]
/**
 * Tests for editing chart of accounts templates in place and in the modal form.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::qtype_accounting_inplace_editable
 * @covers     \qtype_accounting\form\account_form
 */
final class lib_test extends \advanced_testcase {
    /**
     * Templates and their accounts are renamed in place; blank names are ignored.
     */
    public function test_inplace_editable_renames(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $chartid = chart_manager::create_chart('Main', \context_system::instance()->id);
        $accountid = account_manager::add($chartid, 'Bank', 0);

        $editable = qtype_accounting_inplace_editable('chartname', $chartid, ' Renamed ');
        $this->assertSame('Renamed', chart_manager::get_chart($chartid)->name);
        $this->assertSame('Renamed', $editable->export_for_template($PAGE->get_renderer('core'))['value']);

        qtype_accounting_inplace_editable('accountname', $accountid, '1200 Bank');
        qtype_accounting_inplace_editable('accountname', $accountid, '   ');
        $this->assertSame(['1200 Bank'], array_column(account_manager::get_for_chart($chartid), 'accountname'));
    }

    /**
     * Only site administrators can rename templates.
     */
    public function test_inplace_editable_requires_capability(): void {
        $this->resetAfterTest();
        $chartid = chart_manager::create_chart('Main', \context_system::instance()->id);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        qtype_accounting_inplace_editable('chartname', $chartid, 'Renamed');
    }

    /**
     * The modal form appends an account and rejects duplicates.
     */
    public function test_account_form_adds_accounts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $chartid = chart_manager::create_chart('Main', \context_system::instance()->id);
        account_manager::add($chartid, 'Bank', 5);

        $form = new form\account_form(null, null, 'post', '', [], true, form\account_form::mock_ajax_submit([
            'chartid' => $chartid,
            'accountname' => ' Cash ',
        ]));
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();
        $accounts = account_manager::get_for_chart($chartid, 'sortorder');
        $this->assertSame(['Bank', 'Cash'], array_values(array_column($accounts, 'accountname')));
        $this->assertEquals(6, end($accounts)->sortorder);

        $form = new form\account_form(null, null, 'post', '', [], true, form\account_form::mock_ajax_submit([
            'chartid' => $chartid,
            'accountname' => 'Cash',
        ]));
        $this->assertFalse($form->is_validated());
    }
}
