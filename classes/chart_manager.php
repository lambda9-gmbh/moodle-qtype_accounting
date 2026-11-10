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
 * Chart of accounts manager for qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Manager class for charts of accounts.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_manager {
    /**
     * Create a new chart of accounts.
     *
     * @param string $name Chart name.
     * @param int $contextid Context ID.
     * @return int The new chart ID.
     */
    public static function create_chart(string $name, int $contextid): int {
        global $DB, $USER;

        $chart = new \stdClass();
        $chart->name = $name;
        $chart->contextid = $contextid;
        $chart->timecreated = time();
        $chart->timemodified = time();
        $chart->usermodified = $USER->id;

        return $DB->insert_record('qtype_accounting_charts', $chart);
    }

    /**
     * Update a chart of accounts.
     *
     * @param int $chartid Chart ID.
     * @param string $name Chart name.
     * @return bool True on success.
     */
    public static function update_chart(int $chartid, string $name): bool {
        global $DB, $USER;

        $chart = $DB->get_record('qtype_accounting_charts', ['id' => $chartid], '*', MUST_EXIST);
        $chart->name = $name;
        $chart->timemodified = time();
        $chart->usermodified = $USER->id;

        return $DB->update_record('qtype_accounting_charts', $chart);
    }

    /**
     * Delete a chart of accounts.
     *
     * @param int $chartid Chart ID.
     * @return bool True on success.
     */
    public static function delete_chart(int $chartid): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            // Delete all accounts in this chart.
            $DB->delete_records('qtype_accounting_accounts', ['chartid' => $chartid]);

            // Delete the chart.
            $result = $DB->delete_records('qtype_accounting_charts', ['id' => $chartid]);
            $transaction->allow_commit();
        } catch (\Exception $exception) {
            $transaction->rollback($exception);
        }
        return $result;
    }

    /**
     * Get a chart by ID.
     *
     * @param int $chartid Chart ID.
     * @return object|false The chart record or false if not found.
     */
    public static function get_chart(int $chartid) {
        global $DB;
        return $DB->get_record('qtype_accounting_charts', ['id' => $chartid]);
    }

    /**
     * Get all charts for a context.
     *
     * @param int $contextid Context ID.
     * @param string $sort Column to sort by ('name' or 'timecreated').
     * @param string $dir Sort direction ('ASC' or 'DESC').
     * @return array The chart records.
     */
    public static function get_charts_for_context(int $contextid, string $sort = 'name', string $dir = 'ASC'): array {
        global $DB;

        // Whitelist allowed sort columns.
        $allowedsorts = ['name', 'timecreated'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'name';
        }

        // Validate direction.
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        return $DB->get_records('qtype_accounting_charts', ['contextid' => $contextid], "$sort $dir");
    }

    /**
     * Find a chart by name within a context.
     *
     * @param string $name Chart name.
     * @param int $contextid Context ID.
     * @return object|false The chart record or false if not found.
     */
    public static function get_chart_by_name(string $name, int $contextid) {
        global $DB;
        return $DB->get_record('qtype_accounting_charts', ['name' => $name, 'contextid' => $contextid]);
    }

    /**
     * Export accounts to text file (one name per line, no header).
     *
     * @param int $chartid Chart ID.
     * @return string Text content with one account name per line.
     */
    public static function export_to_csv(int $chartid): string {
        $accounts = account_manager::get_for_chart($chartid);

        $output = '';
        foreach ($accounts as $account) {
            $output .= $account->accountname . "\n";
        }

        return $output;
    }

    /**
     * Import a complete chart of accounts from text content.
     *
     * Each line = one account name.
     *
     * @param string $content Text content with one account name per line.
     * @param int $contextid Context ID.
     * @param string $filename Optional original filename (used as chart name).
     * @return array ['chartid' => int, 'chartname' => string, 'imported' => int, 'errors' => array]
     */
    public static function import_chart_from_csv(string $content, int $contextid, string $filename = ''): array {
        global $DB;

        $result = ['chartid' => 0, 'chartname' => '', 'imported' => 0, 'errors' => []];

        try {
            $parsed = import_helper::parse_csv($content, $filename);
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            // Create the chart with name from data.
            $chartname = $parsed['chartname'];
            $chartid = self::create_chart($chartname, $contextid);
            $result['chartid'] = $chartid;
            $result['chartname'] = $chartname;

            // Import all accounts.
            foreach ($parsed['accounts'] as $accountname => $accountdata) {
                account_manager::add($chartid, $accountdata['accountname'], $accountdata['sortorder']);
                $result['imported']++;
            }

            // If no accounts were imported, delete the empty chart.
            if ($result['imported'] === 0) {
                self::delete_chart($chartid);
                $result['chartid'] = 0;
                $result['chartname'] = '';
                $result['errors'][] = get_string('csvnoentries', 'qtype_accounting');
            }

            $transaction->allow_commit();
        } catch (\Exception $exception) {
            $transaction->rollback($exception);
        }

        return $result;
    }

    /**
     * Find a chart by name in a given context that contains all required account names.
     *
     * @param string $name Chart name to search for.
     * @param int $contextid Context ID to search in.
     * @param array $accounts Array of account data keyed by account name.
     * @param bool $exact Require identical account names and ordering for lossless restore.
     * @return int|null Chart ID if a matching chart is found, null otherwise.
     */
    public static function find_matching_chart_in_context(
        string $name,
        int $contextid,
        array $accounts,
        bool $exact = false
    ): ?int {
        global $DB;

        $charts = $DB->get_records('qtype_accounting_charts', ['name' => $name, 'contextid' => $contextid]);
        if (empty($charts)) {
            return null;
        }

        $requiredaccounts = array_map('strval', array_keys($accounts));
        sort($requiredaccounts);

        $chartaccounts = $DB->get_records_list(
            'qtype_accounting_accounts',
            'chartid',
            array_keys($charts),
            '',
            'id, chartid, accountname, sortorder'
        );
        $accountsbychart = [];
        $ordersbychart = [];
        foreach ($chartaccounts as $account) {
            $accountsbychart[$account->chartid][] = $account->accountname;
            $ordersbychart[$account->chartid][$account->accountname] = (int)$account->sortorder;
        }

        foreach ($charts as $chart) {
            $chartaccountnames = $accountsbychart[$chart->id] ?? [];
            sort($chartaccountnames);

            if ($exact) {
                if ($chartaccountnames !== $requiredaccounts) {
                    continue;
                }
                foreach ($accounts as $accountname => $data) {
                    $order = is_object($data) ? ($data->sortorder ?? 0) : ($data['sortorder'] ?? 0);
                    if ($ordersbychart[$chart->id][$accountname] !== (int)$order) {
                        continue 2;
                    }
                }
            }

            // Check if this chart contains all required accounts.
            $hasall = true;
            foreach ($requiredaccounts as $required) {
                if (!in_array($required, $chartaccountnames)) {
                    $hasall = false;
                    break;
                }
            }

            if ($hasall) {
                return (int) $chart->id;
            }
        }

        return null;
    }

    /**
     * Create a copy of a chart (with all accounts) in a new context.
     *
     * @param int $sourcechartid Source chart ID.
     * @param int $targetcontextid Target context ID.
     * @return int The new chart ID.
     */
    public static function duplicate_chart(int $sourcechartid, int $targetcontextid): int {
        $source = self::get_chart($sourcechartid);
        if (!$source) {
            throw new \moodle_exception('chartnotfound', 'qtype_accounting');
        }

        $newchartid = self::create_chart($source->name, $targetcontextid);

        foreach (account_manager::get_for_chart($sourcechartid) as $account) {
            account_manager::add(
                $newchartid,
                $account->accountname,
                $account->sortorder
            );
        }

        return $newchartid;
    }
}
