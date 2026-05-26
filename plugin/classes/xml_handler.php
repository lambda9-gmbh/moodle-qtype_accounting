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
 * XML import/export for the Buchungssatz question type.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_buchungssatz;

/**
 * Handles the Moodle-XML question-export and question-import paths.
 *
 * Extracted from {@see \qtype_buchungssatz} so the question_type subclass can stay
 * focused on the persistence + bootstrap responsibilities Moodle imposes.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xml_handler {
    /**
     * Render the <entries> XML block for question export.
     *
     * Account IDs are resolved to portable names; missing accounts produce empty strings.
     *
     * @param array $entries The entries from $question->options->entries.
     * @param \qformat_xml $format The XML format helper used for escaping.
     * @return string The XML fragment.
     */
    public function export_entries(array $entries, \qformat_xml $format): string {
        global $DB;
        $expout = "    <entries>\n";
        foreach ($entries as $entry) {
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
        return $expout;
    }

    /**
     * Render the <chartofaccounts> XML block for question export.
     *
     * Returns the empty string if the chart no longer exists.
     *
     * @param int $chartid The chart of accounts ID.
     * @param \qformat_xml $format The XML format helper used for escaping.
     * @return string The XML fragment, or '' if the chart cannot be loaded.
     */
    public function export_chart(int $chartid, \qformat_xml $format): string {
        $chart = chart_manager::get_chart($chartid);
        if (!$chart) {
            return '';
        }
        $accounts = account_manager::get_for_chart($chartid);
        $expout = "    <chartofaccounts>\n";
        $expout .= "      <chartname>" . $format->xml_escape($chart->name) . "</chartname>\n";
        foreach ($accounts as $account) {
            $expout .= "      <account>\n";
            $expout .= "        <accountname>" . $format->xml_escape($account->accountname) . "</accountname>\n";
            $expout .= "        <sortorder>{$account->sortorder}</sortorder>\n";
            $expout .= "      </account>\n";
        }
        $expout .= "    </chartofaccounts>\n";
        return $expout;
    }

    /**
     * Build the imported question object's headers and option fields from XML.
     *
     * @param array $data The XML data array.
     * @param \qformat_xml $format The XML format helper.
     * @param string $qtype The question type identifier from the XML.
     * @param array $extraquestionfields Field names from extra_question_fields() (without the leading table name).
     * @return \stdClass The question object populated with headers and options-table fields.
     */
    public function import_options(
        array $data,
        \qformat_xml $format,
        string $qtype,
        array $extraquestionfields
    ): \stdClass {
        // We cannot call parent::import_from_xml because the base implementation
        // unconditionally parses 'answer' elements.
        $qo = $format->import_headers($data);
        $qo->qtype = $qtype;
        foreach ($extraquestionfields as $field) {
            $qo->$field = $format->getpath($data, ['#', $field, 0, '#'], '');
        }
        // The source system's chart ID will be re-resolved from the embedded chart data.
        $qo->chartofaccountsid = 0;
        return $qo;
    }

    /**
     * Populate the entry arrays on the imported question object from the XML <entries> block.
     *
     * Account fields hold names at this point; resolve_chart() converts them to IDs.
     *
     * @param array $data The XML data array.
     * @param \qformat_xml $format The XML format helper.
     * @param \stdClass $qo The question object to populate (modified in place).
     */
    public function import_entries(array $data, \qformat_xml $format, \stdClass $qo): void {
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
            $qo->weight_sollkonto[$i] = (int) $format->getpath($entrydata, ['#', 'weight_sollkonto', 0, '#'], 1);
            $qo->weight_sollbetrag[$i] = (int) $format->getpath($entrydata, ['#', 'weight_sollbetrag', 0, '#'], 1);
            $qo->weight_habenkonto[$i] = (int) $format->getpath($entrydata, ['#', 'weight_habenkonto', 0, '#'], 1);
            $qo->weight_habenbetrag[$i] = (int) $format->getpath($entrydata, ['#', 'weight_habenbetrag', 0, '#'], 1);
        }
    }

    /**
     * Determine the target course context ID for chart resolution during import.
     *
     * Falls back through course → category context → 0.
     *
     * @param \qformat_xml $format The XML format helper (carries $course / $category metadata).
     * @return int The context ID to use, or 0 if none could be resolved.
     */
    public function resolve_contextid(\qformat_xml $format): int {
        if (!empty($format->course->id)) {
            return \context_course::instance($format->course->id)->id;
        }
        if (empty($format->category->contextid)) {
            return 0;
        }
        // Category context may be a course context or a system context.
        $catcontext = \context::instance_by_id($format->category->contextid, IGNORE_MISSING);
        if (!$catcontext) {
            return 0;
        }
        $coursecontext = $catcontext->get_course_context(false);
        return $coursecontext ? $coursecontext->id : $catcontext->id;
    }

    /**
     * Resolve the embedded chart-of-accounts data: find an existing matching chart
     * in the target context, or create a new one, then map account names to IDs.
     *
     * @param array $chartdata The <chartofaccounts> sub-array from the XML.
     * @param \qformat_xml $format The XML format helper.
     * @param int $contextid The target context ID.
     * @param \stdClass $qo The question object to update (chartofaccountsid + account-name → ID).
     */
    public function resolve_chart(
        array $chartdata,
        \qformat_xml $format,
        int $contextid,
        \stdClass $qo
    ): void {
        $chartname = $format->getpath($chartdata, ['#', 'chartname', 0, '#'], '');
        $xmlaccounts = $format->getpath($chartdata, ['#', 'account'], []);
        if (empty($chartname) || empty($xmlaccounts)) {
            return;
        }
        [$accountsbyname, $accountslist] = $this->parse_xml_accounts($xmlaccounts, $format);
        $chartid = chart_manager::find_matching_chart_in_context($chartname, $contextid, $accountsbyname);
        if (!$chartid) {
            $chartid = chart_manager::create_chart($chartname, $contextid);
            foreach ($accountslist as $acc) {
                account_manager::add($chartid, $acc['accountname'], $acc['sortorder']);
            }
        }
        $qo->chartofaccountsid = $chartid;
        $this->resolve_account_names_to_ids($chartid, $qo);
    }

    /**
     * Parse the <account> children of a <chartofaccounts> block into convenient arrays.
     *
     * @param array $xmlaccounts The XML 'account' children.
     * @param \qformat_xml $format The XML format helper.
     * @return array [accountsbyname, accountslist].
     */
    protected function parse_xml_accounts(array $xmlaccounts, \qformat_xml $format): array {
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
        return [$accountsbyname, $accountslist];
    }

    /**
     * Replace the account names on $qo->sollkonto / $qo->habenkonto with the resolved IDs from the chart.
     *
     * @param int $chartid The resolved chart ID.
     * @param \stdClass $qo The question object (modified in place).
     */
    protected function resolve_account_names_to_ids(int $chartid, \stdClass $qo): void {
        $nametoid = [];
        foreach (account_manager::get_for_chart($chartid) as $acc) {
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
