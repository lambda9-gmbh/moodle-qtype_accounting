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

namespace qtype_accounting;

/**
 * The accounts of a question version.
 *
 * Every question version has its own account list. An account is identified by its account key,
 * which the correct entries and the responses store. Keys stay the same for unchanged account
 * names when a new version is saved, so attempts can be regraded with the new version.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_accounts {
    /** Database table of the question accounts. */
    public const TABLE = 'qtype_accounting_qaccounts';

    /** Maximum length of an account name. */
    public const MAX_NAME_LENGTH = 255;

    /**
     * Get the accounts of a question in list order.
     *
     * @param int $questionid The question ID.
     * @return array Records with accountkey, accountname and sortorder, keyed by account key.
     */
    public static function get(int $questionid): array {
        global $DB;
        $records = $DB->get_records(
            self::TABLE,
            ['questionid' => $questionid],
            'sortorder, accountkey',
            'accountkey, accountname, sortorder'
        );
        $accounts = [];
        foreach ($records as $record) {
            $accounts[(int)$record->accountkey] = (object)[
                'accountkey' => (int)$record->accountkey,
                'accountname' => $record->accountname,
                'sortorder' => (int)$record->sortorder,
            ];
        }
        return $accounts;
    }

    /**
     * Get the accounts as option records with the account key as ID, as used by the dropdowns.
     *
     * @param array $accounts Records from {@see get()}.
     * @return array Records with id and accountname, keyed by account key and sorted by name.
     */
    public static function as_options(array $accounts): array {
        $options = [];
        foreach ($accounts as $account) {
            $options[(int)$account->accountkey] = (object)[
                'id' => (int)$account->accountkey,
                'accountname' => $account->accountname,
            ];
        }
        uasort($options, fn($a, $b) => strcmp($a->accountname, $b->accountname));
        return $options;
    }

    /**
     * Split an account list entered one per line into account names.
     *
     * Blank lines and surrounding spaces are ignored; later duplicates are dropped.
     *
     * @param string|array $list The text or a list of names.
     * @return string[] The account names in list order.
     */
    public static function parse_names($list): array {
        $lines = is_array($list) ? $list : preg_split('/\R/u', (string)$list);
        $names = [];
        foreach ($lines as $line) {
            $name = trim((string)$line);
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * Check an account list entered one per line.
     *
     * @param string $list The text.
     * @return string|null An error message, or null if the list is valid.
     */
    public static function validate_list(string $list): ?string {
        $seen = [];
        foreach (preg_split('/\R/u', $list) as $line) {
            $name = trim($line);
            if ($name === '') {
                continue;
            }
            if (\core_text::strlen($name) > self::MAX_NAME_LENGTH) {
                return get_string('err_accountnametoolong', 'qtype_accounting', self::MAX_NAME_LENGTH);
            }
            if (isset($seen[$name])) {
                return get_string('err_accountduplicate', 'qtype_accounting', $name);
            }
            $seen[$name] = true;
        }
        return null;
    }

    /**
     * Assign account keys to names: known names keep their key, new names get the next free keys in list order.
     *
     * The question editor's JavaScript assigns keys to new names the same way.
     *
     * @param string[] $names The account names in list order.
     * @param array $knownkeys Keys of the previous version, keyed by account name.
     * @return array Account keys keyed by account name, in list order.
     */
    public static function assign_keys(array $names, array $knownkeys): array {
        $next = $knownkeys ? max(array_map('intval', $knownkeys)) + 1 : 1;
        $keys = [];
        foreach ($names as $name) {
            if (isset($knownkeys[$name])) {
                $keys[$name] = (int)$knownkeys[$name];
            } else {
                $keys[$name] = $next++;
            }
        }
        return $keys;
    }

    /**
     * Get the account keys of a question keyed by account name.
     *
     * @param array $accounts Records from {@see get()}.
     * @return array Account keys keyed by account name.
     */
    public static function keys_by_name(array $accounts): array {
        $keys = [];
        foreach ($accounts as $account) {
            $keys[$account->accountname] = (int)$account->accountkey;
        }
        return $keys;
    }

    /**
     * Replace the accounts of a question.
     *
     * @param int $questionid The question ID.
     * @param array $keys Account keys keyed by account name, in list order.
     */
    public static function save(int $questionid, array $keys): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['questionid' => $questionid]);
        $records = [];
        $sortorder = 0;
        foreach ($keys as $name => $key) {
            $records[] = [
                'questionid' => $questionid,
                'accountkey' => (int)$key,
                'accountname' => (string)$name,
                'sortorder' => $sortorder++,
            ];
        }
        if ($records) {
            $DB->insert_records(self::TABLE, $records);
        }
    }

    /**
     * Delete the accounts of a question.
     *
     * @param int $questionid The question ID.
     */
    public static function delete(int $questionid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['questionid' => $questionid]);
    }

    /**
     * Get the account lists that can be copied into a question: the site templates and the other
     * accounting questions in the same question bank.
     *
     * @param \context|null $context The question bank context, or null for the site templates only.
     * @param int $excludequestionid A question whose own accounts are not offered.
     * @return array Option labels keyed by source ('t-<chart ID>' or 'q-<question ID>'), grouped by source type.
     */
    public static function get_copy_sources(?\context $context, int $excludequestionid = 0): array {
        global $DB;
        $templates = [];
        foreach (chart_manager::get_charts_for_context(\context_system::instance()->id) as $chart) {
            $templates['t-' . $chart->id] = format_string($chart->name, true, ['context' => \context_system::instance()]);
        }
        $questions = [];
        if ($context) {
            $records = $DB->get_records_sql(
                "SELECT q.id, q.name, qc.name AS categoryname
                   FROM {question} q
                   JOIN {question_versions} qv ON qv.questionid = q.id
                   JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                   JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  WHERE qc.contextid = :contextid AND q.qtype = :qtype AND q.id <> :exclude
                    AND qv.version = (SELECT MAX(v.version) FROM {question_versions} v
                                       WHERE v.questionbankentryid = qbe.id)
                    AND EXISTS (SELECT 1 FROM {" . self::TABLE . "} a WHERE a.questionid = q.id)
               ORDER BY qc.name, q.name",
                ['contextid' => $context->id, 'qtype' => 'accounting', 'exclude' => $excludequestionid],
                0,
                500
            );
            foreach ($records as $record) {
                $questions['q-' . $record->id] = format_string($record->categoryname, true, ['context' => $context])
                    . ': ' . format_string($record->name, true, ['context' => $context]);
            }
        }
        $groups = [];
        if ($templates) {
            $groups[get_string('copyaccounts_templates', 'qtype_accounting')] = $templates;
        }
        if ($questions) {
            $groups[get_string('copyaccounts_questions', 'qtype_accounting')] = $questions;
        }
        return $groups;
    }

    /**
     * Get the account names of a copy source.
     *
     * @param string $source A source key from {@see get_copy_sources()}.
     * @param \context|null $context The question bank context, which a copied question must belong to.
     * @return string[] The account names in list order, or an empty list for unknown sources.
     */
    public static function get_source_names(string $source, ?\context $context): array {
        global $DB;
        if (!preg_match('/^([tq])-(\d+)$/', $source, $matches)) {
            return [];
        }
        $id = (int)$matches[2];
        if ($matches[1] === 't') {
            $chart = chart_manager::get_chart($id);
            if (!$chart || !chart_manager::is_site_chart($chart)) {
                return [];
            }
            $accounts = account_manager::get_for_chart($id, 'sortorder, accountname');
            return array_values(array_map(fn($account) => $account->accountname, $accounts));
        }
        if (!$context || chart_manager::get_question_context($id)?->id !== $context->id) {
            return [];
        }
        return array_values(array_map(fn($account) => $account->accountname, self::get($id)));
    }
}
