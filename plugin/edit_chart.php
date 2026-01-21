<?php
// This file is part of MoFT BuSa - Moodle Question Type Buchungssatz.
//
// MoFT BuSa is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// MoFT BuSa is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with MoFT BuSa.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Edit chart of accounts page.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use qtype_buchungssatz\chart_manager;

require_login();

$chartid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$accountid = optional_param('accountid', 0, PARAM_INT);

$context = context_system::instance();
require_capability('qtype/buchungssatz:managecharts', $context);

// Get the chart.
$chart = chart_manager::get_chart($chartid);
if (!$chart) {
    throw new moodle_exception('chartnotfound', 'qtype_buchungssatz');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/question/type/buchungssatz/edit_chart.php', ['id' => $chartid]));
$PAGE->set_title(get_string('editchart', 'qtype_buchungssatz') . ': ' . $chart->name);
$PAGE->set_heading(get_string('editchart', 'qtype_buchungssatz') . ': ' . $chart->name);

$manageurl = new moodle_url('/question/type/buchungssatz/manage_charts.php');
$PAGE->navbar->add(get_string('managecharts', 'qtype_buchungssatz'), $manageurl);
$PAGE->navbar->add($chart->name);

// Account types for dropdown.
$accounttypes = [
    'asset' => get_string('accounttype_asset', 'qtype_buchungssatz'),
    'liability' => get_string('accounttype_liability', 'qtype_buchungssatz'),
    'equity' => get_string('accounttype_equity', 'qtype_buchungssatz'),
    'revenue' => get_string('accounttype_revenue', 'qtype_buchungssatz'),
    'expense' => get_string('accounttype_expense', 'qtype_buchungssatz'),
];

// Handle actions.
switch ($action) {
    case 'add':
        if (data_submitted() && confirm_sesskey()) {
            $accountnumber = required_param('accountnumber', PARAM_TEXT);
            $accountname = required_param('accountname', PARAM_TEXT);
            $accounttype = required_param('accounttype', PARAM_ALPHA);

            chart_manager::add_account($chartid, $accountnumber, $accountname, $accounttype);
            redirect($PAGE->url, get_string('accountadded', 'qtype_buchungssatz'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        break;

    case 'edit':
        if ($accountid && data_submitted() && confirm_sesskey()) {
            $accountnumber = required_param('accountnumber', PARAM_TEXT);
            $accountname = required_param('accountname', PARAM_TEXT);
            $accounttype = required_param('accounttype', PARAM_ALPHA);

            chart_manager::update_account($accountid, $accountnumber, $accountname, $accounttype);
            redirect($PAGE->url, get_string('accountupdated', 'qtype_buchungssatz'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        break;

    case 'delete':
        if ($accountid && confirm_sesskey()) {
            chart_manager::delete_account($accountid);
            redirect($PAGE->url, get_string('accountdeleted', 'qtype_buchungssatz'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        break;
}

echo $OUTPUT->header();

// Back link.
echo '<p><a href="' . $manageurl->out() . '">&laquo; ' . get_string('managecharts', 'qtype_buchungssatz') . '</a></p>';

// Add account form.
echo $OUTPUT->heading(get_string('addaccount', 'qtype_buchungssatz'), 3);
echo '<form method="post" class="mb-4">';
echo '<input type="hidden" name="action" value="add">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<div class="row">';
echo '<div class="col-md-2">';
echo '<input type="text" class="form-control" name="accountnumber" placeholder="' .
     get_string('accountnumber', 'qtype_buchungssatz') . '" required>';
echo '</div>';
echo '<div class="col-md-4">';
echo '<input type="text" class="form-control" name="accountname" placeholder="' .
     get_string('accountname', 'qtype_buchungssatz') . '" required>';
echo '</div>';
echo '<div class="col-md-3">';
echo '<select class="form-control" name="accounttype" required>';
foreach ($accounttypes as $value => $label) {
    echo '<option value="' . $value . '">' . $label . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div class="col-md-3">';
echo '<button type="submit" class="btn btn-primary">' . get_string('addaccount', 'qtype_buchungssatz') . '</button>';
echo '</div>';
echo '</div>';
echo '</form>';

// Display existing accounts.
echo $OUTPUT->heading(get_string('accounts', 'qtype_buchungssatz'), 3);

$accounts = chart_manager::get_accounts($chartid);

if (empty($accounts)) {
    echo $OUTPUT->notification(get_string('noaccounts', 'qtype_buchungssatz'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('accountnumber', 'qtype_buchungssatz'),
        get_string('accountname', 'qtype_buchungssatz'),
        get_string('accounttype', 'qtype_buchungssatz'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($accounts as $account) {
        $editaccountid = optional_param('edit', 0, PARAM_INT);

        if ($editaccountid == $account->id) {
            // Show edit form inline.
            $row = [
                '<form method="post" class="form-inline">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="accountid" value="' . $account->id . '">
                    <input type="hidden" name="sesskey" value="' . sesskey() . '">
                    <input type="text" class="form-control form-control-sm" name="accountnumber"
                           value="' . s($account->accountnumber) . '" required style="width:100px">',
                '<input type="text" class="form-control form-control-sm" name="accountname"
                        value="' . s($account->accountname) . '" required style="width:200px">',
                '<select class="form-control form-control-sm" name="accounttype" required>',
                '<button type="submit" class="btn btn-sm btn-success">' . get_string('save') . '</button>
                 <a href="' . $PAGE->url->out() . '" class="btn btn-sm btn-secondary">' . get_string('cancel') . '</a>
                 </form>',
            ];

            // Build accounttype select.
            $typeselect = '<select class="form-control form-control-sm" name="accounttype" required>';
            foreach ($accounttypes as $value => $label) {
                $selected = ($account->accounttype == $value) ? ' selected' : '';
                $typeselect .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
            }
            $typeselect .= '</select>';
            $row[2] = $typeselect;

        } else {
            // Show view mode.
            $editurl = new moodle_url($PAGE->url, ['edit' => $account->id]);
            $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'accountid' => $account->id, 'sesskey' => sesskey()]);

            $actions = [];
            $actions[] = '<a href="' . $editurl->out() . '" class="btn btn-sm btn-outline-primary">' .
                         get_string('edit') . '</a>';
            $actions[] = '<a href="' . $deleteurl->out() . '" class="btn btn-sm btn-outline-danger" ' .
                         'onclick="return confirm(\'' . get_string('confirmdeleteaccount', 'qtype_buchungssatz') . '\')">' .
                         get_string('delete') . '</a>';

            $row = [
                s($account->accountnumber),
                s($account->accountname),
                $accounttypes[$account->accounttype] ?? $account->accounttype,
                implode(' ', $actions),
            ];
        }

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
