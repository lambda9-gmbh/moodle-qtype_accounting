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

namespace qtype_buchungssatz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/buchungssatz/questiontype.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Unit tests for qtype_buchungssatz (question type class).
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qtype_buchungssatz
 */
class questiontype_test extends \advanced_testcase {
    /** @var \qtype_buchungssatz The question type instance. */
    protected $qtype;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->qtype = new \qtype_buchungssatz();
    }

    /**
     * Test the name method.
     */
    public function test_name(): void {
        $this->assertEquals('buchungssatz', $this->qtype->name());
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

        $this->assertContains('qtype_buchungssatz_options', $fields);
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
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => 123,
            'allowmultipleentries' => 1,
            'maxentries' => 10,
            'sollkonto' => ['1200', '1000'],
            'sollbetrag' => [1000.00, 500.00],
            'habenkonto' => ['8400', '8400'],
            'habenbetrag' => [1000.00, 500.00],
            'weight_sollkonto' => [2, 1],
            'weight_sollbetrag' => [1, 1],
            'weight_habenkonto' => [2, 1],
            'weight_habenbetrag' => [1, 1],
            'explanation' => ['First entry', 'Second entry'],
        ];

        // Save the question.
        $question = $generator->create_question('buchungssatz', null, $questiondata);

        // Reload the question.
        $loadedquestion = \question_bank::load_question($question->id);

        $this->assertEquals('buchungssatz', $loadedquestion->qtype->name());
        $this->assertEquals(123, $loadedquestion->chartofaccountsid);
        $this->assertEquals(1, $loadedquestion->allowmultipleentries);
        $this->assertEquals(10, $loadedquestion->maxentries);
        $this->assertCount(2, $loadedquestion->entries);

        // Check first entry. Account fields use the sollkontoid / habenkontoid keys
        // (the entries array mirrors the DB column names).
        $this->assertEquals(1200, $loadedquestion->entries[0]['sollkontoid']);
        $this->assertEquals(1000.00, $loadedquestion->entries[0]['sollbetrag']);
        $this->assertEquals(8400, $loadedquestion->entries[0]['habenkontoid']);
        $this->assertEquals(1000.00, $loadedquestion->entries[0]['habenbetrag']);
        $this->assertEquals(2, $loadedquestion->entries[0]['weight_sollkonto']);
        $this->assertEquals(1, $loadedquestion->entries[0]['weight_sollbetrag']);
        $this->assertEquals(2, $loadedquestion->entries[0]['weight_habenkonto']);
        $this->assertEquals(1, $loadedquestion->entries[0]['weight_habenbetrag']);

        // Check second entry.
        $this->assertEquals(1000, $loadedquestion->entries[1]['sollkontoid']);
        $this->assertEquals(500.00, $loadedquestion->entries[1]['sollbetrag']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_sollkonto']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_sollbetrag']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_habenkonto']);
        $this->assertEquals(1, $loadedquestion->entries[1]['weight_habenbetrag']);
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
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 0,
            'maxentries' => 1,
            'sollkonto' => ['1200'],
            'sollbetrag' => [1000.00],
            'habenkonto' => ['8400'],
            'habenbetrag' => [1000.00],
            'weight_sollkonto' => [1],
            'weight_sollbetrag' => [1],
            'weight_habenkonto' => [1],
            'weight_habenbetrag' => [1],
            'explanation' => [''],
        ];

        $question = $generator->create_question('buchungssatz', null, $questiondata);

        // Verify records exist.
        $this->assertTrue($DB->record_exists('qtype_buchungssatz_options', ['questionid' => $question->id]));
        $this->assertTrue($DB->record_exists('qtype_buchungssatz_entries', ['questionid' => $question->id]));

        // Delete the question.
        question_delete_question($question->id);

        // Verify records are deleted.
        $this->assertFalse($DB->record_exists('qtype_buchungssatz_options', ['questionid' => $question->id]));
        $this->assertFalse($DB->record_exists('qtype_buchungssatz_entries', ['questionid' => $question->id]));
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
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 1,
            'maxentries' => 5,
            'sollkonto' => ['1200', '', '1000'],
            'sollbetrag' => [1000.00, 0, 500.00],
            'habenkonto' => ['8400', '', '8400'], // Second entry is empty.
            'habenbetrag' => [1000.00, 0, 500.00],
            'weight_sollkonto' => [1, 1, 1],
            'weight_sollbetrag' => [1, 1, 1],
            'weight_habenkonto' => [1, 1, 1],
            'weight_habenbetrag' => [1, 1, 1],
            'explanation' => ['', '', ''],
        ];

        $question = $generator->create_question('buchungssatz', null, $questiondata);

        // Should only have 2 entries (skipping the empty one).
        $entries = $DB->get_records('qtype_buchungssatz_entries', ['questionid' => $question->id]);
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
     * @param array $entries Array of entry arrays with sollkonto, sollbetrag, etc.
     * @param array|null $chartdata Chart data with 'chartname' and 'accounts' keys.
     * @return array The XML data structure.
     */
    protected function build_xml_import_data(
        array $options = [],
        array $entries = [],
        ?array $chartdata = null
    ): array {
        $data = [
            '@' => ['type' => 'buchungssatz'],
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
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => $chartid,
            'allowmultipleentries' => 1,
            'maxentries' => 5,
            'sollkonto' => [$bankid, $kasseid],
            'sollbetrag' => [500.00, 300.00],
            'habenkonto' => [$erloeseid, $erloeseid],
            'habenbetrag' => [500.00, 300.00],
            'weight_sollkonto' => [2, 1],
            'weight_sollbetrag' => [1, 1],
            'weight_habenkonto' => [2, 1],
            'weight_habenbetrag' => [1, 1],
        ];

        $question = $generator->create_question('buchungssatz', null, $questiondata);

        // Load question data with options.
        $questionobj = new \stdClass();
        $questionobj->id = $question->id;
        $questionobj->qtype = 'buchungssatz';
        $this->qtype->get_question_options($questionobj);

        $format = new \qformat_xml();
        $xml = $this->qtype->export_to_xml($questionobj, $format);

        // Verify entries block is present.
        $this->assertStringContainsString('<entries>', $xml);
        $this->assertStringContainsString('<entry>', $xml);

        // Verify first entry data. Export resolves account IDs to names.
        $this->assertStringContainsString('<sollkonto>1200 Bank</sollkonto>', $xml);
        $this->assertStringContainsString('<habenkonto>8400 Erloese</habenkonto>', $xml);
        $this->assertStringContainsString('<weight_sollkonto>2</weight_sollkonto>', $xml);

        // Verify second entry data.
        $this->assertStringContainsString('<sollkonto>1000 Kasse</sollkonto>', $xml);
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
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => $chartid,
            'allowmultipleentries' => 0,
            'maxentries' => 1,
            'sollkonto' => ['1200 Bank'],
            'sollbetrag' => [1000.00],
            'habenkonto' => ['8400 Erlöse'],
            'habenbetrag' => [1000.00],
            'weight_sollkonto' => [1],
            'weight_sollbetrag' => [1],
            'weight_habenkonto' => [1],
            'weight_habenbetrag' => [1],
        ];

        $question = $generator->create_question('buchungssatz', null, $questiondata);

        $questionobj = new \stdClass();
        $questionobj->id = $question->id;
        $questionobj->qtype = 'buchungssatz';
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
            'qtype' => 'buchungssatz',
            'chartofaccountsid' => 0,
            'allowmultipleentries' => 0,
            'maxentries' => 1,
            'sollkonto' => ['1200'],
            'sollbetrag' => [1000.00],
            'habenkonto' => ['8400'],
            'habenbetrag' => [1000.00],
            'weight_sollkonto' => [1],
            'weight_sollbetrag' => [1],
            'weight_habenkonto' => [1],
            'weight_habenbetrag' => [1],
        ];

        $question = $generator->create_question('buchungssatz', null, $questiondata);

        $questionobj = new \stdClass();
        $questionobj->id = $question->id;
        $questionobj->qtype = 'buchungssatz';
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
                    'sollkonto' => '1200',
                    'sollbetrag' => '500',
                    'habenkonto' => '8400',
                    'habenbetrag' => '500',
                    'weight_sollkonto' => '2',
                    'weight_sollbetrag' => '1',
                    'weight_habenkonto' => '2',
                    'weight_habenbetrag' => '1',
                    'explanation' => '',
                ],
                [
                    'sortorder' => '1',
                    'sollkonto' => '1000',
                    'sollbetrag' => '300',
                    'habenkonto' => '8400',
                    'habenbetrag' => '300',
                    'weight_sollkonto' => '1',
                    'weight_sollbetrag' => '1',
                    'weight_habenkonto' => '1',
                    'weight_habenbetrag' => '1',
                    'explanation' => '',
                ],
            ]
        );

        $format = new \qformat_xml();
        $defaultquestion = new \stdClass();

        $qo = $this->qtype->import_from_xml($data, $defaultquestion, $format);

        $this->assertNotFalse($qo);
        $this->assertEquals('buchungssatz', $qo->qtype);

        // Verify entries were parsed.
        $this->assertCount(2, $qo->sollkonto);
        $this->assertEquals('1200', $qo->sollkonto[0]);
        $this->assertEquals('500', $qo->sollbetrag[0]);
        $this->assertEquals('8400', $qo->habenkonto[0]);
        $this->assertEquals('500', $qo->habenbetrag[0]);
        $this->assertEquals(2, $qo->weight_sollkonto[0]);
        $this->assertEquals(1, $qo->weight_sollbetrag[0]);

        // Verify second entry.
        $this->assertEquals('1000', $qo->sollkonto[1]);
        $this->assertEquals('300', $qo->sollbetrag[1]);
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
                    'sollkonto' => '1200 Bank',
                    'sollbetrag' => '1000',
                    'habenkonto' => '8400 Erlöse',
                    'habenbetrag' => '1000',
                    'weight_sollkonto' => '1',
                    'weight_sollbetrag' => '1',
                    'weight_habenkonto' => '1',
                    'weight_habenbetrag' => '1',
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
                    'sollkonto' => '1200',
                    'sollbetrag' => '1000',
                    'habenkonto' => '8400',
                    'habenbetrag' => '1000',
                    'weight_sollkonto' => '1',
                    'weight_sollbetrag' => '1',
                    'weight_habenkonto' => '1',
                    'weight_habenbetrag' => '1',
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
     * Test import_from_xml returns false for non-buchungssatz questions.
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
        $this->assertEmpty($qo->sollkonto);
        $this->assertEmpty($qo->habenkonto);
        $this->assertEmpty($qo->sollbetrag);
        $this->assertEmpty($qo->habenbetrag);
    }
}
