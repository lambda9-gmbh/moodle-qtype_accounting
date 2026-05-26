<?php
// This file is part of Moodle - https://moodle.org/
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
// along with MoFT BuSa.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Question type class for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * The Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz extends question_type {
    /**
     * Whether this question type can be used for random questions.
     *
     * @return bool
     */
    public function is_usable_by_random(): bool {
        return false;
    }

    /**
     * Move files from the old area to the new.
     *
     * @param int $questionid The question ID.
     * @param int $oldcontextid The old context ID.
     * @param int $newcontextid The new context ID.
     */
    public function move_files($questionid, $oldcontextid, $newcontextid): void {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    /**
     * Delete the files used by this question.
     *
     * @param int $questionid The question ID.
     * @param int $contextid The context ID.
     */
    protected function delete_files($questionid, $contextid): void {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    /**
     * Save the question-type specific options.
     *
     * @param object $question The question being saved.
     * @return bool True on success.
     */
    public function save_question_options($question) {
        global $DB;

        // Delete old options.
        $DB->delete_records('qtype_buchungssatz_options', ['questionid' => $question->id]);
        $DB->delete_records('qtype_buchungssatz_entries', ['questionid' => $question->id]);

        // Save options.
        $options = new stdClass();
        $options->questionid = $question->id;
        $options->chartofaccountsid = $question->chartofaccountsid ?? 0;
        $options->accountsindropdown = $question->accountsindropdown ?? 0;
        $options->numberformat = $question->numberformat ?? 'de';
        $options->extraentrydeduction = $question->extraentrydeduction ?? null;
        $options->allornothinggrading = $question->allornothinggrading ?? 0;
        $options->allowmultipleentries = $question->allowmultipleentries ?? 1;
        $options->maxentries = $question->maxentries ?? 5;
        $DB->insert_record('qtype_buchungssatz_options', $options);

        // Save correct answer entries. Either Debit (Soll) or Credit (Haben) account is required.
        // Form fields are arrays: sollkonto[], sollbetrag[], etc.
        // Also handle Behat data where values may be strings instead of arrays.
        $sortorder = 0;

        // Ensure all field arrays are properly formatted.
        $sollkontoarray = $question->sollkonto ?? [];
        if (!is_array($sollkontoarray)) {
            $sollkontoarray = [$sollkontoarray];
        }
        $sollbetragarray = $question->sollbetrag ?? [];
        if (!is_array($sollbetragarray)) {
            $sollbetragarray = [$sollbetragarray];
        }
        $habenkontoarray = $question->habenkonto ?? [];
        if (!is_array($habenkontoarray)) {
            $habenkontoarray = [$habenkontoarray];
        }
        $habenbetragarray = $question->habenbetrag ?? [];
        if (!is_array($habenbetragarray)) {
            $habenbetragarray = [$habenbetragarray];
        }
        // Weight fields arrays.
        $weightsollkontoarray = $question->weight_sollkonto ?? [];
        if (!is_array($weightsollkontoarray)) {
            $weightsollkontoarray = [$weightsollkontoarray];
        }
        $weightsollbetragarray = $question->weight_sollbetrag ?? [];
        if (!is_array($weightsollbetragarray)) {
            $weightsollbetragarray = [$weightsollbetragarray];
        }
        $weighthabenkontoarray = $question->weight_habenkonto ?? [];
        if (!is_array($weighthabenkontoarray)) {
            $weighthabenkontoarray = [$weighthabenkontoarray];
        }
        $weighthabenbetragarray = $question->weight_habenbetrag ?? [];
        if (!is_array($weighthabenbetragarray)) {
            $weighthabenbetragarray = [$weighthabenbetragarray];
        }

        // Get all unique indices from both sollkonto and habenkonto arrays.
        $allindices = array_unique(array_merge(array_keys($sollkontoarray), array_keys($habenkontoarray)));
        sort($allindices);

        foreach ($allindices as $i) {
            $sollkontoid = intval($sollkontoarray[$i] ?? 0);
            $habenkontoid = intval($habenkontoarray[$i] ?? 0);

            // Save entries that have either a Debit (Soll) or Credit (Haben) account.
            if (empty($sollkontoid) && empty($habenkontoid)) {
                continue;
            }

            $record = new stdClass();
            $record->questionid = $question->id;
            $record->sortorder = $sortorder++;
            $record->sollkontoid = $sollkontoid ?: null;
            $record->sollbetrag = floatval($sollbetragarray[$i] ?? 0);
            $record->habenkontoid = $habenkontoid ?: null;
            $record->habenbetrag = floatval($habenbetragarray[$i] ?? 0);
            $record->weight_sollkonto = intval($weightsollkontoarray[$i] ?? 1);
            $record->weight_sollbetrag = intval($weightsollbetragarray[$i] ?? 1);
            $record->weight_habenkonto = intval($weighthabenkontoarray[$i] ?? 1);
            $record->weight_habenbetrag = intval($weighthabenbetragarray[$i] ?? 1);
            $record->explanation = '';
            $DB->insert_record('qtype_buchungssatz_entries', $record);
        }

        $this->save_hints($question);

        return true;
    }

    /**
     * Get the question options.
     *
     * @param object $question The question object to populate.
     * @return bool True on success.
     */
    public function get_question_options($question): bool {
        global $DB;

        $question->options = $DB->get_record(
            'qtype_buchungssatz_options',
            ['questionid' => $question->id]
        );

        if ($question->options === false) {
            $question->options = new stdClass();
            $question->options->questionid = $question->id;
            $question->options->chartofaccountsid = 0;
            $question->options->accountsindropdown = 0;
            $question->options->numberformat = 'de';
            $question->options->extraentrydeduction = null;
            $question->options->allornothinggrading = 0;
            $question->options->allowmultipleentries = 1;
            $question->options->maxentries = 5;
        }

        $question->options->entries = $DB->get_records(
            'qtype_buchungssatz_entries',
            ['questionid' => $question->id],
            'sortorder ASC'
        );

        parent::get_question_options($question);
        return true;
    }

    /**
     * Initialise the common question_definition fields.
     *
     * @param question_definition $question The question definition to initialise.
     * @param object $questiondata The question data from the database.
     */
    protected function initialise_question_instance(question_definition $question, $questiondata): void {
        global $DB;
        parent::initialise_question_instance($question, $questiondata);

        $question->chartofaccountsid = $questiondata->options->chartofaccountsid ?? 0;
        $question->accountsindropdown = $questiondata->options->accountsindropdown ?? 0;
        $question->numberformat = $questiondata->options->numberformat ?? 'de';
        $question->extraentrydeduction = $questiondata->options->extraentrydeduction ?? null;
        $question->allornothinggrading = $questiondata->options->allornothinggrading ?? 0;
        $question->allowmultipleentries = $questiondata->options->allowmultipleentries ?? 1;
        $question->maxentries = $questiondata->options->maxentries ?? 5;
        $question->entries = [];

        if (!empty($questiondata->options->entries)) {
            foreach ($questiondata->options->entries as $entry) {
                $question->entries[] = [
                    'sollkontoid' => $entry->sollkontoid ? (int) $entry->sollkontoid : null,
                    'sollbetrag' => $entry->sollbetrag,
                    'habenkontoid' => $entry->habenkontoid ? (int) $entry->habenkontoid : null,
                    'habenbetrag' => $entry->habenbetrag,
                    'weight_sollkonto' => $entry->weight_sollkonto ?? 1,
                    'weight_sollbetrag' => $entry->weight_sollbetrag ?? 1,
                    'weight_habenkonto' => $entry->weight_habenkonto ?? 1,
                    'weight_habenbetrag' => $entry->weight_habenbetrag ?? 1,
                    'explanation' => $entry->explanation ?? '',
                ];
            }
        }

        // Load account lookup map for display (ID => name).
        $question->accountsmap = [];
        if ($question->chartofaccountsid > 0) {
            $accounts = $DB->get_records(
                'qtype_buchungssatz_accounts',
                ['chartid' => $question->chartofaccountsid]
            );
            foreach ($accounts as $acc) {
                $question->accountsmap[$acc->id] = $acc->accountname;
            }
        }
    }

    /**
     * Delete a question from the database.
     *
     * @param int $questionid The question ID.
     * @param int $contextid The context ID.
     */
    public function delete_question($questionid, $contextid): void {
        global $DB;

        $DB->delete_records('qtype_buchungssatz_options', ['questionid' => $questionid]);
        $DB->delete_records('qtype_buchungssatz_entries', ['questionid' => $questionid]);

        parent::delete_question($questionid, $contextid);
    }

    /**
     * Get the random guess score.
     *
     * @param object $questiondata The question data.
     * @return float|null The random guess score.
     */
    public function get_random_guess_score($questiondata): ?float {
        return 0;
    }

    /**
     * Get possible responses for reporting.
     *
     * @param object $questiondata The question data.
     * @return array The possible responses.
     */
    public function get_possible_responses($questiondata): array {
        return [];
    }

    /**
     * Define extra columns needed in the question bank.
     *
     * @return array The extra question fields.
     */
    public function extra_question_fields(): array {
        return ['qtype_buchungssatz_options', 'chartofaccountsid', 'accountsindropdown', 'numberformat',
            'extraentrydeduction', 'allornothinggrading',
            'allowmultipleentries', 'maxentries'];
    }

    /**
     * Export this question to the Moodle XML format.
     *
     * Appends correct answer entries and full chart of accounts data
     * to the auto-exported options fields from extra_question_fields().
     *
     * @param object $question The question data to export.
     * @param qformat_xml $format The XML format helper.
     * @param mixed $extra Any additional format specific data.
     * @return string The XML fragment for this question type.
     */
    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        global $DB;
        // Parent exports the extra_question_fields (options table fields).
        $expout = parent::export_to_xml($question, $format, $extra);

        // Export correct answer entries. Resolve IDs to names for portable XML.
        if (!empty($question->options->entries)) {
            $expout .= "    <entries>\n";
            foreach ($question->options->entries as $entry) {
                $sollname = '';
                if (!empty($entry->sollkontoid)) {
                    $acc = $DB->get_record('qtype_buchungssatz_accounts', ['id' => $entry->sollkontoid]);
                    $sollname = $acc ? $acc->accountname : '';
                }
                $habenname = '';
                if (!empty($entry->habenkontoid)) {
                    $acc = $DB->get_record('qtype_buchungssatz_accounts', ['id' => $entry->habenkontoid]);
                    $habenname = $acc ? $acc->accountname : '';
                }
                $expout .= "      <entry>\n";
                $expout .= "        <sortorder>{$entry->sortorder}</sortorder>\n";
                $expout .= "        <sollkonto>" . $format->xml_escape($sollname) . "</sollkonto>\n";
                $expout .= "        <sollbetrag>{$entry->sollbetrag}</sollbetrag>\n";
                $expout .= "        <habenkonto>" . $format->xml_escape($habenname) . "</habenkonto>\n";
                $expout .= "        <habenbetrag>{$entry->habenbetrag}</habenbetrag>\n";
                $expout .= "        <weight_sollkonto>" . ($entry->weight_sollkonto ?? 1) . "</weight_sollkonto>\n";
                $expout .= "        <weight_sollbetrag>" . ($entry->weight_sollbetrag ?? 1) . "</weight_sollbetrag>\n";
                $expout .= "        <weight_habenkonto>" . ($entry->weight_habenkonto ?? 1) . "</weight_habenkonto>\n";
                $expout .= "        <weight_habenbetrag>" . ($entry->weight_habenbetrag ?? 1) . "</weight_habenbetrag>\n";
                $expout .= "        <explanation>" . $format->xml_escape($entry->explanation ?? '') . "</explanation>\n";
                $expout .= "      </entry>\n";
            }
            $expout .= "    </entries>\n";
        }

        // Export chart of accounts with full account data.
        $chartid = $question->options->chartofaccountsid ?? 0;
        if ($chartid > 0) {
            $chart = \qtype_buchungssatz\chart_manager::get_chart($chartid);
            if ($chart) {
                $accounts = \qtype_buchungssatz\chart_manager::get_accounts($chartid);
                $expout .= "    <chartofaccounts>\n";
                $expout .= "      <chartname>" . $format->xml_escape($chart->name) . "</chartname>\n";
                foreach ($accounts as $account) {
                    $expout .= "      <account>\n";
                    $expout .= "        <accountname>" . $format->xml_escape($account->accountname)
                        . "</accountname>\n";
                    $expout .= "        <sortorder>{$account->sortorder}</sortorder>\n";
                    $expout .= "      </account>\n";
                }
                $expout .= "    </chartofaccounts>\n";
            }
        }

        return $expout;
    }

    /**
     * Import a question from the Moodle XML format.
     *
     * Parses correct answer entries and chart of accounts data,
     * resolving the chart to an existing or newly created chart
     * in the target course context.
     *
     * @param array $data The XML data for this question.
     * @param object $question The default question object.
     * @param qformat_xml $format The XML format helper.
     * @param mixed $extra Any additional format specific data.
     * @return object|false The question object, or false if not this type.
     */
    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        // Check this is our question type.
        $qtype = $data['@']['type'];
        if ($qtype != $this->name()) {
            return false;
        }

        // Import headers and extra_question_fields (cannot call parent because
        // the base implementation unconditionally parses 'answer' elements).
        $qo = $format->import_headers($data);
        $qo->qtype = $qtype;

        $extraquestionfields = $this->extra_question_fields();
        array_shift($extraquestionfields); // Remove table name.
        foreach ($extraquestionfields as $field) {
            $qo->$field = $format->getpath($data, ['#', $field, 0, '#'], '');
        }

        // Clear the source system's chart ID — it will be resolved from the chart data below.
        $qo->chartofaccountsid = 0;

        // Parse correct answer entries (account names from XML, resolved to IDs below).
        $qo->sollkonto = [];
        $qo->sollbetrag = [];
        $qo->habenkonto = [];
        $qo->habenbetrag = [];
        $qo->weight_sollkonto = [];
        $qo->weight_sollbetrag = [];
        $qo->weight_habenkonto = [];
        $qo->weight_habenbetrag = [];

        $entries = $format->getpath($data, ['#', 'entries', 0, '#', 'entry'], []);
        foreach ($entries as $i => $entrydata) {
            $qo->sollkonto[$i] = $format->getpath($entrydata, ['#', 'sollkonto', 0, '#'], '');
            $qo->sollbetrag[$i] = $format->getpath($entrydata, ['#', 'sollbetrag', 0, '#'], 0);
            $qo->habenkonto[$i] = $format->getpath($entrydata, ['#', 'habenkonto', 0, '#'], '');
            $qo->habenbetrag[$i] = $format->getpath($entrydata, ['#', 'habenbetrag', 0, '#'], 0);
            $qo->weight_sollkonto[$i] = (int) $format->getpath(
                $entrydata,
                ['#', 'weight_sollkonto', 0, '#'],
                1
            );
            $qo->weight_sollbetrag[$i] = (int) $format->getpath(
                $entrydata,
                ['#', 'weight_sollbetrag', 0, '#'],
                1
            );
            $qo->weight_habenkonto[$i] = (int) $format->getpath(
                $entrydata,
                ['#', 'weight_habenkonto', 0, '#'],
                1
            );
            $qo->weight_habenbetrag[$i] = (int) $format->getpath(
                $entrydata,
                ['#', 'weight_habenbetrag', 0, '#'],
                1
            );
        }

        // Determine target course context for chart resolution.
        $contextid = 0;
        if (!empty($format->course->id)) {
            $coursecontext = \context_course::instance($format->course->id);
            $contextid = $coursecontext->id;
        } else if (!empty($format->category->contextid)) {
            // Category context may be a course context or a system context.
            // Try to get the course context from it.
            $catcontext = \context::instance_by_id($format->category->contextid, IGNORE_MISSING);
            if ($catcontext) {
                $coursecontext = $catcontext->get_course_context(false);
                $contextid = $coursecontext ? $coursecontext->id : $catcontext->id;
            }
        }

        // Parse and resolve chart of accounts.
        $chartdata = $format->getpath($data, ['#', 'chartofaccounts', 0], null);
        if ($chartdata !== null && $contextid > 0) {
            $chartname = $format->getpath($chartdata, ['#', 'chartname', 0, '#'], '');
            $xmlaccounts = $format->getpath($chartdata, ['#', 'account'], []);

            if (!empty($chartname) && !empty($xmlaccounts)) {
                // Build accounts array keyed by account name.
                $accountsbyname = [];
                $accountslist = [];
                foreach ($xmlaccounts as $accdata) {
                    $accountname = $format->getpath($accdata, ['#', 'accountname', 0, '#'], '');
                    $sortorder = (int) $format->getpath($accdata, ['#', 'sortorder', 0, '#'], 0);

                    $accountsbyname[$accountname] = [
                        'accountname' => $accountname,
                        'sortorder' => $sortorder,
                    ];
                    $accountslist[] = $accountsbyname[$accountname];
                }

                // Try to find existing matching chart in target context.
                $chartid = \qtype_buchungssatz\chart_manager::find_matching_chart_in_context(
                    $chartname,
                    $contextid,
                    $accountsbyname
                );

                if (!$chartid) {
                    // Create new chart with all accounts.
                    $chartid = \qtype_buchungssatz\chart_manager::create_chart($chartname, $contextid);
                    foreach ($accountslist as $acc) {
                        \qtype_buchungssatz\chart_manager::add_account(
                            $chartid,
                            $acc['accountname'],
                            $acc['sortorder']
                        );
                    }
                }

                $qo->chartofaccountsid = $chartid;

                // Resolve imported account names to IDs.
                $chartaccounts = \qtype_buchungssatz\chart_manager::get_accounts($chartid);
                $nametoid = [];
                foreach ($chartaccounts as $acc) {
                    $nametoid[$acc->accountname] = $acc->id;
                }
                foreach ($qo->sollkonto as $idx => $name) {
                    $qo->sollkonto[$idx] = $nametoid[$name] ?? 0;
                }
                foreach ($qo->habenkonto as $idx => $name) {
                    $qo->habenkonto[$idx] = $nametoid[$name] ?? 0;
                }
            }
        }

        return $qo;
    }
}
