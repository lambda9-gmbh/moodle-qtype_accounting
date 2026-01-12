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
 */
class qtype_buchungssatz_edit_form extends question_edit_form {

    /** @var int Maximum number of entry fields that can be created */
    const MAX_ENTRY_FIELDS = 50;

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

        // Hidden field to track number of visible entries (for form processing).
        $mform->addElement('hidden', 'maxentries', 1);
        $mform->setType('maxentries', PARAM_INT);

        // Import from Excel/CSV section.
        $mform->addElement('header', 'importhdr', get_string('importfromcsv', 'qtype_buchungssatz'));
        $mform->setExpanded('importhdr', false);

        $mform->addElement('static', 'importhelp', '',
            '<div class="alert alert-info">' . get_string('importhelp', 'qtype_buchungssatz') . '</div>');

        $mform->addElement('textarea', 'csvimport', get_string('csvdata', 'qtype_buchungssatz'),
            ['rows' => 6, 'cols' => 80, 'id' => 'buchungssatz-csv-import']);
        $mform->setType('csvimport', PARAM_RAW);

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

        // Get all accounts grouped by chart for JavaScript.
        $allaccounts = $this->get_all_accounts_by_chart();

        // Build a flat list of all valid account numbers for form validation.
        $allvalidaccounts = ['' => get_string('selectaccount', 'qtype_buchungssatz')];
        foreach ($allaccounts as $chartid => $accounts) {
            foreach ($accounts as $number => $label) {
                $allvalidaccounts[$number] = $label;
            }
        }

        // Get current chart ID for JavaScript to show the right accounts.
        $currentchartid = 0;
        $savedentries = [];

        if (!empty($this->question->options)) {
            $currentchartid = $this->question->options->chartofaccountsid ?? 0;
        }

        if (!empty($this->question->options->entries)) {
            foreach ($this->question->options->entries as $entry) {
                $savedentries[] = [
                    'sollkonto' => $entry->sollkonto,
                    'habenkonto' => $entry->habenkonto,
                ];
            }
        }

