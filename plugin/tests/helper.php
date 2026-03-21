<?php
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
 * Test helper for qtype_buchungssatz.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/buchungssatz/question.php');

/**
 * Test helper class for creating Buchungssatz questions.
 *
 * Account ID constants used throughout tests:
 *   101 = '1200 Bank'
 *   102 = '1000 Kasse'
 *   201 = '8400 Erloese 19% USt'
 *   301 = '4400 Verbindlichkeiten'
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_buchungssatz_test_helper extends question_test_helper {

    /** @var int Account ID for 1200 Bank. */
    const ACCOUNT_BANK = 101;

    /** @var int Account ID for 1000 Kasse. */
    const ACCOUNT_KASSE = 102;

    /** @var int Account ID for 8400 Erloese 19% USt. */
    const ACCOUNT_ERLOESE = 201;

    /** @var int Account ID for 4400 Verbindlichkeiten. */
    const ACCOUNT_VERBINDLICHKEITEN = 301;

    /**
     * Get the standard accounts map used in tests.
     *
     * Maps account IDs to their display names.
     *
     * @return array The accounts map (ID => name).
     */
    public static function get_test_accounts_map(): array {
        return [
            self::ACCOUNT_BANK => '1200 Bank',
            self::ACCOUNT_KASSE => '1000 Kasse',
            self::ACCOUNT_ERLOESE => '8400 Erloese 19% USt',
            self::ACCOUNT_VERBINDLICHKEITEN => '4400 Verbindlichkeiten',
        ];
    }

    /**
     * Get the question types that this helper supports.
     *
     * @return array The supported question types.
     */
    public function get_test_questions(): array {
        return [
            'simple_debit_credit',
            'multiple_entries',
            'debit_only_optional',
            'weighted_entries',
            'all_or_nothing',
            'split_amounts',
            'multiple_same_account',
            'extra_entry_deduction',
        ];
    }

    /**
     * Create a simple question with one debit/credit entry.
     *
     * Example: Bank 1000 / Revenue 1000
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_simple_debit_credit(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 1;
        $question->name = 'Simple debit/credit question';
        $question->questiontext = 'A customer pays 1000 EUR in cash. Record this transaction.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'The correct entry is: Bank 1000 / Revenue 1000';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;

        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 1000.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 1000.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Create a question with multiple entries.
     *
     * Example: Split payment - part cash, part bank transfer.
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_multiple_entries(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 2;
        $question->name = 'Multiple entries question';
        $question->questiontext = 'Record the following: Cash sale 500 EUR and bank sale 500 EUR.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Two entries are needed.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;

        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'sollkontoid' => self::ACCOUNT_KASSE,
                'sollbetrag' => 500.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 500.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => 'Cash payment',
            ],
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 500.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 500.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => 'Bank transfer',
            ],
        ];

        return $question;
    }

    /**
     * Create a question where debit account is optional (credit-only entry).
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_debit_only_optional(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 3;
        $question->name = 'Debit optional question';
        $question->questiontext = 'Record a credit entry to liability account.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Only credit account is required.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;

        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'sollkontoid' => null,
                'sollbetrag' => 0,
                'habenkontoid' => self::ACCOUNT_VERBINDLICHKEITEN,
                'habenbetrag' => 250.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Create a question with custom weights for weighted scoring tests.
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_weighted_entries(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 4;
        $question->name = 'Weighted entries question';
        $question->questiontext = 'Record the transaction with weighted scoring.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Weighted scoring applies.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;
        $question->allornothinggrading = 0;

        $question->accountsmap = self::get_test_accounts_map();

        // Custom weights: account has weight 2, amount has weight 1.
        $question->entries = [
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 1000.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 1000.00,
                'weight_sollkonto' => 2,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 2,
                'weight_habenbetrag' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Create a question with all-or-nothing grading enabled.
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_all_or_nothing(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 5;
        $question->name = 'All or nothing question';
        $question->questiontext = 'Record the transaction (all or nothing).';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Must be completely correct.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;
        $question->allornothinggrading = 1;

        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 1000.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 1000.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Create a question that tests split amounts (same account, amount split across entries).
     *
     * Correct answer: Bank 600 total (can be split as 300+300 or 200+400, etc.)
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_split_amounts(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 6;
        $question->name = 'Split amounts question';
        $question->questiontext = 'Record the transaction (amounts can be split).';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Bank 600 / Revenue 600';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;
        $question->allornothinggrading = 0;

        $question->accountsmap = self::get_test_accounts_map();

        // Single entry with total amounts.
        $question->entries = [
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 600.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 600.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Create a question with multiple entries to the same account.
     *
     * Tests aggregation: two entries to Bank should be aggregated.
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_multiple_same_account(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 7;
        $question->name = 'Multiple same account question';
        $question->questiontext = 'Record two payments to bank.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Bank 300 + Bank 200 = Bank 500 total';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;
        $question->allornothinggrading = 0;

        $question->accountsmap = self::get_test_accounts_map();

        // Two entries to the same account.
        $question->entries = [
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 300.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 300.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => 'First payment',
            ],
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 200.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 200.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => 'Second payment',
            ],
        ];

        return $question;
    }

    /**
     * Create a question with extra entry deduction enabled.
     *
     * Simple debit/credit entry with extraentrydeduction = 0.05 (5% per extra account).
     *
     * @return qtype_buchungssatz_question The test question.
     */
    public function make_buchungssatz_question_extra_entry_deduction(): qtype_buchungssatz_question {
        $question = new qtype_buchungssatz_question();

        $question->id = 8;
        $question->name = 'Extra entry deduction question';
        $question->questiontext = 'Record the transaction. Extra accounts will be penalised.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Bank 1000 / Revenue 1000';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('buchungssatz');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;
        $question->allornothinggrading = 0;
        $question->extraentrydeduction = 0.05;

        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'sollkontoid' => self::ACCOUNT_BANK,
                'sollbetrag' => 1000.00,
                'habenkontoid' => self::ACCOUNT_ERLOESE,
                'habenbetrag' => 1000.00,
                'weight_sollkonto' => 1,
                'weight_sollbetrag' => 1,
                'weight_habenkonto' => 1,
                'weight_habenbetrag' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Get default form data for a Buchungssatz question.
     *
     * This method is called by Moodle's question generator when creating
     * test questions via create_question() without specifying a variant.
     *
     * @return \stdClass The form data.
     */
    public function get_buchungssatz_question_form_data(): \stdClass {
        $fromform = new \stdClass();

        $fromform->name = 'Default Buchungssatz question';
        $fromform->questiontext = ['text' => 'Record the accounting entry.', 'format' => FORMAT_HTML];
        $fromform->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;

        $fromform->chartofaccountsid = 0;
        $fromform->allowmultipleentries = 0;
        $fromform->maxentries = 5;

        // Default entry with minimal data that will be overwritten.
        $fromform->sollkonto = [self::ACCOUNT_BANK];
        $fromform->sollbetrag = [1000.00];
        $fromform->habenkonto = [self::ACCOUNT_ERLOESE];
        $fromform->habenbetrag = [1000.00];
        $fromform->weight_sollkonto = [1];
        $fromform->weight_sollbetrag = [1];
        $fromform->weight_habenkonto = [1];
        $fromform->weight_habenbetrag = [1];

        return $fromform;
    }

    /**
     * Get form data for a simple debit/credit question.
     *
     * This method is called by Moodle's question generator when creating
     * test questions via create_question().
     *
     * @return \stdClass The form data.
     */
    public function get_buchungssatz_question_form_data_simple_debit_credit(): \stdClass {
        $fromform = new \stdClass();

        $fromform->name = 'Simple debit/credit question';
        $fromform->questiontext = ['text' => 'A customer pays 1000 EUR in cash. Record this transaction.', 'format' => FORMAT_HTML];
        $fromform->generalfeedback = ['text' => 'The correct entry is: Bank 1000 / Revenue 1000', 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;

        $fromform->chartofaccountsid = 0;
        $fromform->allowmultipleentries = 0;
        $fromform->maxentries = 1;

        $fromform->sollkonto = [self::ACCOUNT_BANK];
        $fromform->sollbetrag = [1000.00];
        $fromform->habenkonto = [self::ACCOUNT_ERLOESE];
        $fromform->habenbetrag = [1000.00];
        $fromform->weight_sollkonto = [1];
        $fromform->weight_sollbetrag = [1];
        $fromform->weight_habenkonto = [1];
        $fromform->weight_habenbetrag = [1];

        return $fromform;
    }

    /**
     * Get form data for a multiple entries question.
     *
     * @return \stdClass The form data.
     */
    public function get_buchungssatz_question_form_data_multiple_entries(): \stdClass {
        $fromform = new \stdClass();

        $fromform->name = 'Multiple entries question';
        $fromform->questiontext = ['text' => 'Record the following: Cash sale 500 EUR and bank sale 500 EUR.', 'format' => FORMAT_HTML];
        $fromform->generalfeedback = ['text' => 'Two entries are needed.', 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;

        $fromform->chartofaccountsid = 0;
        $fromform->allowmultipleentries = 1;
        $fromform->maxentries = 5;

        $fromform->sollkonto = [self::ACCOUNT_KASSE, self::ACCOUNT_BANK];
        $fromform->sollbetrag = [500.00, 500.00];
        $fromform->habenkonto = [self::ACCOUNT_ERLOESE, self::ACCOUNT_ERLOESE];
        $fromform->habenbetrag = [500.00, 500.00];
        $fromform->weight_sollkonto = [1, 1];
        $fromform->weight_sollbetrag = [1, 1];
        $fromform->weight_habenkonto = [1, 1];
        $fromform->weight_habenbetrag = [1, 1];

        return $fromform;
    }

    /**
     * Get form data for a debit-only optional question.
     *
     * @return \stdClass The form data.
     */
    public function get_buchungssatz_question_form_data_debit_only_optional(): \stdClass {
        $fromform = new \stdClass();

        $fromform->name = 'Debit optional question';
        $fromform->questiontext = ['text' => 'Record a credit entry to liability account.', 'format' => FORMAT_HTML];
        $fromform->generalfeedback = ['text' => 'Only credit account is required.', 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;

        $fromform->chartofaccountsid = 0;
        $fromform->allowmultipleentries = 0;
        $fromform->maxentries = 1;

        $fromform->sollkonto = [0];
        $fromform->sollbetrag = [0];
        $fromform->habenkonto = [self::ACCOUNT_VERBINDLICHKEITEN];
        $fromform->habenbetrag = [250.00];
        $fromform->weight_sollkonto = [1];
        $fromform->weight_sollbetrag = [1];
        $fromform->weight_habenkonto = [1];
        $fromform->weight_habenbetrag = [1];

        return $fromform;
    }

    /**
     * Create a response array for a simple entry.
     *
     * Note: The response field names remain as sollkonto_N / habenkonto_N
     * (Moodle question engine field names), but the values are integer account IDs.
     *
     * @param int $sollkontoid Debit account ID (0 for no debit account).
     * @param float $sollbetrag Debit amount.
     * @param int $habenkontoid Credit account ID.
     * @param float $habenbetrag Credit amount.
     * @param int $index Entry index (default 0).
     * @return array The response array.
     */
    public static function make_response(
        int $sollkontoid,
        float $sollbetrag,
        int $habenkontoid,
        float $habenbetrag,
        int $index = 0
    ): array {
        return [
            "sollkonto_{$index}" => $sollkontoid,
            "sollbetrag_{$index}" => $sollbetrag,
            "habenkonto_{$index}" => $habenkontoid,
            "habenbetrag_{$index}" => $habenbetrag,
        ];
    }

    /**
     * Create a response array with multiple entries.
     *
     * Each entry should use 'sollkontoid' and 'habenkontoid' keys with integer values.
     *
     * @param array $entries Array of entries, each with sollkontoid, sollbetrag, habenkontoid, habenbetrag.
     * @return array The response array.
     */
    public static function make_multi_response(array $entries): array {
        $response = [];
        foreach ($entries as $index => $entry) {
            $response["sollkonto_{$index}"] = $entry['sollkontoid'] ?? 0;
            $response["sollbetrag_{$index}"] = $entry['sollbetrag'] ?? 0;
            $response["habenkonto_{$index}"] = $entry['habenkontoid'] ?? 0;
            $response["habenbetrag_{$index}"] = $entry['habenbetrag'] ?? 0;
        }
        return $response;
    }
}
