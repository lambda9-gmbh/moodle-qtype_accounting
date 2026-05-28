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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/accounting/questiontype.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Unit tests for qtype_accounting (question type class).
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_accounting
 */
class questiontype_test extends \advanced_testcase {
    /** @var \qtype_accounting The question type instance. */
    protected $qtype;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->qtype = new \qtype_accounting();
    }

    /**
     * Test the name method.
     */
    public function test_name(): void {
        $this->assertEquals('accounting', $this->qtype->name());
    }

    /**
     * Test is_usable_by_random returns false.
     */
    public function test_is_usable_by_random(): void {
        $this->assertFalse($this->qtype->is_usable_by_random());
    }

    /**
     * Test get_random_guess_score returns 0.
     */
    public function test_get_random_guess_score(): void {
        $questiondata = new \stdClass();
        $this->assertEquals(0, $this->qtype->get_random_guess_score($questiondata));
    }

    /**
     * Test extra_question_fields returns correct fields.
     */
    public function test_extra_question_fields(): void {
        $fields = $this->qtype->extra_question_fields();

        $this->assertContains('qtype_accounting_options', $fields);
        $this->assertContains('chartofaccountsid', $fields);
        $this->assertContains('allowmultipleentries', $fields);
        $this->assertContains('maxentries', $fields);
    }

    /**
     * Test saving and loading question options.
     */
    public function test_save_and_get_question_options(): void {
        $this->resetAfterTest(true);

        // Create a question.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'Test Buchungssatz',
            'questiontext' => ['text' => 'Test question text', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'accounting',
            'chartofaccountsid' => 123,
            'allowmultipleentries' => 1,
            'maxentries' => 10,
            'debitaccount' => ['1200', '1000'],
            'debitamount' => [1000.00, 500.00],
            'creditaccount' => ['8400', '8400'],
            'creditamount' => [1000.00, 500.00],
            'weight_debitaccount' => [2, 1],
            'weight_debitamount' => [1, 1],
            'weight_creditaccount' => [2, 1],
            'weight_creditamount' => [1, 1],
            'explanation' => ['First entry', 'Second entry'],
        ];

        // Save the question.
        $question = $generator->create_question('accounting', null, $questiondata);

        // Reload the question.
        $loadedquestion = \question_bank::load_question($question->id);

        $this->assertEquals('accounting', $loadedquestion->qtype->name());
        $this->assertEquals(123, $loadedquestion->chartofaccountsid);
        $this->assertEquals(1, $loadedquestion->allowmultipleentries);
        $this->assertEquals(10, $loadedquestion->maxentries);
        $this->assertCount(2, $loadedquestion->entries);

        // Check first entry. Account fields use the debitaccountid / creditaccountid keys
        // (the entries array mirrors the DB column names).
        $this->assertEquals(1200, $loadedquestion->entries[0]['debitaccountid']);
        $this->assertEquals(1000.00, $loadedquestion->entries[0]['debitamount']);
        $this->assertEquals(8400, $loadedquestion->entries[0]['creditaccountid']);
        $this->assertEquals(1000.00, $loadedquestion->entries[0]['creditamount']);
        $this->assertEquals(2, $loadedquestion->entries[0]['weight_debitaccount']);
        $this->assertEquals(1, $loadedquestion->entries[0]['weight_debitamount']);
        $this->assertEquals(2, $loadedquestion->entries[0]['weight_creditaccount']);
        $this->assertEquals(1, $loadedquestion->entries[0]['weight_creditamount']);

        // Check second entry.
        $this->assertEquals(1000, $loadedquestion->entries[1]['debitaccountid']);
        $this->assertEquals(500.00, $loadedquestion->entries[1]['debitamount']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_debitaccount']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_debitamount']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_creditaccount']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_creditamount']);
    }

    /**
     * Test deleting a question removes all related data.
     */
    public function test_delete_question(): void {
        global $DB;

        $this->resetAfterTest(true);

        // Create a question.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'Test Buchungssatz',
            'questiontext' => ['text' => 'Test question text', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'accounting',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 0,
            'maxentries' => 1,
            'debitaccount' => ['1200'],
            'debitamount' => [1000.00],
            'creditaccount' => ['8400'],
            'creditamount' => [1000.00],
            'weight_debitaccount' => [1],
            'weight_debitamount' => [1],
            'weight_creditaccount' => [1],
            'weight_creditamount' => [1],
            'explanation' => [''],
        ];

        $question = $generator->create_question('accounting', null, $questiondata);

        // Verify records exist.
        $this->assertTrue($DB->record_exists('qtype_accounting_options', ['questionid' => $question->id]));
        $this->assertTrue($DB->record_exists('qtype_accounting_entries', ['questionid' => $question->id]));

        // Delete the question.
        question_delete_question($question->id);

        // Verify records are deleted.
        $this->assertFalse($DB->record_exists('qtype_accounting_options', ['questionid' => $question->id]));
        $this->assertFalse($DB->record_exists('qtype_accounting_entries', ['questionid' => $question->id]));
    }

    /**
     * Test saving question with empty credit account skips entry.
     */
    public function test_save_question_skips_empty_entries(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'Test Buchungssatz',
            'questiontext' => ['text' => 'Test question text', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'accounting',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 1,
            'maxentries' => 5,
            'debitaccount' => ['1200', '', '1000'],
            'debitamount' => [1000.00, 0, 500.00],
            'creditaccount' => ['8400', '', '8400'], // Second entry is empty.
            'creditamount' => [1000.00, 0, 500.00],
            'weight_debitaccount' => [1, 1, 1],
            'weight_debitamount' => [1, 1, 1],
            'weight_creditaccount' => [1, 1, 1],
            'weight_creditamount' => [1, 1, 1],
            'explanation' => ['', '', ''],
        ];

        $question = $generator->create_question('accounting', null, $questiondata);

        // Should only have 2 entries (skipping the empty one).
        $entries = $DB->get_records('qtype_accounting_entries', ['questionid' => $question->id]);
        $this->assertCount(2, $entries);
    }

    /**
     * Test get_possible_responses returns empty array.
     */
    public function test_get_possible_responses(): void {
        $questiondata = new \stdClass();
        $responses = $this->qtype->get_possible_responses($questiondata);

        $this->assertIsArray($responses);
        $this->assertEmpty($responses);
    }

    /**
     * Helper to build the XML data array structure for import tests.
     *
     * Constructs the nested array format that Moodle's XML parser produces.
     *
     * @param array $options Options fields (chartofaccountsid, allowmultipleentries, etc).
     * @param array $entries Array of entry arrays with debitaccount, debitamount, etc.
     * @param array|null $chartdata Chart data with 'chartname' and 'accounts' keys.
     * @return array The XML data structure.
     */
    protected function build_xml_import_data(
        array $options = [],
        array $entries = [],
        ?array $chartdata = null
    ): array {
        $data = [
            '@' => ['type' => 'accounting'],
            '#' => [
                'name' => [0 => ['#' => ['text' => [0 => ['#' => 'Imported Question']]]]],
                'questiontext' => [0 => [
                    '@' => ['format' => 'html'],
                    '#' => ['text' => [0 => ['#' => 'Test question text']]],
                ]],
                'generalfeedback' => [0 => [
                    '@' => ['format' => 'html'],
                    '#' => ['text' => [0 => ['#' => '']]],
                ]],
                'defaultmark' => [0 => ['#' => '1']],
                'penalty' => [0 => ['#' => '0.3333333']],
            ],
        ];

        // Add options fields.
        $defaults = [
            'chartofaccountsid' => '0',
            'accountsindropdown' => '0',
            'numberformat' => 'de',
            'extraentrydeduction' => '0.0',
            'allornothinggrading' => '0',
            'allowmultipleentries' => '1',
            'maxentries' => '5',
        ];
        foreach (array_merge($defaults, $options) as $field => $value) {
            $data['#'][$field] = [0 => ['#' => (string) $value]];
        }

        // Add entries.
        if (!empty($entries)) {
            $xmlentries = [];
            foreach ($entries as $i => $entry) {
                $xmlentry = ['#' => []];
                foreach ($entry as $field => $value) {
                    $xmlentry['#'][$field] = [0 => ['#' => (string) $value]];
                }
                $xmlentries[$i] = $xmlentry;
            }
            $data['#']['entries'] = [0 => ['#' => ['entry' => $xmlentries]]];
        }

        // Add chart data.
        if ($chartdata !== null) {
            $chartxml = ['#' => [
                'chartname' => [0 => ['#' => $chartdata['chartname']]],
            ]];
            $xmlaccounts = [];
            foreach ($chartdata['accounts'] as $i => $acc) {
                $xmlaccounts[$i] = ['#' => [
                    'accountname' => [0 => ['#' => $acc['accountname']]],
                    'sortorder' => [0 => ['#' => (string) $acc['sortorder']]],
                ]];
            }
            $chartxml['#']['account'] = $xmlaccounts;
            $data['#']['chartofaccounts'] = [0 => $chartxml];
        }

        return $data;
    }

    /**
     * Test that export_to_xml includes correct answer entries.
     */
    public function test_export_to_xml_includes_entries(): void {
        $this->resetAfterTest(true);

        // Create real accounts so the export can resolve IDs to names.
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Export Entries Chart', $contextid);
        $bankid = account_manager::add($chartid, '1200 Bank', 0);
        $kasseid = account_manager::add($chartid, '1000 Kasse', 1);
        $erloeseid = account_manager::add($chartid, '8400 Erloese', 2);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'XML Export Entries Test',
            'questiontext' => ['text' => 'Test', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'accounting',
            'chartofaccountsid' => $chartid,
            'allowmultipleentries' => 1,
            'maxentries' => 5,
            'debitaccount' => [$bankid, $kasseid],
            'debitamount' => [500.00, 300.00],
            'creditaccount' => [$erloeseid, $erloeseid],
            'creditamount' => [500.00, 300.00],
            'weight_debitaccount' => [2, 1],
            'weight_debitamount' => [1, 1],
            'weight_creditaccount' => [2, 1],
            'weight_creditamount' => [1, 1],
        ];

        $question = $generator->create_question('accounting', null, $questiondata);

        // Load question data with options.
        $questionobj = new \stdClass();
        $questionobj->id = $question->id;
        $questionobj->qtype = 'accounting';
        $this->qtype->get_question_options($questionobj);

        $format = new \qformat_xml();
        $xml = $this->qtype->export_to_xml($questionobj, $format);

        // Verify entries block is present.
        $this->assertStringContainsString('<entries>', $xml);
        $this->assertStringContainsString('<entry>', $xml);

        // Verify first entry data. Export resolves account IDs to names.
        $this->assertStringContainsString('<debitaccount>1200 Bank</debitaccount>', $xml);
        $this->assertStringContainsString('<creditaccount>8400 Erloese</creditaccount>', $xml);
        $this->assertStringContainsString('<weight_debitaccount>2</weight_debitaccount>', $xml);

        // Verify second entry data.
        $this->assertStringContainsString('<debitaccount>1000 Kasse</debitaccount>', $xml);
    }

    /**
     * Test that export_to_xml includes chart of accounts data.
     */
    public function test_export_to_xml_includes_chart(): void {
        $this->resetAfterTest(true);

        // Create a chart with accounts.
        $contextid = \context_system::instance()->id;
        $chartid = chart_manager::create_chart('Test Export Chart', $contextid);
        account_manager::add($chartid, '1200 Bank', 0);
        account_manager::add($chartid, '8400 Erlöse', 1);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'XML Export Chart Test',
            'questiontext' => ['text' => 'Test', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'accounting',
            'chartofaccountsid' => $chartid,
            'allowmultipleentries' => 0,
            'maxentries' => 1,
            'debitaccount' => ['1200 Bank'],
            'debitamount' => [1000.00],
            'creditaccount' => ['8400 Erlöse'],
            'creditamount' => [1000.00],
            'weight_debitaccount' => [1],
            'weight_debitamount' => [1],
            'weight_creditaccount' => [1],
            'weight_creditamount' => [1],
        ];

        $question = $generator->create_question('accounting', null, $questiondata);

        $questionobj = new \stdClass();
        $questionobj->id = $question->id;
        $questionobj->qtype = 'accounting';
        $this->qtype->get_question_options($questionobj);

        $format = new \qformat_xml();
        $xml = $this->qtype->export_to_xml($questionobj, $format);

        // Verify chart block is present.
        $this->assertStringContainsString('<chartofaccounts>', $xml);
        $this->assertStringContainsString('<chartname>Test Export Chart</chartname>', $xml);
        $this->assertStringContainsString('<accountname>1200 Bank</accountname>', $xml);
        $this->assertStringContainsString('<accountname>8400 Erlöse</accountname>', $xml);
    }

    /**
     * Test that export_to_xml omits chart block when chartofaccountsid is 0.
     */
    public function test_export_to_xml_no_chart(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $questiondata = [
            'category' => $category->id,
            'name' => 'XML Export No Chart Test',
            'questiontext' => ['text' => 'Test', 'format' => FORMAT_HTML],
            'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
            'defaultmark' => 1,
            'penalty' => 0.3333333,
            'qtype' => 'accounting',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 0,
            'maxentries' => 1,
            'debitaccount' => ['1200'],
            'debitamount' => [1000.00],
            'creditaccount' => ['8400'],
            'creditamount' => [1000.00],
            'weight_debitaccount' => [1],
            'weight_debitamount' => [1],
            'weight_creditaccount' => [1],
            'weight_creditamount' => [1],
        ];

        $question = $generator->create_question('accounting', null, $questiondata);

        $questionobj = new \stdClass();
        $questionobj->id = $question->id;
        $questionobj->qtype = 'accounting';
        $this->qtype->get_question_options($questionobj);

        $format = new \qformat_xml();
        $xml = $this->qtype->export_to_xml($questionobj, $format);

        $this->assertStringNotContainsString('<chartofaccounts>', $xml);
    }

    /**
     * Test importing entries from XML data.
     */
    public function test_import_from_xml_with_entries(): void {
        $this->resetAfterTest(true);

        $data = $this->build_xml_import_data(
            ['allowmultipleentries' => '1', 'maxentries' => '5'],
            [
                [
                    'sortorder' => '0',
                    'debitaccount' => '1200',
                    'debitamount' => '500',
                    'creditaccount' => '8400',
                    'creditamount' => '500',
                    'weight_debitaccount' => '2',
                    'weight_debitamount' => '1',
                    'weight_creditaccount' => '2',
                    'weight_creditamount' => '1',
                    'explanation' => '',
                ],
                [
                    'sortorder' => '1',
                    'debitaccount' => '1000',
                    'debitamount' => '300',
                    'creditaccount' => '8400',
                    'creditamount' => '300',
                    'weight_debitaccount' => '1',
                    'weight_debitamount' => '1',
                    'weight_creditaccount' => '1',
                    'weight_creditamount' => '1',
                    'explanation' => '',
                ],
            ]
        );

        $format = new \qformat_xml();
        $defaultquestion = new \stdClass();

        $qo = $this->qtype->import_from_xml($data, $defaultquestion, $format);

        $this->assertNotFalse($qo);
        $this->assertEquals('accounting', $qo->qtype);

        // Verify entries were parsed.
        $this->assertCount(2, $qo->debitaccount);
        $this->assertEquals('1200', $qo->debitaccount[0]);
        $this->assertEquals('500', $qo->debitamount[0]);
        $this->assertEquals('8400', $qo->creditaccount[0]);
        $this->assertEquals('500', $qo->creditamount[0]);
        $this->assertEquals(2, $qo->weight_debitaccount[0]);
        $this->assertEquals(1, $qo->weight_debitamount[0]);

        // Verify second entry.
        $this->assertEquals('1000', $qo->debitaccount[1]);
        $this->assertEquals('300', $qo->debitamount[1]);
    }

    /**
     * Test importing chart of accounts from XML creates a new chart.
     */
    public function test_import_from_xml_creates_chart(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        $chartaccounts = [
            ['accountname' => '1200 Bank', 'sortorder' => 0],
            ['accountname' => '8400 Erlöse', 'sortorder' => 1],
        ];

        $data = $this->build_xml_import_data(
            [],
            [
                [
                    'sortorder' => '0',
                    'debitaccount' => '1200 Bank',
                    'debitamount' => '1000',
                    'creditaccount' => '8400 Erlöse',
                    'creditamount' => '1000',
                    'weight_debitaccount' => '1',
                    'weight_debitamount' => '1',
                    'weight_creditaccount' => '1',
                    'weight_creditamount' => '1',
                    'explanation' => '',
                ],
            ],
            ['chartname' => 'Import Test Chart', 'accounts' => $chartaccounts]
        );

        $format = new \qformat_xml();
        $format->course = $course;
        $defaultquestion = new \stdClass();

        $qo = $this->qtype->import_from_xml($data, $defaultquestion, $format);

        $this->assertNotFalse($qo);
        $this->assertGreaterThan(0, $qo->chartofaccountsid);

        // Verify chart was created.
        $chart = chart_manager::get_chart($qo->chartofaccountsid);
        $this->assertNotFalse($chart);
        $this->assertEquals('Import Test Chart', $chart->name);

        // Verify accounts were created.
        $accounts = account_manager::get_for_chart($qo->chartofaccountsid);
        $this->assertCount(2, $accounts);
    }

    /**
     * Test importing reuses an existing matching chart.
     */
    public function test_import_from_xml_reuses_existing_chart(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        // Pre-create a chart in the course context.
        $existingchartid = chart_manager::create_chart('Reuse Chart', $coursecontext->id);
        account_manager::add($existingchartid, '1200 Bank', 0);
        account_manager::add($existingchartid, '8400 Erlöse', 1);

        $chartaccounts = [
            ['accountname' => '1200 Bank', 'sortorder' => 0],
            ['accountname' => '8400 Erlöse', 'sortorder' => 1],
        ];

        $data = $this->build_xml_import_data(
            [],
            [
                [
                    'sortorder' => '0',
                    'debitaccount' => '1200',
                    'debitamount' => '1000',
                    'creditaccount' => '8400',
                    'creditamount' => '1000',
                    'weight_debitaccount' => '1',
                    'weight_debitamount' => '1',
                    'weight_creditaccount' => '1',
                    'weight_creditamount' => '1',
                    'explanation' => '',
                ],
            ],
            ['chartname' => 'Reuse Chart', 'accounts' => $chartaccounts]
        );

        $format = new \qformat_xml();
        $format->course = $course;
        $defaultquestion = new \stdClass();

        $qo = $this->qtype->import_from_xml($data, $defaultquestion, $format);

        $this->assertNotFalse($qo);
        // Should reuse the existing chart, not create a new one.
        $this->assertEquals($existingchartid, $qo->chartofaccountsid);
    }

    /**
     * Test import_from_xml returns false for non-accounting questions.
     */
    public function test_import_from_xml_wrong_type(): void {
        $data = [
            '@' => ['type' => 'multichoice'],
            '#' => [],
        ];

        $format = new \qformat_xml();
        $defaultquestion = new \stdClass();

        $result = $this->qtype->import_from_xml($data, $defaultquestion, $format);
        $this->assertFalse($result);
    }

    /**
     * Test import_from_xml with no entries produces empty arrays.
     */
    public function test_import_from_xml_no_entries(): void {
        $this->resetAfterTest(true);

        $data = $this->build_xml_import_data();

        $format = new \qformat_xml();
        $defaultquestion = new \stdClass();

        $qo = $this->qtype->import_from_xml($data, $defaultquestion, $format);

        $this->assertNotFalse($qo);
        $this->assertEmpty($qo->debitaccount);
        $this->assertEmpty($qo->creditaccount);
        $this->assertEmpty($qo->debitamount);
        $this->assertEmpty($qo->creditamount);
    }
}
