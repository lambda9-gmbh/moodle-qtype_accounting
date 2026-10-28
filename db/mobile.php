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

/**
 * Moodle App question handler.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'qtype_accounting' => [
        'handlers' => [
            'accounting' => [
                'delegate' => 'CoreQuestionDelegate',
                'method' => 'mobile_get_accounting',
                'displaydata' => [
                    'title' => 'Accounting Entry',
                    'icon' => '/question/type/accounting/pix/icon.svg',
                ],
                'offlinefunctions' => [
                    'mobile_get_accounting' => [],
                ],
            ],
        ],
        'lang' => array_map(static fn($key) => [$key, 'qtype_accounting'], [
            'pluginname', 'debitentries', 'creditentries', 'account', 'amount',
            'adddebitentry', 'addcreditentry', 'deleteentry',
        ]),
    ],
];
