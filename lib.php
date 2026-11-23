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
 * Library callbacks of qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_accounting\account_manager;
use qtype_accounting\chart_manager;
use qtype_accounting\local\chart_page;

/**
 * Rename a chart of accounts template or one of its accounts in place.
 *
 * @param string $itemtype 'chartname' or 'accountname'.
 * @param int $itemid The chart or account ID.
 * @param string $newvalue The new name.
 * @return \core\output\inplace_editable The updated editable.
 */
function qtype_accounting_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB;

    $context = \context_system::instance();
    \core_external\external_api::validate_context($context);
    require_capability('moodle/site:config', $context);

    $newvalue = trim(clean_param($newvalue, PARAM_TEXT));
    if ($itemtype === 'chartname') {
        $chart = chart_page::get_template((int)$itemid);
        if ($newvalue !== '') {
            if (\core_text::strlen($newvalue) > 255) {
                throw new \moodle_exception('maximumchars', '', '', 255);
            }
            chart_manager::update_chart((int)$chart->id, $newvalue);
        }
        return chart_page::chart_name_editable(chart_page::get_template((int)$chart->id));
    }
    if ($itemtype === 'accountname') {
        $account = $DB->get_record('qtype_accounting_accounts', ['id' => (int)$itemid], '*', MUST_EXIST);
        chart_page::get_template((int)$account->chartid);
        if ($newvalue !== '' && $newvalue !== $account->accountname) {
            account_manager::update((int)$account->id, $newvalue);
            chart_manager::accounts_changed((int)$account->chartid);
        }
        return chart_page::account_name_editable(
            $DB->get_record('qtype_accounting_accounts', ['id' => (int)$account->id], '*', MUST_EXIST)
        );
    }
    throw new \coding_exception('Unknown item type: ' . $itemtype);
}
