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
 * Chart selection and account options of the edit form.
 *
 * @module     qtype_accounting/editform/charts
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';
import Pending from 'core/pending';
import Log from 'core/log';
import {call as ajaxCall} from 'core/ajax';
import {state} from 'qtype_accounting/editform/state';
import {syncAllDisplayToHidden} from 'qtype_accounting/editform/fields';
import {updateAllDebitamountStates, updateAllWeightStates} from 'qtype_accounting/editform/field_states';

/**
 * Rebuild the account options when another chart is selected.
 */
export function setupChartChangeHandler() {
    if (state.chartSelect) {
        state.chartSelect.addEventListener('change', function() {
            checkOptionAvailability(state.chartSelect);
            state.lastChartId = null;
            updateAccountDropdowns(true);
        });
    }
}

/**
 * Create the refresh callback used when chart management closes, keeping the question form in place.
 *
 * @param {number} contextId Question bank context ID.
 * @returns {Function|null} Refresh callback, or null outside a course.
 */
export function createChartRefreshCallback(contextId) {
    const status = document.getElementById('qtype_accounting-refresh-status');
    const chartSelect = state.chartSelect;
    if (!status || !chartSelect || !contextId) {
        return null;
    }
    let pendingRefresh = Promise.resolve();
    const refresh = async function() {
        status.textContent = '';
        try {
            const messages = await Str.get_strings([
                {key: 'refreshchartsdone', component: 'qtype_accounting'},
                {key: 'refreshchartsmissing', component: 'qtype_accounting'},
                {key: 'err_accountunavailable', component: 'qtype_accounting'},
            ]);
            const charts = await ajaxCall([{
                methodname: 'qtype_accounting_get_charts',
                args: {contextid: contextId},
            }])[0];
            state.unavailableMessage = messages[2];
            // Read selections after the request: the teacher may edit while it is in flight.
            const chartId = chartSelect.value;
            const selectedOption = chartSelect.selectedOptions[0];
            const freshAccounts = {};
            while (chartSelect.options.length > 1) {
                chartSelect.remove(1);
            }
            charts.forEach(function(chart) {
                chartSelect.add(new Option(chart.name, chart.id));
                freshAccounts[chart.id] = {};
                chart.accounts.forEach(function(account) {
                    freshAccounts[chart.id][account.id] = account.name;
                });
            });
            if (chartId !== '0' && !Object.prototype.hasOwnProperty.call(freshAccounts, chartId)) {
                selectedOption.dataset.unavailable = 'true';
                chartSelect.add(selectedOption);
            }
            chartSelect.value = chartId;
            checkOptionAvailability(chartSelect);
            state.accountsByChart = freshAccounts;
            updateAccountDropdowns(true, true);
            syncAllDisplayToHidden();
            const missing = state.form.querySelector('option[data-unavailable="true"]:checked');
            status.textContent = ' ' + messages[missing ? 1 : 0];
        } catch (error) {
            Log.error('Failed to refresh accounting charts', error);
            status.textContent = ' ' + await Str.get_string('refreshchartsfailed', 'qtype_accounting');
        }
    };
    // Queue refreshes when overlays are closed before an earlier refresh completes.
    const refreshCharts = () => {
        const pending = new Pending('qtype_accounting/editform:refreshcharts');
        pendingRefresh = pendingRefresh.then(refresh, refresh).finally(() => pending.resolve());
        return pendingRefresh;
    };
    return refreshCharts;
}

/**
 * Block saving a selection which disappeared during a refresh.
 *
 * @param {HTMLSelectElement} select Chart or account selector.
 */
export function checkOptionAvailability(select) {
    const missing = select.selectedOptions[0] && select.selectedOptions[0].dataset.unavailable;
    select.setCustomValidity(missing ? state.unavailableMessage : '');
    select.classList.toggle('is-invalid', !!missing);
}

/**
 * Update the account options of all entries to the selected chart.
 *
 * @param {boolean} forceRebuild Whether to force rebuild even if chart hasn't changed.
 * @param {boolean} preserveMissing Whether to retain unavailable selections and their amounts.
 */
export function updateAccountDropdowns(forceRebuild, preserveMissing = false) {
    const chartId = state.chartSelect ? state.chartSelect.value : '0';
    const accounts = state.accountsByChart[chartId] || {};

    if (!forceRebuild && state.lastChartId === chartId) {
        return;
    }
    state.lastChartId = chartId;

    state.form.querySelectorAll('select.qtype_accounting-debitaccount, select.qtype_accounting-creditaccount')
        .forEach(function(select) {
            const currentValue = select.value;
            const currentOption = select.selectedOptions[0];
            // Keep the first option (placeholder).
            while (select.options.length > 1) {
                select.remove(1);
            }
            for (const accountId in accounts) {
                const option = document.createElement('option');
                option.value = accountId;
                option.text = accounts[accountId];
                select.add(option);
            }
            if (currentValue) {
                if (preserveMissing && !Object.prototype.hasOwnProperty.call(accounts, currentValue)) {
                    currentOption.dataset.unavailable = 'true';
                    select.add(currentOption);
                }
                select.value = currentValue;
            }
            checkOptionAvailability(select);
        });

    updateAllDebitamountStates();
    updateAllWeightStates();
}
