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
 * English language strings for qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['account'] = 'Account';
$string['accountadded'] = 'Account added successfully.';
$string['accountdeleted'] = 'Account deleted successfully.';
$string['accountdeletewarning'] = 'Deleting this account can leave existing questions with missing accounts and affect grading and the display of saved attempts. This action cannot be undone. Review any affected questions before proceeding.';
$string['accounting:managecharts'] = 'Manage charts of accounts';
$string['accountname'] = 'Account name';
$string['accountnumber'] = 'Account name';
$string['accounts'] = 'Accounts';
$string['accountsindropdown'] = 'Number of additional accounts in selection list';
$string['accountsindropdown_help'] = 'The number of random additional accounts to show in the dropdown selection list alongside the correct account. All includes every account from the selected chart. Leaving the field empty or entering 0 also selects All. For example, entering 3 will show the correct account plus 3 random accounts (4 total).';
$string['accountsonlyexception'] = 'Points mode: one point for correct accounts without amounts';
$string['accountsonlyexception_desc'] = 'If enabled, a student who names exactly the correct accounts on both sides (none missing, none extra) but enters no amounts at all receives one point in points mode, instead of no points when only fully correct pairs count. The teacher\'s correct answer always contains accounts and amounts.';
$string['accountupdated'] = 'Account updated successfully.';
$string['accountusagewarning'] = 'This account is used by the following questions or their saved attempts:';
$string['addaccount'] = 'Add Account';
$string['addchart'] = 'Add new chart';
$string['addcreditentry'] = 'Add Credit Entry';
$string['adddebitentry'] = 'Add Debit Entry';
$string['addentry'] = 'Add Entry';
$string['allcorrect'] = 'All entries are correct!';
$string['allornothinggrading'] = 'No partial credit';
$string['allornothinggrading_help'] = 'If enabled, the student only receives marks if all booking entries are completely correct. If there is any error (even just one amount), the entire question is graded as 0 points.';
$string['allornothinggrading_notice'] = 'Note: No partial credit is awarded for this question. All entries must be completely correct to receive marks.';
$string['allowmultipleentries'] = 'Allow multiple entries';
$string['allowmultipleentries_help'] = 'If enabled, students can enter multiple booking lines (e.g., for compound journal entries).';
$string['amount'] = 'Amount';
$string['amountminwidth'] = 'Minimum width of amount fields';
$string['amountminwidth_desc'] = 'Minimum width of the amount input fields in the booking table, in pixels.';
$string['an'] = 'Cr.';
$string['autocalculatedfromfractions'] = 'Auto-calculated from the sum of entry points';
$string['balanced'] = 'Balanced';
$string['chartcopied'] = 'Chart of accounts copied to this course. You can now adapt it.';
$string['chartcreated'] = 'Chart of accounts created successfully.';
$string['chartdeleted'] = 'Chart of accounts deleted successfully.';
$string['chartdescription'] = 'Description';
$string['chartexists_enableoverride'] = 'A chart named "{$a}" already exists. Enable "Override existing chart" to replace it.';
$string['chartimportfailed'] = 'Chart import failed';
$string['chartimportsuccess'] = '{$a} accounts imported successfully';
$string['chartname'] = 'Chart name';
$string['chartnotfound'] = 'Chart of accounts not found.';
$string['chartofaccounts'] = 'Chart of Accounts';
$string['chartofaccounts_help'] = 'Select the chart of accounts that students will use to select accounts for their entries.';
$string['chartofaccounts_section'] = 'Chart of Accounts';
$string['chartoverlaydiscard'] = 'Discard unsaved edits';
$string['chartoverlaydone'] = 'Done';
$string['chartoverlayhint'] = 'Changes also apply to other questions using the same chart. Choose Done to return to your question.';
$string['chartoverlaysaving'] = 'Please wait until your changes have finished saving.';
$string['chartoverlayunsaved'] = 'Discard your unfinished edits and return to the question? Changes already saved will be kept.';
$string['chartoverlayunsavedtitle'] = 'Unsaved chart changes';
$string['chartrenamed'] = 'Chart of accounts renamed successfully.';
$string['confirmdelete'] = 'Are you sure you want to permanently delete the chart of accounts "{$a}"? All accounts in this chart will also be deleted. Existing questions may stop working correctly, and grading and the display of saved attempts may be affected. This action cannot be undone. Review any affected questions before proceeding.';
$string['confirmdeleteaccount'] = 'Are you sure you want to delete this account?';
$string['copytocourse'] = 'Copy to this course';
$string['correctanswer'] = 'Correct Answer';
$string['correctansweris'] = 'The correct answer is:';
$string['createchart'] = 'Create chart of accounts';
$string['createchart_help'] = 'Enter a name to create an empty chart of accounts. You can then add accounts directly in Moodle or import them from a CSV or ODS file.';
$string['credit'] = 'Credit';
$string['creditaccount'] = 'Credit Account';
$string['creditamount'] = 'Credit Amount';
$string['creditentries'] = 'Credit Entries';
$string['credithasextraaccounts'] = 'The credit side contains unnecessary accounts.';
$string['creditincorrect'] = 'The credit side is incorrect.';
$string['creditpartiallyincorrect'] = 'The credit side is partially incorrect.';
$string['csvdata'] = 'CSV/Excel data';
$string['csvempty'] = 'CSV data is empty.';
$string['csvfile'] = 'CSV, text or ODS file';
$string['csvfile_help'] = 'Upload a CSV, text or ODS file containing account names.<br><br>
<strong>CSV/text:</strong> One account name per line.<br>
<strong>ODS:</strong> One account name per row in column A of the first sheet. Other columns and sheets are ignored.<br>
Empty lines are ignored. Duplicate names are skipped.<br><br>
Example:<br>
<code>1200 Bank</code><br>
<code>8400 Erlöse 19% USt</code><br>
<code>1000 Kasse</code><br><br>
Maximum file size: 2MB<br><br>
<strong>Supported encodings:</strong> UTF-8 and Windows-1252 (common Excel export).';
$string['csvfilehelp'] = 'Upload a text file with one account name per line. The chart name is derived from the filename or auto-generated.';
$string['csvfilerequired'] = 'A CSV, text or ODS file is required for import.';
$string['csvinvalidformat'] = 'Could not parse the account data. Each line should be one account name.';
$string['csvnodata'] = 'No account names found. Each non-empty line is treated as one account name.';
$string['csvnoentries'] = 'No valid account names found in the file.';
$string['currency'] = 'Currency';
$string['currency_chf'] = 'CHF (Swiss franc)';
$string['currency_eur'] = '€ (Euro)';
$string['currency_gbp'] = '£ (Pound sterling)';
$string['currency_help'] = 'The currency symbol shown after the amount fields. Choose "No symbol" to show plain amounts.';
$string['currency_none'] = 'No symbol';
$string['currency_usd'] = '$ (US dollar)';
$string['debit'] = 'Debit';
$string['debitaccount'] = 'Debit Account';
$string['debitamount'] = 'Debit Amount';
$string['debitentries'] = 'Debit Entries';
$string['debithasextraaccounts'] = 'The debit side contains unnecessary accounts.';
$string['debitincorrect'] = 'The debit side is incorrect.';
$string['debitpartiallyincorrect'] = 'The debit side is partially incorrect.';
$string['defaultcurrency'] = 'Default currency';
$string['defaultcurrency_desc'] = 'Currency preselected for new questions. Its symbol is also shown for existing questions that have no currency saved.';
$string['defaultgradingmode'] = 'Default grading mode';
$string['defaultgradingmode_desc'] = 'Grading mode preselected for new accounting questions. Teachers can change it per question.';
$string['deleteaccount'] = 'Delete account';
$string['deletechart'] = 'Delete chart';
$string['deleteentry'] = 'Delete';
$string['distributegradesequally'] = 'Distribute equally';
$string['editaccounts'] = 'Edit Accounts';
$string['editchart'] = 'Edit chart';
$string['enteraccount'] = 'Enter account number';
$string['entriesimported'] = 'entries imported.';
$string['entry'] = 'Entry {no}';
$string['entryrows'] = 'Number of entry rows';
$string['err_accountsindropdown'] = 'Enter All or a non-negative whole number.';
$string['err_accountsindropdown_negative'] = 'The number of accounts in selection list cannot be negative.';
$string['err_accountunavailable'] = 'This chart or account is no longer available. Please choose a replacement.';
$string['err_balancemismatch'] = 'Total debit amount must equal total credit amount for a valid accounting entry.';
$string['err_chartdeleted'] = 'The chart of accounts used by this question has been deleted. Please select a replacement and review the accounts before saving.';
$string['err_chartrequired'] = 'Please select a chart of accounts.';
$string['err_creditaccountrequired'] = 'Please select an account, or remove this entry.';
$string['err_creditamountrequired'] = 'Credit amount is required.';
$string['err_creditrequired'] = 'Credit account is required when debit account is specified.';
$string['err_debitaccountrequired'] = 'Please select an account, or remove this entry.';
$string['err_debitamountrequired'] = 'Debit amount is required.';
$string['err_debitrequired'] = 'Debit account is required when credit account is specified.';
$string['err_duplicateaccount'] = 'An account with this name already exists in this chart.';
$string['err_extraentrydeduction_range'] = 'The deduction must be between 0 and 100.';
$string['err_gradeinvalid'] = 'Grade must be between 0 and 100.';
$string['err_graderequired'] = 'Grade is required.';
$string['err_gradesumnotcomplete'] = 'The sum of all grades must equal exactly 100%. Current sum: {$a}%';
$string['err_maxentries'] = 'Maximum entries cannot exceed {$a}.';
$string['err_minentries'] = 'Maximum entries must be at least 1.';
$string['err_negativeamount'] = 'Amounts must be positive.';
$string['err_noentries'] = 'Please enter at least one booking entry with account and amount.';
$string['explanation'] = 'Explanation';
$string['explanation_help'] = 'An optional explanation for this entry that will be shown to students when reviewing the correct answer.';
$string['exportaccounts'] = 'Export accounts to CSV';
$string['extraentrydeduction'] = 'Deduction for extra entries';
$string['extraentrydeduction_help'] = 'Percentage deducted per extra account the student uses that is not part of the correct answer. Each side (debit/credit) is counted independently. Set to "None" for no deduction. The score cannot go below 0.';
$string['extraentrydeductionpoints'] = 'Deduction for extra entries (points)';
$string['extraentrydeductionpoints_help'] = 'Points deducted per extra account the student uses that is not part of the correct answer. Each side (debit/credit) is counted independently. The score cannot go below 0 points.';
$string['extraentrydeductionpoints_option'] = '{$a} point(s) per extra account';
$string['filereaderror'] = 'Error reading the file. Please try again.';
$string['grade'] = 'Grade (%)';
$string['grade_help'] = 'The percentage of total marks this entry is worth. All entry grades must sum to exactly 100%.';
$string['gradingmode'] = 'Grading mode';
$string['gradingmode_help'] = 'Percentage: account and amount of every entry are weighted (weights 1-3) and the result is a percentage of the default mark.

