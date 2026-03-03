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
$string['chartofaccounts_section'] = 'Chart of Accounts';
$string['accountsindropdown'] = 'Number of additional accounts in selection list';
$string['accountsindropdown_help'] = 'The number of random additional accounts to show in the dropdown selection list alongside the correct account. Set to 0 to show all accounts from the selected chart. For example, entering 3 will show the correct account plus 3 random accounts (4 total).';
$string['numberformat'] = 'Number format';
$string['currency_symbol'] = 'Currency Symbol';
$string['decimalplaces'] = 'Decimal places';
$string['decimalplaces_help'] = 'The number of decimal places to display for amounts in both the teacher and student view.';
$string['extraentrydeduction'] = 'Deduction for extra entries';
$string['extraentrydeduction_help'] = 'Points deducted for each extra entry the student provides beyond the correct answer. An extra line with both debit and credit filled counts as 2 extra entries. Leave empty for no deduction. The score cannot go below 0. (Feature not yet implemented)';
$string['allornothinggrading'] = 'Only award marks if all entries are correct';
$string['allornothinggrading_help'] = 'If enabled, the student only receives marks if all booking entries are completely correct. If there is any error (even just one amount), the entire question is graded as 0 points.';
$string['numberformat_help'] = 'Select the number format for displaying amounts. German/EU format uses dot as thousands separator and comma as decimal separator (1.234,56). US format uses comma as thousands separator and dot as decimal separator (1,234.56).';
$string['numberformat_de'] = 'German/EU (1.234,56)';
$string['numberformat_us'] = 'US (1,234.56)';
$string['nochartselected'] = '-- No chart selected --';
$string['allowmultipleentries'] = 'Allow multiple entries';
$string['allowmultipleentries_help'] = 'If enabled, students can enter multiple booking lines (e.g., for compound journal entries).';
$string['maxentries'] = 'Maximum entries';
$string['correctanswer'] = 'Correct Answer';
$string['entry'] = 'Entry {no}';
$string['addentry'] = 'Add Entry';
$string['adddebitentry'] = 'Add Debit Entry';
$string['addcreditentry'] = 'Add Credit Entry';
$string['deleteentry'] = 'Delete';

// Account fields.
$string['per'] = 'Per';
$string['an'] = 'to';
$string['soll'] = 'Debit';
$string['haben'] = 'Credit';
$string['account'] = 'Account';
$string['sollkonto'] = 'Debit Account';
$string['sollbetrag'] = 'Debit Amount';
$string['habenkonto'] = 'Credit Account';
$string['habenbetrag'] = 'Credit Amount';
$string['weight'] = 'Weight';
$string['weight_help'] = 'Weight for this field in grading (1, 2, or 3). Higher values mean more points. For example, if an account has weight 3 and its amount has weight 1, the account is three times as important as the amount for scoring.';
$string['accountnumber'] = 'Account name';
$string['amount'] = 'Amount';
$string['selectaccount'] = '-- Select account --';
$string['enteraccount'] = 'Enter account number';
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
$string['allcorrect'] = 'All entries are correct!';
$string['debitincorrect'] = 'The debit side (Soll) is incorrect.';
$string['debitpartiallyincorrect'] = 'The debit side (Soll) is partially incorrect.';
$string['debithasextraaccounts'] = 'The debit side (Soll) contains unnecessary accounts.';
$string['creditincorrect'] = 'The credit side (Haben) is incorrect.';
$string['creditpartiallyincorrect'] = 'The credit side (Haben) is partially incorrect.';
$string['credithasextraaccounts'] = 'The credit side (Haben) contains unnecessary accounts.';

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
$string['err_chartrequired'] = 'Please select a chart of accounts.';
$string['err_accountsindropdown_negative'] = 'The number of accounts in selection list cannot be negative.';

// Chart of accounts management.
$string['managecharts'] = 'Manage Charts of Accounts';
$string['addchart'] = 'Add new chart';
$string['editchart'] = 'Edit chart';
$string['deletechart'] = 'Delete chart';
$string['chartname'] = 'Chart name';
$string['chartdescription'] = 'Description';
$string['importaccounts'] = 'Import accounts from CSV';
$string['exportaccounts'] = 'Export accounts to CSV';

// Privacy.
$string['privacy:metadata:qtype_buchungssatz_charts'] = 'The charts of accounts table stores who last modified each chart.';
$string['privacy:metadata:qtype_buchungssatz_charts:usermodified'] = 'The ID of the user who last modified the chart of accounts.';

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
$string['chartrenamed'] = 'Chart of accounts renamed successfully.';
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
$string['chartnotfound'] = 'Chart of accounts not found.';
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
$string['filereaderror'] = 'Error reading the file. Please try again.';
$string['importsuccess'] = 'Import successful! ';
$string['entriesimported'] = 'entries imported.';
$string['importerror'] = 'Import error: ';
$string['csvempty'] = 'CSV data is empty.';
$string['csvnodata'] = 'No account names found. Each non-empty line is treated as one account name.';
$string['csvinvalidformat'] = 'Could not parse the account data. Each line should be one account name.';
$string['csvnoentries'] = 'No valid entries found in CSV data.';
$string['importedchart'] = 'Imported Chart';
$string['importedchartdesc'] = 'Chart of accounts imported from CSV data.';
$string['autoCalculatedFromFractions'] = 'Auto-calculated from the sum of entry points';
$string['selectDebitAccountFirst'] = 'Please select a debit account first';
$string['distributegradesequally'] = 'Distribute equally';

// Chart import from CSV.
$string['importchartfromcsv'] = 'Import Chart of Accounts from CSV';
$string['importchart'] = 'Import Chart';
$string['csvfilerequired'] = 'A CSV file is required to create a chart of accounts.';
$string['csvfilehelp'] = 'Upload a text file with one account name per line. The chart name is derived from the filename or auto-generated.';
$string['csvfile_help'] = 'Upload a text file containing account names.<br><br>
<strong>Format:</strong> One account name per line.<br>
Empty lines are ignored. Duplicate names are skipped.<br><br>
Example:<br>
<code>1200 Bank</code><br>
<code>8400 Erlöse 19% USt</code><br>
<code>1000 Kasse</code><br><br>
Maximum file size: 2MB';
$string['overrideexisting'] = 'Override existing chart';
$string['overrideexistingdesc'] = 'Replace existing chart with the same name';
$string['overrideexisting_help'] = 'If enabled and a chart with the same name already exists, it will be deleted and replaced with the imported chart. Warning: This will permanently delete the existing chart and all its accounts.';
$string['chartexists_enableoverride'] = 'A chart named "{$a}" already exists. Enable "Override existing chart" to replace it.';
$string['chartimportsuccess'] = '{$a} accounts imported successfully';
$string['witherrors'] = 'with errors';
$string['chartimportfailed'] = 'Chart import failed';
$string['importlineerror'] = 'Error on line {$a}';
$string['importdate'] = 'Import date';
$string['uploadchartcsv'] = 'Upload Chart of Accounts (CSV)';
$string['uploadchartcsv_btn'] = 'Upload';
$string['uploadchartcsv_help'] = 'Upload a text file to create a new chart of accounts for this course. The chart will appear in the dropdown immediately after upload. Format: one account name per line.';
$string['saveandcontinue'] = 'Save and continue';
