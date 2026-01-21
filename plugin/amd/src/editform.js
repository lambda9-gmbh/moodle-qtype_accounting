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
 * JavaScript for the Buchungssatz question edit form.
 *
 * @module     qtype_buchungssatz/editform
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/config'], function($, Str, Config) {

    // Module-level state
    let accountsByChart = {};
    let lastChartId = null;
    let chartManagementOpened = false;

    // DOM element references
    let chartSelect = null;
    let existingEntries = [];
    let initialChartId = '0';

    /**
     * Initialize the edit form enhancements.
     *
     * @param {Object} accounts All accounts grouped by chart ID.
     * @param {string|number} chartId The initial chart ID.
     * @param {Array} entries Existing entry values for restoration.
     */
    function init(accounts, chartId, entries) {
        accountsByChart = accounts || {};
        initialChartId = String(chartId || '0');
        existingEntries = entries || [];

        chartSelect = document.getElementById('id_chartofaccountsid');

        // Initial setup - populate dropdowns based on selected chart
        if (chartSelect && initialChartId && (!chartSelect.value || chartSelect.value === '0')) {
            chartSelect.value = initialChartId;
        }
        lastChartId = chartSelect ? chartSelect.value : '0';

        // Setup event handlers
        setupChartChangeHandler();
        setupSollkontoChangeHandler();
        setupMutationObserver();
        setupDistributeGradesHandler();
        setupCsvImportHandler();
        setupChartManagementRefresh();

        // Initial dropdown population
        updateAccountDropdowns(true, existingEntries.length > 0);
        updateAllSollbetragStates();
    }

    /**
     * Setup chart selection change handler.
     */
    function setupChartChangeHandler() {
        if (chartSelect) {
            chartSelect.addEventListener('change', function() {
                lastChartId = null; // Reset to force rebuild
                updateAccountDropdowns(true, false);
            });
        }
    }

    /**
     * Setup event delegation for sollkonto changes.
     */
    function setupSollkontoChangeHandler() {
        document.addEventListener('change', function(e) {
            if (e.target.name && e.target.name.startsWith('sollkonto[')) {
                updateSollbetragState(e.target);
            }
        });
    }

    /**
     * Setup MutationObserver for dynamically added elements.
     */
    function setupMutationObserver() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    updateAccountDropdowns(true, false);
                }
            });
        });

        const form = document.querySelector('form');
        if (form) {
            observer.observe(form, {childList: true, subtree: true});
        }
    }

    /**
     * Setup distribute grades equally button handler.
     */
    function setupDistributeGradesHandler() {
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.buchungssatz-distribute-grades-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();

                const gradeInputs = document.querySelectorAll("input[name^='grade[']");
                const visibleGrades = [];

                gradeInputs.forEach(function(input) {
                    const entryGroup = input.closest('.buchungssatz-entry-group');
                    const isVisible = !entryGroup ||
                        entryGroup.offsetParent !== null ||
                        window.getComputedStyle(entryGroup).display !== 'none';

                    if (isVisible) {
                        visibleGrades.push(input);
                    }
                });

                if (visibleGrades.length > 0) {
                    const gradePerEntry = 100 / visibleGrades.length;
                    visibleGrades.forEach(function(gradeInput) {
                        gradeInput.value = gradePerEntry;
                    });
                }
            }
        });
    }

    /**
     * Setup CSV import functionality.
     */
    function setupCsvImportHandler() {
        const importBtn = document.getElementById('buchungssatz-import-btn');
        const csvFileInput = document.getElementById('buchungssatz-csv-file');
        const csvFilenameDisplay = document.getElementById('buchungssatz-csv-filename');

        if (csvFileInput && csvFilenameDisplay) {
            csvFileInput.addEventListener('change', function() {
                if (csvFileInput.files.length > 0) {
                    csvFilenameDisplay.textContent = csvFileInput.files[0].name;
                    csvFilenameDisplay.style.color = '#212529';
                } else {
                    Str.get_string('nofileselected', 'qtype_buchungssatz').then(function(str) {
                        csvFilenameDisplay.textContent = str;
                        csvFilenameDisplay.style.color = '#6c757d';
                    });
                }
            });
        }

        if (importBtn && csvFileInput) {
            importBtn.addEventListener('click', function() {
                const file = csvFileInput.files[0];
                if (!file) {
                    Str.get_string('nofileselected', 'qtype_buchungssatz').then(function(str) {
                        alert(str);
                    });
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const csvData = e.target.result.trim();
                    if (!csvData) {
                        Str.get_string('nocsverror', 'qtype_buchungssatz').then(function(str) {
                            alert(str);
                        });
                        return;
                    }

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', Config.wwwroot + '/question/type/buchungssatz/ajax/import_entries.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            handleImportResponse(xhr, csvFileInput, csvFilenameDisplay);
                        }
                    };
                    xhr.send('sesskey=' + Config.sesskey + '&csvdata=' + encodeURIComponent(csvData));
                };
                reader.onerror = function() {
                    Str.get_string('filereaderror', 'qtype_buchungssatz').then(function(str) {
                        alert(str);
                    });
                };
                reader.readAsText(file);
            });
        }
    }

    /**
     * Handle import response from server.
     *
     * @param {XMLHttpRequest} xhr The XMLHttpRequest object.
     * @param {HTMLElement} csvFileInput The file input element.
     * @param {HTMLElement} csvFilenameDisplay The filename display element.
     */
    function handleImportResponse(xhr, csvFileInput, csvFilenameDisplay) {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    if (response.chartid && chartSelect) {
                        let optionExists = false;
                        for (let i = 0; i < chartSelect.options.length; i++) {
                            if (chartSelect.options[i].value == response.chartid) {
                                optionExists = true;
                                break;
                            }
                        }
                        if (!optionExists) {
                            const opt = document.createElement('option');
                            opt.value = response.chartid;
                            opt.text = response.chartname;
                            chartSelect.add(opt);
                        }
                        chartSelect.value = response.chartid;
                    }

                    accountsByChart = response.accounts;
                    updateAccountDropdowns(true, false);

                    // Fill in entries
                    const entries = response.entries;
                    const sollSelects = document.querySelectorAll("select[name^='sollkonto[']");
                    const habenSelects = document.querySelectorAll("select[name^='habenkonto[']");
                    const sollBetrags = document.querySelectorAll("input[name^='sollbetrag[']");
                    const habenBetrags = document.querySelectorAll("input[name^='habenbetrag[']");
                    const grades = document.querySelectorAll("input[name^='grade[']");

                    const gradePerEntry = entries.length > 0 ? (100 / entries.length) : 0;

                    for (let i = 0; i < entries.length; i++) {
                        const entry = entries[i];
                        if (sollSelects[i]) {
                            sollSelects[i].value = entry.sollkonto;
                        }
                        if (habenSelects[i]) {
                            habenSelects[i].value = entry.habenkonto;
                        }
                        if (sollBetrags[i]) {
                            sollBetrags[i].value = entry.sollbetrag;
                        }
                        if (habenBetrags[i]) {
                            habenBetrags[i].value = entry.habenbetrag;
                        }
                        if (grades[i]) {
                            grades[i].value = gradePerEntry;
                        }
                    }

                    updateAllSollbetragStates();

                    csvFileInput.value = '';
                    if (csvFilenameDisplay) {
                        Str.get_string('nofileselected', 'qtype_buchungssatz').then(function(str) {
                            csvFilenameDisplay.textContent = str;
                            csvFilenameDisplay.style.color = '#6c757d';
                        });
                    }

                    Str.get_strings([
                        {key: 'importsuccess', component: 'qtype_buchungssatz'},
                        {key: 'entriesimported', component: 'qtype_buchungssatz'}
                    ]).then(function(strings) {
                        alert(strings[0] + entries.length + ' ' + strings[1]);
                    });
                } else {
                    Str.get_string('importerror', 'qtype_buchungssatz').then(function(str) {
                        alert(str + response.error);
                    });
                }
            } catch (e) {
                Str.get_string('importerror', 'qtype_buchungssatz').then(function(str) {
                    alert(str + e.message);
                });
            }
        } else {
            Str.get_string('importerror', 'qtype_buchungssatz').then(function(str) {
                alert(str + 'HTTP ' + xhr.status);
            });
        }
    }

    /**
     * Setup auto-refresh when returning from chart management.
     */
    function setupChartManagementRefresh() {
        const manageChartsLink = document.getElementById('buchungssatz-manage-charts-link');

        if (manageChartsLink) {
            manageChartsLink.addEventListener('click', function() {
                chartManagementOpened = true;
            });
        }

        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible' && chartManagementOpened) {
                chartManagementOpened = false;
                refreshAccountsFromServer();
            }
        });

        window.addEventListener('focus', function() {
            if (chartManagementOpened) {
                chartManagementOpened = false;
                refreshAccountsFromServer();
            }
        });
    }

    /**
     * Refresh accounts from server via AJAX.
     */
    function refreshAccountsFromServer() {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', Config.wwwroot + '/question/type/buchungssatz/ajax/get_accounts.php?sesskey=' + Config.sesskey, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    accountsByChart = JSON.parse(xhr.responseText);
                    updateAccountDropdowns(true, false);
                } catch (e) {
                    console.error('Failed to parse accounts response', e);
                }
            }
        };
        xhr.send();
    }

    /**
     * Update account dropdowns based on selected chart.
     *
     * @param {boolean} forceRebuild Whether to force rebuild even if chart hasn't changed.
     * @param {boolean} useExistingValues Whether to use existing entry values.
     */
    function updateAccountDropdowns(forceRebuild, useExistingValues) {
        const chartId = chartSelect ? chartSelect.value : '0';
        const accounts = accountsByChart[chartId] || {};

        // Skip if chart hasn't changed (unless forced)
        if (!forceRebuild && lastChartId === chartId) {
            updateAllSollbetragStates();
            return;
        }
        lastChartId = chartId;

        // Find all sollkonto and habenkonto selects
        const sollSelects = document.querySelectorAll("select[name^='sollkonto[']");
        const habenSelects = document.querySelectorAll("select[name^='habenkonto[']");

        sollSelects.forEach(function(select, idx) {
            let currentValue = select.value;
            if (useExistingValues && existingEntries[idx] && !currentValue) {
                currentValue = existingEntries[idx].sollkonto || '';
            }
            // Keep first option (placeholder)
            while (select.options.length > 1) {
                select.remove(1);
            }
            for (const accountNumber in accounts) {
                const option = document.createElement('option');
                option.value = accountNumber;
                option.text = accounts[accountNumber];
                select.add(option);
            }
            if (currentValue) {
                select.value = currentValue;
            }
        });

        habenSelects.forEach(function(select, idx) {
            let currentValue = select.value;
            if (useExistingValues && existingEntries[idx] && !currentValue) {
                currentValue = existingEntries[idx].habenkonto || '';
            }
            while (select.options.length > 1) {
                select.remove(1);
            }
            for (const accountNumber in accounts) {
                const option = document.createElement('option');
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

    /**
     * Update the disabled state of a Soll (debit) amount field.
     *
     * @param {HTMLElement} sollSelect The Soll account select element.
     */
    function updateSollbetragState(sollSelect) {
        const name = sollSelect.name;
        const match = name.match(/\[(\d+)\]/);
        if (!match) {
            return;
        }
        const index = match[1];
        const sollBetrag = document.querySelector("input[name='sollbetrag[" + index + "]']");

        if (sollBetrag) {
            const hasAccount = sollSelect.value !== '' && sollSelect.value !== null;
            sollBetrag.disabled = !hasAccount;
            sollBetrag.style.backgroundColor = hasAccount ? '' : '#e9ecef';

            if (hasAccount) {
                sollBetrag.title = '';
            } else {
                Str.get_string('selectDebitAccountFirst', 'qtype_buchungssatz').then(function(str) {
                    sollBetrag.title = str;
                });
                sollBetrag.value = '';
            }
        }
    }

    /**
     * Update all Soll (debit) amount field states.
     */
    function updateAllSollbetragStates() {
        const sollSelects = document.querySelectorAll("select[name^='sollkonto[']");
        sollSelects.forEach(function(select) {
            updateSollbetragState(select);
        });
    }

    return {
        init: init
    };
});
