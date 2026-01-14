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
                        ' . get_string('choosefile', 'moodle') . '
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
        $accountoptions = ['' => get_string('selectaccount', 'qtype_buchungssatz')];

        // Get current chart and populate account options.
        $currentchartid = 0;
        if (!empty($this->question->options)) {
            $currentchartid = $this->question->options->chartofaccountsid ?? 0;
        }
        if ($currentchartid && isset($allaccounts[$currentchartid])) {
            $accountoptions = $accountoptions + $allaccounts[$currentchartid];
        }

        // Define the repeatable elements for entries.
        $repeatarray = [];
        $repeatarray[] = $mform->createElement('html', '<div class="buchungssatz-entry-group card mb-3 p-3">');
        $repeatheader = [];
        $repeatheader[] = $mform->createElement('submit', 'entry_delete', get_string('deleteentry', 'qtype_buchungssatz'), ['class' => 'delete_entry']);
        $repeatarray[] = $mform->createElement('group', 'entry_header', '<strong style="font-size: 1.25rem;">' . get_string('entry', 'qtype_buchungssatz') . '</strong>', $repeatheader, null, false);
        $repeatarray[] = $mform->createElement('select', 'sollkonto',
            get_string('soll', 'qtype_buchungssatz') . ' ' . get_string('account', 'qtype_buchungssatz'),
            $accountoptions, ['class' => 'buchungssatz-sollkonto']);
        $repeatarray[] = $mform->createElement('text', 'sollbetrag',
            get_string('soll', 'qtype_buchungssatz') . ' ' . get_string('amount', 'qtype_buchungssatz'),
            ['size' => 15, 'placeholder' => '0.00', 'class' => 'buchungssatz-sollbetrag']);
        $repeatarray[] = $mform->createElement('select', 'habenkonto',
            get_string('haben', 'qtype_buchungssatz') . ' ' . get_string('account', 'qtype_buchungssatz'),
            $accountoptions, ['class' => 'buchungssatz-habenkonto']);
        $repeatarray[] = $mform->createElement('text', 'habenbetrag',
            get_string('haben', 'qtype_buchungssatz') . ' ' . get_string('amount', 'qtype_buchungssatz'),
            ['size' => 15, 'placeholder' => '0.00', 'class' => 'buchungssatz-habenbetrag']);
        $repeatarray[] = $mform->createElement('text', 'fraction',
            get_string('fraction', 'qtype_buchungssatz'),
            ['size' => 5, 'class' => 'buchungssatz-fraction']);
        $repeatarray[] = $mform->createElement('html', '</div>');

        // Set up repeat options.
        $repeatoptions = [];
        $repeatoptions['sollkonto']['type'] = PARAM_TEXT;
        $repeatoptions['sollbetrag']['type'] = PARAM_RAW;
        $repeatoptions['habenkonto']['type'] = PARAM_TEXT;
        $repeatoptions['habenbetrag']['type'] = PARAM_RAW;
        $repeatoptions['fraction']['type'] = PARAM_RAW;
        $repeatoptions['fraction']['default'] = '1.0';

        // Determine how many entries to show initially.
        $repeatcount = 1;
        if (!empty($this->question->options->entries)) {
            $repeatcount = count($this->question->options->entries);
        }

        // Add the repeating elements.
        $this->repeat_elements(
            $repeatarray,
            $repeatcount,
            $repeatoptions,
            'entry_repeats',
            'entry_add',
            1,
            get_string('addentry', 'qtype_buchungssatz'),
            false,
            'entry_delete',
            true
        );

        // Add JavaScript for dynamic account dropdowns and other functionality.
        $this->add_entry_javascript($mform, $allaccounts);
    }

    /**
     * Add JavaScript for entry field handling.
     *
     * @param MoodleQuickForm $mform The form being built.
     * @param array $allaccounts All accounts grouped by chart ID.
     */
    protected function add_entry_javascript($mform, array $allaccounts): void {
        $accountsjson = json_encode($allaccounts);

        $mform->addElement('html', '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var accountsByChart = ' . $accountsjson . ';
                var chartSelect = document.getElementById("id_chartofaccountsid");

                // Function to update account dropdowns based on selected chart
                function updateAccountDropdowns() {
                    var chartId = chartSelect ? chartSelect.value : "0";
                    var accounts = accountsByChart[chartId] || {};

                    // Find all sollkonto and habenkonto selects
                    var sollSelects = document.querySelectorAll("select[name^=\'sollkonto[\']");
                    var habenSelects = document.querySelectorAll("select[name^=\'habenkonto[\']");

                    sollSelects.forEach(function(select) {
                        var currentValue = select.value;
                        // Keep first option (placeholder)
                        while (select.options.length > 1) {
                            select.remove(1);
                        }
                        for (var accountNumber in accounts) {
                            var option = document.createElement("option");
                            option.value = accountNumber;
                            option.text = accounts[accountNumber];
                            select.add(option);
                        }
                        if (currentValue) {
                            select.value = currentValue;
                        }
                    });

                    habenSelects.forEach(function(select) {
                        var currentValue = select.value;
                        while (select.options.length > 1) {
                            select.remove(1);
                        }
                        for (var accountNumber in accounts) {
                            var option = document.createElement("option");
                            option.value = accountNumber;
                            option.text = accounts[accountNumber];
                            select.add(option);
                        }
                        if (currentValue) {
                            select.value = currentValue;
                        }
                    });

                    updateAllSollbetragStates();
                }

                // Function to update default mark based on sum of fractions
                function updateDefaultMark() {
                    var totalFraction = 0;
                    var fractionInputs = document.querySelectorAll("input[name^=\'fraction[\']");

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
                    var name = sollSelect.name;
                    var index = name.match(/\[(\d+)\]/)[1];
                    var sollBetrag = document.querySelector("input[name=\'sollbetrag[" + index + "]\']");

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
                    var sollSelects = document.querySelectorAll("select[name^=\'sollkonto[\']");
                    sollSelects.forEach(function(select) {
                        updateSollbetragState(select);
                    });
                }

                // Event delegation for dynamically added elements
                document.addEventListener("change", function(e) {
                    if (e.target.name && e.target.name.startsWith("sollkonto[")) {
                        updateSollbetragState(e.target);
                    }
                    if (e.target.name && e.target.name.startsWith("fraction[")) {
                        updateDefaultMark();
                    }
                });

                document.addEventListener("input", function(e) {
                    if (e.target.name && e.target.name.startsWith("fraction[")) {
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

                                                // Fill in entries
                                                var entries = response.entries;
                                                var sollSelects = document.querySelectorAll("select[name^=\'sollkonto[\']");
                                                var habenSelects = document.querySelectorAll("select[name^=\'habenkonto[\']");
                                                var sollBetrags = document.querySelectorAll("input[name^=\'sollbetrag[\']");
                                                var habenBetrags = document.querySelectorAll("input[name^=\'habenbetrag[\']");
                                                var fractions = document.querySelectorAll("input[name^=\'fraction[\']");

                                                for (var i = 0; i < entries.length; i++) {
                                                    var entry = entries[i];
                                                    if (sollSelects[i]) sollSelects[i].value = entry.sollkonto;
                                                    if (habenSelects[i]) habenSelects[i].value = entry.habenkonto;
                                                    if (sollBetrags[i]) sollBetrags[i].value = entry.sollbetrag;
                                                    if (habenBetrags[i]) habenBetrags[i].value = entry.habenbetrag;
                                                    if (fractions[i]) fractions[i].value = "1.0";
                                                }

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
