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
 * English language strings for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Accounting Entry (Buchungssatz)';
$string['pluginname_help'] = 'A question type where students create accounting entries (journal entries) by selecting debit and credit accounts and entering amounts.';
$string['pluginname_link'] = 'question/type/buchungssatz';
$string['pluginnameadding'] = 'Adding an Accounting Entry question';
$string['pluginnameediting'] = 'Editing an Accounting Entry question';
$string['pluginnamesummary'] = 'A question type for practicing accounting entries (Buchungssätze). Students select accounts from a chart of accounts and enter debit/credit amounts.';

// Form labels.
$string['chartofaccounts'] = 'Chart of Accounts';
$string['chartofaccounts_help'] = 'Select the chart of accounts that students will use to select accounts for their entries.';
$string['nochartselected'] = '-- No chart selected --';
$string['allowmultipleentries'] = 'Allow multiple entries';
$string['allowmultipleentries_help'] = 'If enabled, students can enter multiple booking lines (e.g., for compound journal entries).';
$string['maxentries'] = 'Maximum entries';
$string['correctanswer'] = 'Correct Answer';
$string['entry'] = 'Entry {no}';
$string['addentry'] = 'Add Entry';
$string['deleteentry'] = 'Delete';

// Account fields.
$string['soll'] = 'Debit';
$string['haben'] = 'Credit';
$string['account'] = 'Account';
$string['accountnumber'] = 'Account number';
$string['amount'] = 'Amount';
$string['selectaccount'] = '-- Select account --';
$string['noaccountselected'] = 'No account selected';
$string['sollamount'] = 'Debit amount';
$string['habenamount'] = 'Credit amount';
$string['grade'] = 'Grade (%)';
$string['grade_help'] = 'The percentage of total marks this entry is worth. All entry grades must sum to exactly 100%.';
$string['explanation'] = 'Explanation';
$string['explanation_help'] = 'An optional explanation for this entry that will be shown to students when reviewing the correct answer.';

// Feedback.
$string['correctansweris'] = 'The correct answer is:';
$string['pleaseenteranswer'] = 'Please enter at least one complete booking entry.';

// Validation errors.
$string['err_noentries'] = 'Please enter at least one booking entry with account and amount.';
$string['err_sollrequired'] = 'Debit account is required when credit account is specified.';
$string['err_habenrequired'] = 'Credit account is required when debit account is specified.';
$string['err_negativeamount'] = 'Amounts must be positive.';
$string['err_minentries'] = 'Maximum entries must be at least 1.';
$string['err_maxentries'] = 'Maximum entries cannot exceed {$a}.';
$string['err_habenamountrequired'] = 'Credit amount is required.';
$string['err_sollbetragrequired'] = 'Debit amount is required when a debit account is selected.';
$string['err_graderequired'] = 'Grade is required.';
$string['err_gradeinvalid'] = 'Grade must be between 0 and 100.';
$string['err_gradesumnotcomplete'] = 'The sum of all grades must equal exactly 100%. Current sum: {$a}%';

// Chart of accounts management.
$string['managecharts'] = 'Manage Charts of Accounts';
$string['addchart'] = 'Add new chart';
$string['editchart'] = 'Edit chart';
$string['deletechart'] = 'Delete chart';
$string['chartname'] = 'Chart name';
$string['chartdescription'] = 'Description';
$string['importaccounts'] = 'Import accounts from CSV';
$string['exportaccounts'] = 'Export accounts to CSV';
$string['accounttype'] = 'Account type';
$string['accounttype_asset'] = 'Asset';
$string['accounttype_liability'] = 'Liability';
$string['accounttype_equity'] = 'Equity';
$string['accounttype_revenue'] = 'Revenue';
$string['accounttype_expense'] = 'Expense';

// Privacy.
$string['privacy:metadata'] = 'The Buchungssatz question type plugin does not store any personal data.';

// Capabilities.
$string['buchungssatz:managecharts'] = 'Manage charts of accounts';

// Settings.
$string['settings'] = 'Buchungssatz settings';
$string['defaultchart'] = 'Default chart of accounts';
$string['defaultchart_desc'] = 'The default chart of accounts to use for new questions.';

// Chart management.
$string['nocharts'] = 'No charts of accounts have been created yet.';
$string['accounts'] = 'Accounts';
$string['chartcreated'] = 'Chart of accounts created successfully.';
$string['chartdeleted'] = 'Chart of accounts deleted successfully.';
$string['defaultchartcreated'] = 'Default SKR03 chart created successfully.';
$string['imported'] = '{$a} accounts imported successfully.';
$string['confirmdelete'] = 'Are you sure you want to delete this chart of accounts?';
$string['balanced'] = 'Balanced';

// Account management.
$string['editaccounts'] = 'Edit Accounts';
$string['addaccount'] = 'Add Account';
$string['noaccounts'] = 'No accounts in this chart yet.';
$string['accountadded'] = 'Account added successfully.';
$string['accountupdated'] = 'Account updated successfully.';
$string['accountdeleted'] = 'Account deleted successfully.';
$string['confirmdeleteaccount'] = 'Are you sure you want to delete this account?';
$string['accountname'] = 'Account name';

// CSV Import.
$string['importfromcsv'] = 'Import from Excel/CSV';
$string['importhelp'] = 'Upload a CSV file with accounting data. Supported formats:<br>
<strong>Full format:</strong> Debit Account, Debit Name, Debit Amount, Credit Account, Credit Name, Credit Amount<br>
<strong>Compact format:</strong> Debit Account, Debit Amount, Credit Account, Credit Amount<br>
Use tab, semicolon, or comma as delimiter. German number format (1.234,56) is supported.';
$string['csvdata'] = 'CSV/Excel data';
$string['csvfile'] = 'CSV file';
$string['importentries'] = 'Import Entries';
$string['nocsverror'] = 'The CSV file is empty.';
$string['nofileselected'] = 'No file selected';
$string['filereaderror'] = 'Error reading the file. Please try again.';
$string['importsuccess'] = 'Import successful! ';
$string['entriesimported'] = 'entries imported.';
$string['importerror'] = 'Import error: ';
$string['csvempty'] = 'CSV data is empty.';
$string['csvnodata'] = 'CSV must contain at least a header row and one data row.';
$string['csvinvalidformat'] = 'Could not detect debit and credit account columns. Please check the CSV format.';
$string['csvnoentries'] = 'No valid entries found in CSV data.';
$string['importedchart'] = 'Imported Chart';
$string['importedchartdesc'] = 'Chart of accounts imported from CSV data.';
$string['autoCalculatedFromFractions'] = 'Auto-calculated from the sum of entry points';
$string['selectDebitAccountFirst'] = 'Please select a debit account first';
$string['choosefile'] = 'Choose File';
$string['distributegradesequally'] = 'Distribute equally';

// Chart import from CSV.
$string['importaccountsfromcsv'] = 'Import accounts from CSV (optional)';
$string['csvfilehelp'] = 'Upload a CSV file to import accounts. Format: accountnumber,accountname,accounttype. Leave empty to create an empty chart.';
$string['chartimportsuccess'] = '{$a} accounts imported successfully';
$string['witherrors'] = 'with errors';
$string['chartimportfailed'] = 'Chart import failed';
$string['importlineerror'] = 'Error on line {$a}';
$string['createdefaultskr03'] = 'Create Default SKR03 Chart';
