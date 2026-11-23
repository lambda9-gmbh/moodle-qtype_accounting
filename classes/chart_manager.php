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
 * Manager class for the site-wide chart of accounts templates.
 *
 * Questions copy the accounts of a template into their own account list, see {@see question_accounts}.
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
        $chart->timecreated = \core\di::get(\core\clock::class)->time();
        $chart->timemodified = $chart->timecreated;
        $chart->usermodified = $USER->id;

        $chartid = $DB->insert_record('qtype_accounting_charts', $chart);
        event\chart_created::create_from_chartid($chartid)->trigger();
        return $chartid;
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
        $chart->timemodified = \core\di::get(\core\clock::class)->time();
        $chart->usermodified = $USER->id;

        $DB->update_record('qtype_accounting_charts', $chart);
        event\chart_updated::create_from_chartid($chartid)->trigger();
        return true;
    }

    /**
     * Record a change of the accounts of a chart: update its modification time and trigger the event.
     *
     * @param int $chartid Chart ID.
     */
    public static function accounts_changed(int $chartid): void {
        global $DB, $USER;

        $DB->update_record('qtype_accounting_charts', (object)[
            'id' => $chartid,
            'timemodified' => \core\di::get(\core\clock::class)->time(),
            'usermodified' => $USER->id,
        ]);
        event\chart_updated::create_from_chartid($chartid)->trigger();
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
        event\chart_deleted::create_from_chartid($chartid)->trigger();
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
     * Whether a chart is a site-wide default chart managed by the administrator.
     *
     * @param \stdClass $chart The chart record.
     * @return bool True if the chart belongs to the system context.
     */
    public static function is_site_chart(\stdClass $chart): bool {
        return (int)$chart->contextid === (int)\context_system::instance()->id;
    }

    /**
     * Get the context of the category that holds a question.
     *
     * @param int $questionid The question ID.
     * @return \context|null The question category context, or null if the question has no category.
     */
    public static function get_question_context(int $questionid): ?\context {
        global $DB;
        $contextid = $DB->get_field_sql(
            "SELECT qc.contextid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
              WHERE qv.questionid = ?",
            [$questionid]
        );
        return $contextid ? \context::instance_by_id($contextid, IGNORE_MISSING) ?: null : null;
    }

    /**
     * Export accounts as a single-column CSV file (one account per line, no header).
     *
     * Names containing a comma, a double quote or a line break are quoted as in RFC 4180;
     * other names are written as they are, so the file stays readable as a plain list.
     *
     * @param int $chartid Chart ID.
     * @return string CSV content with one account name per record.
     */
    public static function export_to_csv(int $chartid): string {
        $accounts = account_manager::get_for_chart($chartid);

        $output = '';
        foreach ($accounts as $account) {
            $name = $account->accountname;
            if (preg_match('/[",\r\n]/', $name)) {
                $name = '"' . str_replace('"', '""', $name) . '"';
            }
            $output .= $name . "\n";
        }

        return $output;
    }

    /**
     * Import a complete chart of accounts from text or ODS content.
     *
     * Each line = one account name.
     *
     * @param string $content Text with one account name per line, or raw ODS content.
     * @param int $contextid Context ID.
     * @param string $filename Optional original filename (used as chart name).
     * @return array ['chartid' => int, 'chartname' => string, 'imported' => int, 'errors' => array]
     */
    public static function import_chart_from_csv(string $content, int $contextid, string $filename = ''): array {
        global $DB;

        $result = ['chartid' => 0, 'chartname' => '', 'imported' => 0, 'errors' => []];

        try {
            $parsed = import_helper::parse_file($content, $filename);
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
}
