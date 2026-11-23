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
 * XML import/export for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Handles the Moodle-XML question-export and question-import paths.
 *
 * Extracted from {@see \qtype_accounting} so the question_type subclass can stay
 * focused on the persistence + bootstrap responsibilities Moodle imposes.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xml_handler {
    /**
     * Render the <entries> XML block for question export.
     *
     * Account keys are written as portable account names; unknown keys produce empty strings.
     *
     * @param array $entries The entries from $question->options->entries.
     * @param array $accounts The question's accounts keyed by account key.
     * @param \qformat_xml $format The XML format helper used for escaping.
     * @return string The XML fragment.
     */
    public function export_entries(array $entries, array $accounts, \qformat_xml $format): string {
        $expout = "    <entries>\n";
        foreach ($entries as $entry) {
            $debitname = $accounts[(int)$entry->debitaccountid]->accountname ?? '';
            $creditname = $accounts[(int)$entry->creditaccountid]->accountname ?? '';
            $expout .= "      <entry>\n";
            $expout .= "        <sortorder>{$entry->sortorder}</sortorder>\n";
            $expout .= "        <debitaccount>" . $format->xml_escape($debitname) . "</debitaccount>\n";
            $expout .= "        <debitamount>{$entry->debitamount}</debitamount>\n";
            $expout .= "        <creditaccount>" . $format->xml_escape($creditname) . "</creditaccount>\n";
            $expout .= "        <creditamount>{$entry->creditamount}</creditamount>\n";
            $expout .= "        <weight_debitaccount>" . ($entry->weight_debitaccount ?? 1) . "</weight_debitaccount>\n";
            $expout .= "        <weight_debitamount>" . ($entry->weight_debitamount ?? 1) . "</weight_debitamount>\n";
            $expout .= "        <weight_creditaccount>" . ($entry->weight_creditaccount ?? 1) . "</weight_creditaccount>\n";
            $expout .= "        <weight_creditamount>" . ($entry->weight_creditamount ?? 1) . "</weight_creditamount>\n";
            $expout .= "        <explanation>" . $format->xml_escape($entry->explanation ?? '') . "</explanation>\n";
            $expout .= "      </entry>\n";
        }
        $expout .= "    </entries>\n";
        return $expout;
    }

    /**
     * Render the question's accounts as a <chartofaccounts> block for question export.
     *
     * The block keeps the format of earlier versions, which stored a shared chart; the question
     * name serves as chart name for them. Account keys are added so a re-import keeps them.
     *
     * @param string $questionname The question name.
     * @param array $accounts The question's accounts keyed by account key.
     * @param \qformat_xml $format The XML format helper used for escaping.
     * @return string The XML fragment.
     */
    public function export_accounts(string $questionname, array $accounts, \qformat_xml $format): string {
        $expout = "    <chartofaccounts>\n";
        $expout .= "      <chartname>" . $format->xml_escape($questionname) . "</chartname>\n";
        foreach ($accounts as $account) {
            $expout .= "      <account>\n";
            $expout .= "        <accountname>" . $format->xml_escape($account->accountname) . "</accountname>\n";
            $expout .= "        <sortorder>{$account->sortorder}</sortorder>\n";
            $expout .= "        <accountkey>{$account->accountkey}</accountkey>\n";
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
        return $qo;
    }

    /**
     * Populate the entry arrays on the imported question object from the XML <entries> block.
     *
     * Account fields hold names at this point; import_accounts() converts them to account keys.
     *
     * @param array $data The XML data array.
     * @param \qformat_xml $format The XML format helper.
     * @param \stdClass $qo The question object to populate (modified in place).
     */
    public function import_entries(array $data, \qformat_xml $format, \stdClass $qo): void {
        $qo->debitaccount = [];
        $qo->debitamount = [];
        $qo->creditaccount = [];
        $qo->creditamount = [];
        $qo->weight_debitaccount = [];
        $qo->weight_debitamount = [];
        $qo->weight_creditaccount = [];
        $qo->weight_creditamount = [];

        $entries = $format->getpath($data, ['#', 'entries', 0, '#', 'entry'], []);
        foreach ($entries as $i => $entrydata) {
            $qo->debitaccount[$i] = $format->getpath($entrydata, ['#', 'debitaccount', 0, '#'], '');
            $qo->debitamount[$i] = $format->getpath($entrydata, ['#', 'debitamount', 0, '#'], 0);
            $qo->creditaccount[$i] = $format->getpath($entrydata, ['#', 'creditaccount', 0, '#'], '');
            $qo->creditamount[$i] = $format->getpath($entrydata, ['#', 'creditamount', 0, '#'], 0);
            $qo->weight_debitaccount[$i] = (int) $format->getpath($entrydata, ['#', 'weight_debitaccount', 0, '#'], 1);
            $qo->weight_debitamount[$i] = (int) $format->getpath($entrydata, ['#', 'weight_debitamount', 0, '#'], 1);
            $qo->weight_creditaccount[$i] = (int) $format->getpath($entrydata, ['#', 'weight_creditaccount', 0, '#'], 1);
            $qo->weight_creditamount[$i] = (int) $format->getpath($entrydata, ['#', 'weight_creditamount', 0, '#'], 1);
        }
    }

    /**
     * Read the accounts from the <chartofaccounts> block and convert the entry account names to account keys.
     *
     * Exports of earlier versions have no account keys; their accounts get keys in list order.
     *
     * @param array $data The XML data array.
     * @param \qformat_xml $format The XML format helper.
     * @param \stdClass $qo The question object to update (accounts, accountkeys and entry account keys).
     */
    public function import_accounts(array $data, \qformat_xml $format, \stdClass $qo): void {
        $accounts = [];
        foreach ($format->getpath($data, ['#', 'chartofaccounts', 0, '#', 'account'], []) as $accdata) {
            $name = trim((string)$format->getpath($accdata, ['#', 'accountname', 0, '#'], ''));
            if ($name === '' || isset($accounts[$name])) {
                continue;
            }
            $accounts[$name] = [
                'sortorder' => (int)$format->getpath($accdata, ['#', 'sortorder', 0, '#'], 0),
                'key' => (int)$format->getpath($accdata, ['#', 'accountkey', 0, '#'], 0),
            ];
        }
        uasort($accounts, fn($a, $b) => $a['sortorder'] <=> $b['sortorder']);
        // Numeric names such as 1200 became integer array keys.
        $names = array_map('strval', array_keys($accounts));
        $keys = array_column($accounts, 'key');
        $haskeys = $keys && !in_array(0, $keys, true) && count(array_unique($keys)) === count($keys);
        $qo->accounts = $names;
        $qo->accountkeys = $haskeys ? array_combine($names, $keys) : question_accounts::assign_keys($names, []);
        foreach (['debitaccount', 'creditaccount'] as $field) {
            foreach ($qo->$field as $index => $name) {
                $qo->{$field}[$index] = $qo->accountkeys[$name] ?? 0;
            }
        }
    }
}
