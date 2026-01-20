<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines the editing form for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Buchungssatz question editing form definition.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_edit_form extends question_edit_form {

    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    protected function definition_inner($mform) {
        global $DB, $PAGE;

        // Chart of accounts selection.
        $charts = $this->get_available_charts();
        $mform->addElement('select', 'chartofaccountsid',
            get_string('chartofaccounts', 'qtype_buchungssatz'), $charts);
        $mform->setType('chartofaccountsid', PARAM_INT);
        $mform->addHelpButton('chartofaccountsid', 'chartofaccounts', 'qtype_buchungssatz');

        // Link to manage charts.
        $manageurl = new moodle_url('/question/type/buchungssatz/manage_charts.php');
        $mform->addElement('static', 'managecharts_link', '',
            '<a href="' . $manageurl->out() . '" target="_blank" id="buchungssatz-manage-charts-link">' .
            get_string('managecharts', 'qtype_buchungssatz') . '</a>');

        // Import from Excel/CSV section.
        $mform->addElement('header', 'importhdr', get_string('importfromcsv', 'qtype_buchungssatz'));
        $mform->setExpanded('importhdr', false);

        $mform->addElement('static', 'importhelp', '',
            '<div class="alert alert-info">' . get_string('importhelp', 'qtype_buchungssatz') . '</div>');

        $mform->addElement('html', '<div class="form-group row fitem">
            <div class="col-md-3 col-form-label d-flex pb-0 pr-md-0">
                <label>' . get_string('csvfile', 'qtype_buchungssatz') . '</label>
            </div>
            <div class="col-md-9 form-inline align-items-start felement">
                <div class="custom-file-input-wrapper" style="display: flex; align-items: center; gap: 10px;">
                    <label for="buchungssatz-csv-file" class="btn btn-outline-secondary mb-0" style="cursor: pointer;">
                        ' . get_string('choosefile', 'qtype_buchungssatz') . '
                    </label>
                    <span id="buchungssatz-csv-filename" style="color: #6c757d; font-size: 0.9em;">' . get_string('nofileselected', 'qtype_buchungssatz') . '</span>
                    <input type="file" id="buchungssatz-csv-file" accept=".csv,.txt" style="display: none;">
                </div>
            </div>
        </div>');

        $mform->addElement('html', '<div class="form-group row fitem">
            <div class="col-md-3"></div>
            <div class="col-md-9">
                <button type="button" class="btn btn-secondary" id="buchungssatz-import-btn">' .
                get_string('importentries', 'qtype_buchungssatz') . '</button>
            </div>
        </div>');

        // Correct answer entries section.
        $mform->addElement('header', 'answerhdr', get_string('correctanswer', 'qtype_buchungssatz'));
        $mform->setExpanded('answerhdr', true);

        // Get all accounts for dropdowns.
        $allaccounts = $this->get_all_accounts_by_chart();

        // Load existing entries for pre-populating the table.
        // Note: get_records returns associative array keyed by ID, convert to sequential.
        $existingentries = [];
        if (!empty($this->question->options->entries)) {
            $existingentries = array_values($this->question->options->entries);
        }
        $initialrows = max(1, count($existingentries));

        $sollplaceholder = get_string('noaccountselected', 'qtype_buchungssatz');
        $habenplaceholder = get_string('selectaccount', 'qtype_buchungssatz');

        // Build the table-based entry layout.
        $tablehtml = '
        <div class="form-group row fitem buchungssatz-entries-container">
            <div class="col-md-12">
                <div class="buchungssatz-entries-table">
                    <!-- Header Row 1: Soll / Haben -->
                    <div class="buchungssatz-header-main" style="display: flex; border-bottom: 1px solid #dee2e6; background: #f8f9fa;">
                        <div style="flex: 2; text-align: center; font-weight: bold; padding: 0.5rem; border-right: 1px solid #dee2e6;">
                            ' . get_string('soll', 'qtype_buchungssatz') . '
                        </div>
                        <div style="flex: 2; text-align: center; font-weight: bold; padding: 0.5rem; border-right: 1px solid #dee2e6;">
                            ' . get_string('haben', 'qtype_buchungssatz') . '
                        </div>
                        <div style="flex: 0 0 80px; text-align: center; font-weight: bold; padding: 0.5rem;">
                            ' . get_string('fraction', 'qtype_buchungssatz') . '
                        </div>
                        <div style="flex: 0 0 40px;"></div>
                    </div>
                    <!-- Header Row 2: Account / Amount / Account / Amount -->
                    <div class="buchungssatz-header-sub" style="display: flex; border-bottom: 2px solid #dee2e6; background: #f8f9fa;">
                        <div style="flex: 1; text-align: center; font-weight: 600; padding: 0.25rem; font-size: 0.9em;">
                            ' . get_string('account', 'qtype_buchungssatz') . '
                        </div>
                        <div style="flex: 1; text-align: center; font-weight: 600; padding: 0.25rem; font-size: 0.9em; border-right: 1px solid #dee2e6;">
                            ' . get_string('amount', 'qtype_buchungssatz') . '
                        </div>
                        <div style="flex: 1; text-align: center; font-weight: 600; padding: 0.25rem; font-size: 0.9em;">
                            ' . get_string('account', 'qtype_buchungssatz') . '
                        </div>
                        <div style="flex: 1; text-align: center; font-weight: 600; padding: 0.25rem; font-size: 0.9em; border-right: 1px solid #dee2e6;">
                            ' . get_string('amount', 'qtype_buchungssatz') . '
                        </div>
                        <div style="flex: 0 0 80px;"></div>
                        <div style="flex: 0 0 40px;"></div>
                    </div>
                    <!-- Entry rows container -->
                    <div id="buchungssatz-entry-rows">';

        // Generate initial rows.
        for ($i = 0; $i < $initialrows; $i++) {
            $entry = isset($existingentries[$i]) ? $existingentries[$i] : null;
            $sollkonto = $entry ? $entry->sollkonto : '';
            $sollbetrag = $entry ? $entry->sollbetrag : '';
            $habenkonto = $entry ? $entry->habenkonto : '';
            $habenbetrag = $entry ? $entry->habenbetrag : '';
            $fraction = $entry ? $entry->fraction : '1.0';

            $tablehtml .= $this->render_entry_row_html($i, $sollkonto, $sollbetrag, $habenkonto, $habenbetrag, $fraction, $sollplaceholder, $habenplaceholder);
        }

        $tablehtml .= '
                    </div>
                </div>
                <!-- Add Entry Button -->
                <div class="mt-2">
                    <button type="button" class="btn btn-secondary btn-sm" id="buchungssatz-add-entry-btn">
                        ' . get_string('addentry', 'qtype_buchungssatz') . '
                    </button>
                </div>
            </div>
        </div>';

        $mform->addElement('html', $tablehtml);

        // Add hidden field to track number of entries.
        $mform->addElement('hidden', 'entry_repeats', $initialrows);
        $mform->setType('entry_repeats', PARAM_INT);

        // Add hidden form elements that Moodle will process.
        // JavaScript will sync the visible table inputs to these hidden fields.
        $maxentries = 20;
        for ($i = 0; $i < $maxentries; $i++) {
            $sollval = isset($existingentries[$i]) ? $existingentries[$i]->sollkonto : '';
            $sollbval = isset($existingentries[$i]) ? $existingentries[$i]->sollbetrag : '';
            $habenval = isset($existingentries[$i]) ? $existingentries[$i]->habenkonto : '';
            $habenbval = isset($existingentries[$i]) ? $existingentries[$i]->habenbetrag : '';
            $fracval = isset($existingentries[$i]) ? $existingentries[$i]->fraction : '1.0';

            $mform->addElement('hidden', "sollkonto[$i]", $sollval);
            $mform->setType("sollkonto[$i]", PARAM_TEXT);
            $mform->addElement('hidden', "sollbetrag[$i]", $sollbval);
            $mform->setType("sollbetrag[$i]", PARAM_RAW);
            $mform->addElement('hidden', "habenkonto[$i]", $habenval);
            $mform->setType("habenkonto[$i]", PARAM_TEXT);
            $mform->addElement('hidden', "habenbetrag[$i]", $habenbval);
            $mform->setType("habenbetrag[$i]", PARAM_RAW);
            $mform->addElement('hidden', "fraction[$i]", $fracval);
            $mform->setType("fraction[$i]", PARAM_RAW);
        }

        // Add JavaScript for dynamic account dropdowns and other functionality.
        $this->add_entry_javascript($mform, $allaccounts, $sollplaceholder, $habenplaceholder);
    }

    /**
     * Render HTML for a single entry row in the table.
     *
     * @param int $index The row index.
     * @param string $sollkonto The debit account value.
     * @param string $sollbetrag The debit amount value.
     * @param string $habenkonto The credit account value.
     * @param string $habenbetrag The credit amount value.
     * @param string $fraction The points value.
     * @param string $sollplaceholder The placeholder for debit account.
     * @param string $habenplaceholder The placeholder for credit account.
     * @return string The HTML for the row.
     */
    protected function render_entry_row_html(
        int $index,
        string $sollkonto,
        string $sollbetrag,
        string $habenkonto,
        string $habenbetrag,
        string $fraction,
        string $sollplaceholder,
        string $habenplaceholder
    ): string {
        $sollbetragdisabled = empty($sollkonto) ? 'disabled style="background-color: #e9ecef;"' : '';

        return '
        <div class="buchungssatz-entry-row" data-index="' . $index . '" style="display: flex; border-bottom: 1px solid #dee2e6; align-items: center;">
            <div style="flex: 1; padding: 0.5rem;">
                <select class="form-control buchungssatz-sollkonto">
                    <option value="">' . s($sollplaceholder) . '</option>
                </select>
                <input type="hidden" class="sollkonto-value" value="' . s($sollkonto) . '">
            </div>
            <div style="flex: 1; padding: 0.5rem; border-right: 1px solid #dee2e6;">
                <input type="number" value="' . s($sollbetrag) . '"
                    class="form-control buchungssatz-sollbetrag" step="0.01" min="0" placeholder="0.00" ' . $sollbetragdisabled . '>
            </div>
            <div style="flex: 1; padding: 0.5rem;">
                <select class="form-control buchungssatz-habenkonto">
                    <option value="">' . s($habenplaceholder) . '</option>
                </select>
                <input type="hidden" class="habenkonto-value" value="' . s($habenkonto) . '">
            </div>
            <div style="flex: 1; padding: 0.5rem; border-right: 1px solid #dee2e6;">
                <input type="number" value="' . s($habenbetrag) . '"
                    class="form-control buchungssatz-habenbetrag" step="0.01" min="0" placeholder="0.00">
            </div>
            <div style="flex: 0 0 80px; padding: 0.5rem; text-align: center;">
                <input type="text" value="' . s($fraction) . '"
                    class="form-control buchungssatz-fraction" style="width: 60px; text-align: center;">
            </div>
            <div style="flex: 0 0 40px; padding: 0.5rem; text-align: center;">
                <button type="button" class="btn btn-outline-danger btn-sm buchungssatz-delete-row" title="' . get_string('deleteentry', 'qtype_buchungssatz') . '">&times;</button>
            </div>
        </div>';
    }

    /**
     * Add JavaScript for entry field handling.
     *
     * @param MoodleQuickForm $mform The form being built.
     * @param array $allaccounts All accounts grouped by chart ID.
     * @param string $sollplaceholder The placeholder for debit account.
     * @param string $habenplaceholder The placeholder for credit account.
     */
    protected function add_entry_javascript($mform, array $allaccounts, string $sollplaceholder, string $habenplaceholder): void {
        $accountsjson = json_encode($allaccounts);
        $deletetext = get_string('deleteentry', 'qtype_buchungssatz');

        $mform->addElement('html', '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var accountsByChart = ' . $accountsjson . ';
                var chartSelect = document.getElementById("id_chartofaccountsid");
                var sollPlaceholder = ' . json_encode($sollplaceholder) . ';
                var habenPlaceholder = ' . json_encode($habenplaceholder) . ';
                var deleteText = ' . json_encode($deletetext) . ';

                // Function to update account dropdowns based on selected chart
                function updateAccountDropdowns() {
                    var chartId = chartSelect ? chartSelect.value : "0";
                    var accounts = accountsByChart[chartId] || {};

                    // Find all entry rows and update their dropdowns
                    var rows = document.querySelectorAll(".buchungssatz-entry-row");

                    rows.forEach(function(row) {
                        var sollSelect = row.querySelector(".buchungssatz-sollkonto");
                        var habenSelect = row.querySelector(".buchungssatz-habenkonto");
                        var sollHidden = row.querySelector(".sollkonto-value");
                        var habenHidden = row.querySelector(".habenkonto-value");

                        // Update soll dropdown
                        if (sollSelect) {
                            var currentSoll = sollSelect.value || (sollHidden ? sollHidden.value : "");
                            while (sollSelect.options.length > 1) {
                                sollSelect.remove(1);
                            }
                            for (var accountNumber in accounts) {
                                var option = document.createElement("option");
                                option.value = accountNumber;
                                option.text = accounts[accountNumber];
                                sollSelect.add(option);
                            }
                            if (currentSoll) {
                                sollSelect.value = currentSoll;
                            }
                            // Clear hidden value after restoring
                            if (sollHidden) sollHidden.value = "";
                        }

                        // Update haben dropdown
                        if (habenSelect) {
                            var currentHaben = habenSelect.value || (habenHidden ? habenHidden.value : "");
                            while (habenSelect.options.length > 1) {
                                habenSelect.remove(1);
                            }
                            for (var accountNumber in accounts) {
                                var option = document.createElement("option");
                                option.value = accountNumber;
                                option.text = accounts[accountNumber];
                                habenSelect.add(option);
                            }
                            if (currentHaben) {
                                habenSelect.value = currentHaben;
                            }
                            // Clear hidden value after restoring
                            if (habenHidden) habenHidden.value = "";
                        }
                    });

                    updateAllSollbetragStates();
                }

                // Function to update default mark based on sum of fractions
                function updateDefaultMark() {
                    var totalFraction = 0;
                    var fractionInputs = document.querySelectorAll(".buchungssatz-entry-row .buchungssatz-fraction");

                    fractionInputs.forEach(function(input) {
                        var val = parseFloat(input.value);
                        if (!isNaN(val)) {
                            totalFraction += val;
                        }
                    });

                    var defaultMarkInput = document.getElementById("id_defaultmark");
                    if (defaultMarkInput) {
                        defaultMarkInput.value = totalFraction.toFixed(7).replace(/\.?0+$/, "");
                        defaultMarkInput.readOnly = true;
                        defaultMarkInput.style.backgroundColor = "#e9ecef";
                        defaultMarkInput.title = "' . get_string('autoCalculatedFromFractions', 'qtype_buchungssatz') . '";

                        if (!document.getElementById("defaultmark-auto-info")) {
                            var infoSpan = document.createElement("span");
                            infoSpan.id = "defaultmark-auto-info";
                            infoSpan.innerHTML = "<small style=\"color: #6c757d; margin-left: 8px;\">&#9432; ' . get_string('autoCalculatedFromFractions', 'qtype_buchungssatz') . '</small>";
                            defaultMarkInput.parentNode.appendChild(infoSpan);
                        }
                    }
                }

                // Function to update Debit Amount disabled state
                function updateSollbetragState(sollSelect) {
                    var row = sollSelect.closest(".buchungssatz-entry-row");
                    if (!row) return;

                    var sollBetrag = row.querySelector(".buchungssatz-sollbetrag");

                    if (sollBetrag) {
                        var hasAccount = sollSelect.value !== "" && sollSelect.value !== null;
                        sollBetrag.disabled = !hasAccount;
                        sollBetrag.style.backgroundColor = hasAccount ? "" : "#e9ecef";
                        sollBetrag.title = hasAccount ? "" : "' . get_string('selectDebitAccountFirst', 'qtype_buchungssatz') . '";
                        if (!hasAccount) {
                            sollBetrag.value = "";
                        }
                    }
                }

                function updateAllSollbetragStates() {
                    var sollSelects = document.querySelectorAll(".buchungssatz-entry-row .buchungssatz-sollkonto");
                    sollSelects.forEach(function(select) {
                        updateSollbetragState(select);
                    });
                }

                // Event delegation for dynamically added elements
                document.addEventListener("change", function(e) {
                    if (e.target.classList.contains("buchungssatz-sollkonto")) {
                        updateSollbetragState(e.target);
                    }
                    if (e.target.classList.contains("buchungssatz-fraction")) {
                        updateDefaultMark();
                    }
                });

                document.addEventListener("input", function(e) {
                    if (e.target.classList.contains("buchungssatz-fraction")) {
                        updateDefaultMark();
                    }
                });

                // Watch for new elements being added (MutationObserver)
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            updateAccountDropdowns();
                            updateDefaultMark();
                        }
                    });
                });

                var form = document.querySelector("form");
                if (form) {
                    observer.observe(form, { childList: true, subtree: true });
                }

                // Chart selection change handler
                if (chartSelect) {
                    chartSelect.addEventListener("change", updateAccountDropdowns);
                }

                // Initial setup
                updateAccountDropdowns();
                updateDefaultMark();

                // Auto-refresh accounts when returning from chart management
                var manageChartsLink = document.getElementById("buchungssatz-manage-charts-link");
                var chartManagementOpened = false;

                if (manageChartsLink) {
                    manageChartsLink.addEventListener("click", function() {
                        chartManagementOpened = true;
                    });
                }

                document.addEventListener("visibilitychange", function() {
                    if (document.visibilityState === "visible" && chartManagementOpened) {
                        chartManagementOpened = false;
                        refreshAccountsFromServer();
                    }
                });

                window.addEventListener("focus", function() {
                    if (chartManagementOpened) {
                        chartManagementOpened = false;
                        refreshAccountsFromServer();
                    }
                });

                function refreshAccountsFromServer() {
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", M.cfg.wwwroot + "/question/type/buchungssatz/ajax/get_accounts.php?sesskey=" + M.cfg.sesskey, true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            try {
                                accountsByChart = JSON.parse(xhr.responseText);
                                updateAccountDropdowns();
                            } catch (e) {
                                console.error("Failed to parse accounts response", e);
                            }
                        }
                    };
                    xhr.send();
                }

                // Add Entry functionality
                var addEntryBtn = document.getElementById("buchungssatz-add-entry-btn");
                var entryRowsContainer = document.getElementById("buchungssatz-entry-rows");

                function getNextIndex() {
                    var rows = document.querySelectorAll(".buchungssatz-entry-row");
                    var maxIndex = -1;
                    rows.forEach(function(row) {
                        var idx = parseInt(row.getAttribute("data-index"), 10);
                        if (idx > maxIndex) maxIndex = idx;
                    });
                    return maxIndex + 1;
                }

                function updateEntryRepeats() {
                    var rows = document.querySelectorAll(".buchungssatz-entry-row");
                    var entryRepeatsInput = document.querySelector("input[name=\'entry_repeats\']");
                    if (entryRepeatsInput) {
                        entryRepeatsInput.value = rows.length;
                    }
                }

                function createNewRow(index) {
                    var chartId = chartSelect ? chartSelect.value : "0";
                    var accounts = accountsByChart[chartId] || {};

                    var row = document.createElement("div");
                    row.className = "buchungssatz-entry-row";
                    row.setAttribute("data-index", index);
                    row.style.cssText = "display: flex; border-bottom: 1px solid #dee2e6; align-items: center;";

                    // Build account options HTML
                    var sollOptionsHtml = "<option value=\"\">" + sollPlaceholder + "</option>";
                    var habenOptionsHtml = "<option value=\"\">" + habenPlaceholder + "</option>";
                    for (var accountNumber in accounts) {
                        var optHtml = "<option value=\"" + accountNumber + "\">" + accounts[accountNumber] + "</option>";
                        sollOptionsHtml += optHtml;
                        habenOptionsHtml += optHtml;
                    }

                    row.innerHTML = \'<div style="flex: 1; padding: 0.5rem;">\' +
                        \'<select class="form-control buchungssatz-sollkonto">\' + sollOptionsHtml + \'</select>\' +
                        \'</div>\' +
                        \'<div style="flex: 1; padding: 0.5rem; border-right: 1px solid #dee2e6;">\' +
                        \'<input type="number" value="" class="form-control buchungssatz-sollbetrag" step="0.01" min="0" placeholder="0.00" disabled style="background-color: #e9ecef;">\' +
                        \'</div>\' +
                        \'<div style="flex: 1; padding: 0.5rem;">\' +
                        \'<select class="form-control buchungssatz-habenkonto">\' + habenOptionsHtml + \'</select>\' +
                        \'</div>\' +
                        \'<div style="flex: 1; padding: 0.5rem; border-right: 1px solid #dee2e6;">\' +
                        \'<input type="number" value="" class="form-control buchungssatz-habenbetrag" step="0.01" min="0" placeholder="0.00">\' +
                        \'</div>\' +
                        \'<div style="flex: 0 0 80px; padding: 0.5rem; text-align: center;">\' +
                        \'<input type="text" value="1.0" class="form-control buchungssatz-fraction" style="width: 60px; text-align: center;">\' +
                        \'</div>\' +
                        \'<div style="flex: 0 0 40px; padding: 0.5rem; text-align: center;">\' +
                        \'<button type="button" class="btn btn-outline-danger btn-sm buchungssatz-delete-row" title="\' + deleteText + \'">&times;</button>\' +
                        \'</div>\';

                    return row;
                }

                if (addEntryBtn && entryRowsContainer) {
                    addEntryBtn.addEventListener("click", function() {
                        var newIndex = getNextIndex();
                        var newRow = createNewRow(newIndex);
                        entryRowsContainer.appendChild(newRow);
                        updateEntryRepeats();
                        updateDefaultMark();
                    });
                }

                // Delete row functionality (event delegation)
                document.addEventListener("click", function(e) {
                    if (e.target.classList.contains("buchungssatz-delete-row")) {
                        var row = e.target.closest(".buchungssatz-entry-row");
                        if (row) {
                            // Ensure at least one row remains
                            var rows = document.querySelectorAll(".buchungssatz-entry-row");
                            if (rows.length > 1) {
                                row.remove();
                                updateEntryRepeats();
                                updateDefaultMark();
                            }
                        }
                    }
                });

                // Sync visible table inputs to hidden Moodle form fields before submission
                function syncToHiddenFields() {
                    var rows = document.querySelectorAll(".buchungssatz-entry-row");
                    var rowCount = 0;

                    // First, clear all hidden field values
                    for (var i = 0; i < 20; i++) {
                        var sollHidden = document.querySelector("input[name=\'sollkonto[" + i + "]\']");
                        var sollbHidden = document.querySelector("input[name=\'sollbetrag[" + i + "]\']");
                        var habenHidden = document.querySelector("input[name=\'habenkonto[" + i + "]\']");
                        var habenbHidden = document.querySelector("input[name=\'habenbetrag[" + i + "]\']");
                        var fractionHidden = document.querySelector("input[name=\'fraction[" + i + "]\']");

                        if (sollHidden) sollHidden.value = "";
                        if (sollbHidden) sollbHidden.value = "";
                        if (habenHidden) habenHidden.value = "";
                        if (habenbHidden) habenbHidden.value = "";
                        if (fractionHidden) fractionHidden.value = "1.0";
                    }

                    // Now copy values from visible rows to hidden fields
                    rows.forEach(function(row, index) {
                        var sollSelect = row.querySelector(".buchungssatz-sollkonto");
                        var sollBetrag = row.querySelector(".buchungssatz-sollbetrag");
                        var habenSelect = row.querySelector(".buchungssatz-habenkonto");
                        var habenBetrag = row.querySelector(".buchungssatz-habenbetrag");
                        var fractionInput = row.querySelector(".buchungssatz-fraction");

                        var sollHidden = document.querySelector("input[name=\'sollkonto[" + index + "]\']");
                        var sollbHidden = document.querySelector("input[name=\'sollbetrag[" + index + "]\']");
                        var habenHidden = document.querySelector("input[name=\'habenkonto[" + index + "]\']");
                        var habenbHidden = document.querySelector("input[name=\'habenbetrag[" + index + "]\']");
                        var fractionHidden = document.querySelector("input[name=\'fraction[" + index + "]\']");

                        if (sollHidden && sollSelect) sollHidden.value = sollSelect.value;
                        if (sollbHidden && sollBetrag) sollbHidden.value = sollBetrag.value;
                        if (habenHidden && habenSelect) habenHidden.value = habenSelect.value;
                        if (habenbHidden && habenBetrag) habenbHidden.value = habenBetrag.value;
                        if (fractionHidden && fractionInput) fractionHidden.value = fractionInput.value;

                        rowCount++;
                    });

                    // Update entry_repeats
                    var entryRepeatsInput = document.querySelector("input[name=\'entry_repeats\']");
                    if (entryRepeatsInput) {
                        entryRepeatsInput.value = rowCount;
                    }

                    console.log("Synced " + rowCount + " entries to hidden fields");
                }

                // Hook into form submission
                var moodleForm = document.querySelector("form.mform");
                if (moodleForm) {
                    moodleForm.addEventListener("submit", function(e) {
                        syncToHiddenFields();
                    });
                }

                // CSV Import functionality
                var importBtn = document.getElementById("buchungssatz-import-btn");
                var csvFileInput = document.getElementById("buchungssatz-csv-file");
                var csvFilenameDisplay = document.getElementById("buchungssatz-csv-filename");

                if (csvFileInput && csvFilenameDisplay) {
                    csvFileInput.addEventListener("change", function() {
                        if (csvFileInput.files.length > 0) {
                            csvFilenameDisplay.textContent = csvFileInput.files[0].name;
                            csvFilenameDisplay.style.color = "#212529";
                        } else {
                            csvFilenameDisplay.textContent = "' . get_string('nofileselected', 'qtype_buchungssatz') . '";
                            csvFilenameDisplay.style.color = "#6c757d";
                        }
                    });
                }

                if (importBtn && csvFileInput) {
                    importBtn.addEventListener("click", function() {
                        var file = csvFileInput.files[0];
                        if (!file) {
                            alert("' . get_string('nofileselected', 'qtype_buchungssatz') . '");
                            return;
                        }

                        var reader = new FileReader();
                        reader.onload = function(e) {
                            var csvData = e.target.result.trim();
                            if (!csvData) {
                                alert("' . get_string('nocsverror', 'qtype_buchungssatz') . '");
                                return;
                            }

                            var xhr = new XMLHttpRequest();
                            xhr.open("POST", M.cfg.wwwroot + "/question/type/buchungssatz/ajax/import_entries.php", true);
                            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4) {
                                    if (xhr.status === 200) {
                                        try {
                                            var response = JSON.parse(xhr.responseText);
                                            if (response.success) {
                                                if (response.chartid && chartSelect) {
                                                    var optionExists = false;
                                                    for (var i = 0; i < chartSelect.options.length; i++) {
                                                        if (chartSelect.options[i].value == response.chartid) {
                                                            optionExists = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!optionExists) {
                                                        var opt = document.createElement("option");
                                                        opt.value = response.chartid;
                                                        opt.text = response.chartname;
                                                        chartSelect.add(opt);
                                                    }
                                                    chartSelect.value = response.chartid;
                                                }

                                                accountsByChart = response.accounts;
                                                updateAccountDropdowns();

                                                // Fill in entries - add rows if needed
                                                var entries = response.entries;
                                                var existingRows = document.querySelectorAll(".buchungssatz-entry-row");

                                                // Add more rows if needed
                                                while (existingRows.length < entries.length) {
                                                    var newIndex = getNextIndex();
                                                    var newRow = createNewRow(newIndex);
                                                    entryRowsContainer.appendChild(newRow);
                                                    existingRows = document.querySelectorAll(".buchungssatz-entry-row");
                                                }

                                                // Now fill in the data
                                                existingRows = document.querySelectorAll(".buchungssatz-entry-row");
                                                for (var i = 0; i < entries.length; i++) {
                                                    var entry = entries[i];
                                                    var row = existingRows[i];
                                                    if (row) {
                                                        var sollSelect = row.querySelector(".buchungssatz-sollkonto");
                                                        var habenSelect = row.querySelector(".buchungssatz-habenkonto");
                                                        var sollBetrag = row.querySelector(".buchungssatz-sollbetrag");
                                                        var habenBetrag = row.querySelector(".buchungssatz-habenbetrag");
                                                        var fraction = row.querySelector(".buchungssatz-fraction");

                                                        if (sollSelect) sollSelect.value = entry.sollkonto;
                                                        if (habenSelect) habenSelect.value = entry.habenkonto;
                                                        if (sollBetrag) sollBetrag.value = entry.sollbetrag;
                                                        if (habenBetrag) habenBetrag.value = entry.habenbetrag;
                                                        if (fraction) fraction.value = "1.0";
                                                    }
                                                }

                                                updateEntryRepeats();
                                                updateDefaultMark();
                                                updateAllSollbetragStates();

                                                csvFileInput.value = "";
                                                if (csvFilenameDisplay) {
                                                    csvFilenameDisplay.textContent = "' . get_string('nofileselected', 'qtype_buchungssatz') . '";
                                                    csvFilenameDisplay.style.color = "#6c757d";
                                                }
                                                alert("' . get_string('importsuccess', 'qtype_buchungssatz') . '" + entries.length + " ' . get_string('entriesimported', 'qtype_buchungssatz') . '");
                                            } else {
                                                alert("' . get_string('importerror', 'qtype_buchungssatz') . '" + response.error);
                                            }
                                        } catch (e) {
                                            alert("' . get_string('importerror', 'qtype_buchungssatz') . '" + e.message);
                                        }
                                    } else {
                                        alert("' . get_string('importerror', 'qtype_buchungssatz') . 'HTTP " + xhr.status);
                                    }
                                }
                            };
                            xhr.send("sesskey=" + M.cfg.sesskey + "&csvdata=" + encodeURIComponent(csvData));
                        };
                        reader.onerror = function() {
                            alert("' . get_string('filereaderror', 'qtype_buchungssatz') . '");
                        };
                        reader.readAsText(file);
                    });
                }
            });
            </script>
        ');
    }

    /**
     * Get all accounts grouped by chart ID.
     *
     * @return array The accounts grouped by chart ID.
     */
    protected function get_all_accounts_by_chart(): array {
        global $DB;

        $result = [];
        $charts = $this->get_available_charts();

        foreach (array_keys($charts) as $chartid) {
            if ($chartid == 0) {
                continue;
            }
            $accounts = $DB->get_records('qtype_buchungssatz_accounts',
                ['chartid' => $chartid], 'sortorder, accountnumber');
            $result[$chartid] = [];
            foreach ($accounts as $account) {
                $result[$chartid][$account->accountnumber] = $account->accountnumber . ' - ' . $account->accountname;
            }
        }

        return $result;
    }

    /**
     * Get available charts of accounts.
     *
     * @return array The available charts keyed by ID.
     */
    protected function get_available_charts(): array {
        global $DB;

        $charts = [0 => get_string('nochartselected', 'qtype_buchungssatz')];

        // Always include system context charts.
        $systemcontext = context_system::instance();
        $contextids = [$systemcontext->id];

        // Try to get the current context.
        try {
            $currentcontext = $this->context ?? context_system::instance();
            if ($currentcontext->id != $systemcontext->id) {
                $contextids[] = $currentcontext->id;
                // Also include parent contexts.
                $parentcontexts = $currentcontext->get_parent_context_ids();
                $contextids = array_merge($contextids, $parentcontexts);
            }
        } catch (Exception $e) {
            // Just use system context if there's an error.
        }

        // Remove duplicates.
        $contextids = array_unique($contextids);

        list($insql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select('qtype_buchungssatz_charts',
            "contextid $insql", $params, 'name ASC');

        foreach ($records as $record) {
            $charts[$record->id] = $record->name;
        }

        return $charts;
    }

    /**
     * Preprocess the question data for the form.
     *
     * @param object $question The question data.
     * @return object The preprocessed question data.
     */
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);

        // Load options if they exist.
        if (!empty($question->options)) {
            $question->chartofaccountsid = $question->options->chartofaccountsid ?? 0;

            // Load entries into array form fields.
            if (!empty($question->options->entries)) {
                $question->sollkonto = [];
                $question->sollbetrag = [];
                $question->habenkonto = [];
                $question->habenbetrag = [];
                $question->fraction = [];

                foreach ($question->options->entries as $entry) {
                    $question->sollkonto[] = $entry->sollkonto;
                    $question->sollbetrag[] = $entry->sollbetrag;
                    $question->habenkonto[] = $entry->habenkonto;
                    $question->habenbetrag[] = $entry->habenbetrag;
                    $question->fraction[] = $entry->fraction;
                }
            }
        }

        return $question;
    }

    /**
     * Perform validation on the form data.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @return array The validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $entrycount = $data['entry_repeats'] ?? 0;
        $hasentries = false;

        for ($i = 0; $i < $entrycount; $i++) {
            $sollkonto = trim($data['sollkonto'][$i] ?? '');
            $habenkonto = trim($data['habenkonto'][$i] ?? '');
            $sollbetrag = floatval($data['sollbetrag'][$i] ?? 0);
            $habenbetrag = floatval($data['habenbetrag'][$i] ?? 0);

            // Check if entry has any data filled in.
            $hasanydata = !empty($sollkonto) || $sollbetrag != 0 || $habenbetrag != 0;

            // An entry is valid if it has at least a Credit (Haben) account.
            if (!empty($habenkonto)) {
                $hasentries = true;

                // Validate amounts are positive.
                if ($sollbetrag < 0) {
                    $errors["sollbetrag[$i]"] = get_string('err_negativeamount', 'qtype_buchungssatz');
                }
                if ($habenbetrag < 0) {
                    $errors["habenbetrag[$i]"] = get_string('err_negativeamount', 'qtype_buchungssatz');
                }
            } else if ($hasanydata) {
                // Entry has data but no Credit account - Credit is required.
                $errors["habenkonto[$i]"] = get_string('err_habenrequired', 'qtype_buchungssatz');
            }
        }

        if (!$hasentries) {
            $errors['habenkonto[0]'] = get_string('err_noentries', 'qtype_buchungssatz');
        }

        return $errors;
    }

    /**
     * Get the question type name.
     *
     * @return string The question type name.
     */
    public function qtype(): string {
        return 'buchungssatz';
    }
}