        // Add entries for the correct answer.
        $this->add_entry_fields($mform, self::MAX_ENTRY_FIELDS, $allaccounts, $allvalidaccounts, $currentchartid, $savedentries);
    }

    /**
     * Get all accounts grouped by chart ID.
     *
     * @return array
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
     * @return array
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
     * Add entry fields for correct answer.
     *
     * @param MoodleQuickForm $mform
     * @param int $count
     * @param array $allaccounts Accounts grouped by chart ID (for JavaScript)
     * @param array $allvalidaccounts Flat list of all valid accounts (for form validation)
     * @param int $currentchartid Current chart ID for pre-populating dropdowns
     * @param array $savedentries Saved entry values to restore
     */
    protected function add_entry_fields($mform, int $count, array $allaccounts, array $allvalidaccounts, int $currentchartid = 0, array $savedentries = []): void {
        // Container for all entries.
        $mform->addElement('html', '<div id="buchungssatz-entries-container">');

        for ($i = 0; $i < $count; $i++) {
            // Hide entries beyond the first one by default.
            $hidden = ($i > 0) ? ' style="display:none;"' : '';
            $mform->addElement('html', '<div class="buchungssatz-entry-group card mb-3 p-3" id="entry_group_' . $i . '"' . $hidden . '>');

            // Entry header with label and delete button.
            $deletebutton = ($i > 0) ? '<button type="button" class="btn btn-sm btn-outline-danger float-right buchungssatz-delete-entry" data-entry="' . $i . '">' . get_string('deleteentry', 'qtype_buchungssatz') . '</button>' : '';
            $mform->addElement('html', '<div class="entry-header mb-2"><strong class="entry-label">' . get_string('entry', 'qtype_buchungssatz', $i + 1) . '</strong>' . $deletebutton . '</div>');

            // Soll (Debit) account - dropdown.
            $mform->addElement('select', 'sollkonto_' . $i,
                get_string('soll', 'qtype_buchungssatz') . ' ' . get_string('account', 'qtype_buchungssatz'),
                $allvalidaccounts);
            $mform->setType('sollkonto_' . $i, PARAM_TEXT);

            // Soll (Debit) amount.
            $mform->addElement('text', 'sollbetrag_' . $i,
                get_string('soll', 'qtype_buchungssatz') . ' ' . get_string('amount', 'qtype_buchungssatz'),
                ['size' => 15, 'placeholder' => '0.00']);
            $mform->setType('sollbetrag_' . $i, PARAM_RAW);

            // Haben (Credit) account - dropdown.
            $mform->addElement('select', 'habenkonto_' . $i,
                get_string('haben', 'qtype_buchungssatz') . ' ' . get_string('account', 'qtype_buchungssatz'),
                $allvalidaccounts);
            $mform->setType('habenkonto_' . $i, PARAM_TEXT);

            // Haben (Credit) amount.
            $mform->addElement('text', 'habenbetrag_' . $i,
                get_string('haben', 'qtype_buchungssatz') . ' ' . get_string('amount', 'qtype_buchungssatz'),
                ['size' => 15, 'placeholder' => '0.00']);
            $mform->setType('habenbetrag_' . $i, PARAM_RAW);

            // Fraction for partial grading.
            $mform->addElement('text', 'fraction_' . $i,
                get_string('fraction', 'qtype_buchungssatz'), ['size' => 5]);
            $mform->setType('fraction_' . $i, PARAM_RAW);
            $mform->setDefault('fraction_' . $i, '1.0');

            $mform->addElement('html', '</div>');
        }

        $mform->addElement('html', '</div>');

        // Add Entry button.
        $mform->addElement('html', '<div class="mb-3"><button type="button" class="btn btn-secondary" id="buchungssatz-add-entry">' . get_string('addentry', 'qtype_buchungssatz') . '</button></div>');

        // Encode accounts data for JavaScript.
        $accountsjson = json_encode($allaccounts);
        $savedentriesjson = json_encode($savedentries);

        // Add JavaScript to handle chart selection and add/remove entries.
        $maxfields = self::MAX_ENTRY_FIELDS;
        $mform->addElement('html', '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var accountsByChart = ' . $accountsjson . ';
                var savedEntries = ' . $savedentriesjson . ';
                var maxEntryFields = ' . $maxfields . ';
                var chartSelect = document.getElementById("id_chartofaccountsid");
                var addButton = document.getElementById("buchungssatz-add-entry");
                var initialLoad = true;

                // Track visible entries
                var visibleEntries = [];

                function getVisibleEntries() {
                    visibleEntries = [];
                    for (var i = 0; i < maxEntryFields; i++) {
                        var group = document.getElementById("entry_group_" + i);
                        if (group && group.style.display !== "none") {
                            visibleEntries.push(i);
                        }
                    }
                    return visibleEntries;
                }

                function updateAddButtonVisibility() {
                    var visible = getVisibleEntries();
                    if (addButton) {
                        addButton.style.display = (visible.length >= maxEntryFields) ? "none" : "";
                    }
                }

                function updateAccountDropdowns() {
                    var chartId = chartSelect ? chartSelect.value : "0";
                    var accounts = accountsByChart[chartId] || {};

                    for (var i = 0; i < maxEntryFields; i++) {
                        var sollSelect = document.getElementById("id_sollkonto_" + i);
                        var habenSelect = document.getElementById("id_habenkonto_" + i);

                        var savedSoll = (initialLoad && savedEntries[i]) ? savedEntries[i].sollkonto : null;
                        var savedHaben = (initialLoad && savedEntries[i]) ? savedEntries[i].habenkonto : null;

                        if (sollSelect) {
                            var currentSoll = sollSelect.value || savedSoll;
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
                        }

                        if (habenSelect) {
                            var currentHaben = habenSelect.value || savedHaben;
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
                        }
                    }
                    initialLoad = false;
                }

                function addEntry() {
                    // Find the first hidden entry
                    for (var i = 0; i < maxEntryFields; i++) {
                        var group = document.getElementById("entry_group_" + i);
                        if (group && group.style.display === "none") {
                            group.style.display = "";
                            updateAddButtonVisibility();
                            return;
                        }
                    }
                }

                function deleteEntry(index) {
                    var group = document.getElementById("entry_group_" + index);
                    if (!group) return;

                    // Clear the fields
                    var sollSelect = document.getElementById("id_sollkonto_" + index);
                    var habenSelect = document.getElementById("id_habenkonto_" + index);
                    var sollBetrag = document.getElementById("id_sollbetrag_" + index);
                    var habenBetrag = document.getElementById("id_habenbetrag_" + index);
                    var fraction = document.getElementById("id_fraction_" + index);

                    if (sollSelect) sollSelect.value = "";
                    if (habenSelect) habenSelect.value = "";
                    if (sollBetrag) sollBetrag.value = "";
                    if (habenBetrag) habenBetrag.value = "";
                    if (fraction) fraction.value = "1.0";

                    // Hide the entry
                    group.style.display = "none";
                    updateAddButtonVisibility();
                }

                // Event listeners
                if (chartSelect) {
                    chartSelect.addEventListener("change", updateAccountDropdowns);
                }

                if (addButton) {
                    addButton.addEventListener("click", addEntry);
                }

                // Delegate delete button clicks
                document.addEventListener("click", function(e) {
                    if (e.target.classList.contains("buchungssatz-delete-entry")) {
                        var entryIndex = parseInt(e.target.getAttribute("data-entry"));
                        deleteEntry(entryIndex);
                    }
                });

                // Initial setup
                updateAccountDropdowns();

                // Show entries that have saved data
                for (var i = 0; i < savedEntries.length; i++) {
                    var group = document.getElementById("entry_group_" + i);
                    if (group) group.style.display = "";
                }

                updateAddButtonVisibility();

                // Auto-refresh accounts when returning from chart management
                var manageChartsLink = document.getElementById("buchungssatz-manage-charts-link");
                var chartManagementOpened = false;

                if (manageChartsLink) {
                    manageChartsLink.addEventListener("click", function() {
                        chartManagementOpened = true;
                    });
                }

                // Refresh accounts when tab becomes visible again
                document.addEventListener("visibilitychange", function() {
                    if (document.visibilityState === "visible" && chartManagementOpened) {
                        chartManagementOpened = false;
                        refreshAccountsFromServer();
                    }
                });

                // Also refresh on window focus (backup for browsers that dont fire visibilitychange reliably)
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
                var csvTextarea = document.getElementById("buchungssatz-csv-import");

                if (importBtn && csvTextarea) {
                    importBtn.addEventListener("click", function() {
                        var csvData = csvTextarea.value.trim();
                        if (!csvData) {
                            alert("' . get_string('nocsverror', 'qtype_buchungssatz') . '");
                            return;
                        }

                        // Send to server for processing
                        var xhr = new XMLHttpRequest();
                        xhr.open("POST", M.cfg.wwwroot + "/question/type/buchungssatz/ajax/import_entries.php", true);
                        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            // Update chart dropdown and select the new chart
                                            if (response.chartid && chartSelect) {
                                                // Add option if not exists
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

                                            // Update accounts data
                                            accountsByChart = response.accounts;
                                            updateAccountDropdowns();

                                            // Fill in entries
                                            var entries = response.entries;
                                            for (var i = 0; i < entries.length && i < maxEntryFields; i++) {
                                                var entry = entries[i];
                                                var group = document.getElementById("entry_group_" + i);
                                                if (group) group.style.display = "";

                                                var sollSelect = document.getElementById("id_sollkonto_" + i);
                                                var habenSelect = document.getElementById("id_habenkonto_" + i);
                                                var sollBetrag = document.getElementById("id_sollbetrag_" + i);
                                                var habenBetrag = document.getElementById("id_habenbetrag_" + i);

                                                if (sollSelect) sollSelect.value = entry.sollkonto;
                                                if (habenSelect) habenSelect.value = entry.habenkonto;
                                                if (sollBetrag) sollBetrag.value = entry.sollbetrag;
                                                if (habenBetrag) habenBetrag.value = entry.habenbetrag;
                                            }

                                            updateAddButtonVisibility();
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
                    });
                }
            });
            </script>
        ');
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

            // Load entries into individual form fields.
            if (!empty($question->options->entries)) {
                $i = 0;
                foreach ($question->options->entries as $entry) {
                    $question->{'sollkonto_' . $i} = $entry->sollkonto;
                    $question->{'sollbetrag_' . $i} = $entry->sollbetrag;
                    $question->{'habenkonto_' . $i} = $entry->habenkonto;
                    $question->{'habenbetrag_' . $i} = $entry->habenbetrag;
                    $question->{'fraction_' . $i} = $entry->fraction;
                    $i++;
                }
            }
        }

        return $question;
    }

    /**
     * Perform validation on the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Check that at least one entry is provided.
        $hasentries = false;
        for ($i = 0; $i < self::MAX_ENTRY_FIELDS; $i++) {
            $sollkonto = trim($data['sollkonto_' . $i] ?? '');
            $habenkonto = trim($data['habenkonto_' . $i] ?? '');

            if (!empty($sollkonto) || !empty($habenkonto)) {
                $hasentries = true;

                // Validate that both sides are filled.
                if (empty($sollkonto) && !empty($habenkonto)) {
                    $errors['sollkonto_' . $i] = get_string('err_sollrequired', 'qtype_buchungssatz');
                }
                if (!empty($sollkonto) && empty($habenkonto)) {
                    $errors['habenkonto_' . $i] = get_string('err_habenrequired', 'qtype_buchungssatz');
                }

                // Validate amounts are positive.
                $sollbetrag = floatval($data['sollbetrag_' . $i] ?? 0);
                $habenbetrag = floatval($data['habenbetrag_' . $i] ?? 0);

                if ($sollbetrag < 0) {
                    $errors['sollbetrag_' . $i] = get_string('err_negativeamount', 'qtype_buchungssatz');
                }
                if ($habenbetrag < 0) {
                    $errors['habenbetrag_' . $i] = get_string('err_negativeamount', 'qtype_buchungssatz');
                }
            }
        }

        if (!$hasentries) {
            $errors['sollkonto_0'] = get_string('err_noentries', 'qtype_buchungssatz');
        }

        return $errors;
    }

    /**
     * Get the question type name.
     *
     * @return string
     */
    public function qtype(): string {
        return 'buchungssatz';
    }
}