Points: every account-amount pair of the correct answer is worth one point. The default mark is set automatically to the number of pairs. Depending on the site settings, a pair only earns its point if both account and amount are correct.';
$string['gradingmode_percent'] = 'Percentage (weighted)';
$string['gradingmode_points'] = 'Points (one point per account-amount pair)';
$string['gradingmode_points_info'] = 'The default mark is calculated from the correct answer when saving: one point per account-amount pair. If only the account or only the amount is correct, the pair earns a share of its point according to the weights.';
$string['gradingmode_points_info_strict'] = 'The default mark is calculated from the correct answer when saving: one point per account-amount pair. A pair only earns its point if both account and amount are correct, so weights are not used.';
$string['importaccounts'] = 'Import accounts from CSV or ODS';
$string['importchart'] = 'Import Chart';
$string['importchartfromcsv'] = 'Import Chart of Accounts from CSV';
$string['importdate'] = 'Import date';
$string['imported'] = '{$a} accounts imported successfully.';
$string['importedchart'] = 'Imported Chart';
$string['importedchartdesc'] = 'Chart of accounts imported from CSV data.';
$string['importentries'] = 'Import Entries';
$string['importerror'] = 'Import error: ';
$string['importfromcsv'] = 'Import from Excel/CSV';
$string['importhelp'] = 'Upload a CSV file with accounting data. Supported formats:<br>
<strong>Full format:</strong> Debit Account, Debit Name, Debit Amount, Credit Account, Credit Name, Credit Amount<br>
<strong>Compact format:</strong> Debit Account, Debit Amount, Credit Account, Credit Amount<br>
Use tab, semicolon, or comma as delimiter. German number format (1.234,56) is supported.';
$string['importlineerror'] = 'Error on line {$a}';
$string['importsuccess'] = 'Import successful! ';
$string['managecharts'] = 'Manage Charts of Accounts';
$string['managechartslink'] = 'Manage chart of accounts';
$string['maxentries'] = 'Maximum entries';
$string['noaccounts'] = 'No accounts in this chart yet.';
$string['noaccountselected'] = 'No account selected';
$string['nocharts'] = 'No charts of accounts have been created yet.';
$string['nochartselected'] = '-- No chart selected --';
$string['nocsverror'] = 'The CSV file is empty.';
$string['numberformat'] = 'Number format';
$string['numberformat_de'] = '#.###,00';
$string['numberformat_help'] = 'Choose the format for decimal and thousand separators. Either #,###.00 or #.###,00';
$string['numberformat_us'] = '#,###.00';
$string['odsinvalid'] = 'Could not read the ODS spreadsheet. Upload a valid ODS file with uncompressed content no larger than 16 MiB.';
$string['overrideexisting'] = 'Override existing chart';
$string['overrideexisting_help'] = 'If enabled and a chart with the same name already exists, it will be deleted and replaced with the imported chart. Warning: This will permanently delete the existing chart and all its accounts.';
$string['overrideexistingdesc'] = 'Replace existing chart with the same name';
$string['per'] = 'Dr.';
$string['pleaseenteranswer'] = 'Please enter at least one complete booking entry.';
$string['pluginname'] = 'Accounting Entry';
$string['pluginname_help'] = 'A question type where students create accounting entries (journal entries) by selecting debit and credit accounts and entering amounts.';
$string['pluginname_link'] = 'question/type/accounting';
$string['pluginnameadding'] = 'Adding an Accounting Entry question';
$string['pluginnameediting'] = 'Editing an Accounting Entry question';
$string['pluginnamesummary'] = 'A question type for practicing accounting entries. Students select accounts from a chart of accounts and enter debit/credit amounts.';
$string['privacy:metadata:qtype_accounting_charts'] = 'The charts of accounts table stores who last modified each chart.';
$string['privacy:metadata:qtype_accounting_charts:usermodified'] = 'The ID of the user who last modified the chart of accounts.';
$string['refreshchartsdone'] = 'Charts of accounts updated.';
$string['refreshchartsfailed'] = 'Charts of accounts could not be updated. Please try again. Your entries have been preserved.';
$string['refreshchartsmissing'] = 'Charts updated. A selected chart or account is no longer available. Your entries have been preserved; please choose a replacement before saving.';
$string['refreshentries'] = 'Update chart and rows';
$string['selectaccount'] = '-- Select account --';
$string['selectcreditaccountfirst'] = 'Please select a credit account first';
$string['selectdebitaccountfirst'] = 'Please select a debit account first';
$string['settings_display'] = 'Display';
$string['settings_grading'] = 'Grading';
$string['showaccountsindropdown'] = 'Show "Number of additional accounts in selection list"';
$string['showaccountsindropdown_desc'] = 'Offer the option "Number of additional accounts in selection list" in the question editor. If disabled, the option is hidden: new questions show the entire chart of accounts, existing questions keep their saved value.';
$string['showpercolumn'] = 'Show the "Dr." column';
$string['showpercolumn_desc'] = 'Show the leading label column ("Dr.") of the booking table to students and in the question editor. If disabled, the column is hidden.';
$string['sitechart_suffix'] = '{$a} (site default)';
$string['sitecharts'] = 'Site charts of accounts';
$string['sitecharts_add'] = 'Create or upload a site chart of accounts';
$string['sitecharts_course_desc'] = 'These charts of accounts are provided by the site administrator and can be selected in every question. They cannot be changed here; copy a chart into this course to adapt it.';
$string['sitecharts_desc'] = 'Charts of accounts created or uploaded here are available to teachers in every course. Teachers can still create or upload their own charts in their courses.';
$string['strictpairs'] = 'Points mode: only fully correct pairs earn points';
$string['strictpairs_desc'] = 'If enabled, an account-amount pair in points mode earns its point only if both the account and the amount are correct; otherwise neither earns anything. If disabled, the point is split between account and amount according to the weights.';
$string['uploadchartcsv'] = 'Upload Chart of Accounts (CSV or ODS)';
$string['uploadchartcsv_btn'] = 'Upload';
$string['uploadchartcsv_help'] = 'Upload a text file to create a new chart of accounts for this course. The chart will appear in the dropdown immediately after upload. Format: one account name per line.';
$string['weight'] = 'Weight';
$string['weight_help'] = 'Weight for this field in grading (1, 2, or 3). Higher values mean more points. For example, if an account has weight 3 and its amount has weight 1, the account is three times as important as the amount for scoring.';
$string['weight_tooltip'] = 'Input fields (accounts, amounts) can be assigned a weight from 1–3. The weight is used to calculate partial credit as follows: (Σ weights of correct fields) ÷ (Σ weights of all fields) × achievable points.

Important: Account and amount are linked – a correct amount only counts if the associated account is also correct. A correctly entered account without the correct amount is still partially credited.

The weighting is overridden and has no effect when the option "No partial credit" is enabled.';
$string['witherrors'] = 'with errors';
