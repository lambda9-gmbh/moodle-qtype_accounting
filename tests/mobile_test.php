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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

namespace qtype_accounting;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the app content served by Moodle, including Mustache/Angular interoperability.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting\output\mobile
 */
final class mobile_test extends \advanced_testcase {
    /**
     * Angular expressions must survive server-side Mustache rendering.
     */
    public function test_app_template_preserves_angular_expressions(): void {
        $content = \qtype_accounting\output\mobile::mobile_get_accounting();

        $html = $content['templates'][0]['html'];
        $this->assertStringContainsString('{{ side.title | translate }}', $html);
        $this->assertStringContainsString('{{ account.label }}', $html);
        $this->assertStringContainsString('(ionInput)="entry.amount = $event.detail.value', $html);
        $this->assertStringContainsString('type="hidden" [attr.name]="entry.accountName"', $html);
        $this->assertStringContainsString('type="hidden" [attr.name]="entry.amountName"', $html);
        $this->assertStringContainsString('<ion-select ', $html);
        $this->assertStringContainsString('<ion-input ', $html);
        $this->assertStringContainsString('componentInit()', $content['javascript']);
    }
}
