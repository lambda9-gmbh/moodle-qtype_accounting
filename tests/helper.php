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
 * Test helper for qtype_accounting.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/accounting/question.php');

/**
 * Test helper class for creating Buchungssatz questions.
 *
 * Account ID constants used throughout tests:
 *   101 = '1200 Bank'
 *   102 = '1000 Kasse'
 *   201 = '8400 Erloese 19% USt'
 *   301 = '4400 Verbindlichkeiten'
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_accounting_test_helper extends question_test_helper {
    /** @var int Account ID for 1200 Bank. */
    public const ACCOUNT_BANK = 101;

    /** @var int Account ID for 1000 Kasse. */
    public const ACCOUNT_KASSE = 102;

    /** @var int Account ID for 8400 Erloese 19% USt. */
    public const ACCOUNT_ERLOESE = 201;

    /** @var int Account ID for 4400 Verbindlichkeiten. */
    public const ACCOUNT_VERBINDLICHKEITEN = 301;

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
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_simple_debit_credit(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 1;
        $question->name = 'Simple debit/credit question';
        $question->questiontext = 'A customer pays 1000 EUR in cash. Record this transaction.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'The correct entry is: Bank 1000 / Revenue 1000';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;
        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 1000.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 1000.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
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
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_multiple_entries(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 2;
        $question->name = 'Multiple entries question';
        $question->questiontext = 'Record the following: Cash sale 500 EUR and bank sale 500 EUR.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Two entries are needed.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;

        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'debitaccountid' => self::ACCOUNT_KASSE,
                'debitamount' => 500.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 500.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
                'explanation' => 'Cash payment',
            ],
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 500.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 500.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
                'explanation' => 'Bank transfer',
            ],
        ];

        return $question;
    }

    /**
     * Create a question where debit account is optional (credit-only entry).
     *
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_debit_only_optional(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 3;
        $question->name = 'Debit optional question';
        $question->questiontext = 'Record a credit entry to liability account.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Only credit account is required.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;
        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'debitaccountid' => null,
                'debitamount' => 0,
                'creditaccountid' => self::ACCOUNT_VERBINDLICHKEITEN,
                'creditamount' => 250.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Create a question with custom weights for weighted scoring tests.
     *
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_weighted_entries(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 4;
        $question->name = 'Weighted entries question';
        $question->questiontext = 'Record the transaction with weighted scoring.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Weighted scoring applies.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;
        $question->allornothinggrading = 0;

        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        // Custom weights: account has weight 2, amount has weight 1.
        $question->entries = [
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 1000.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 1000.00,
                'weight_debitaccount' => 2,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 2,
                'weight_creditamount' => 1,
                'explanation' => '',
            ],
        ];

        return $question;
    }

    /**
     * Create a question with all-or-nothing grading enabled.
     *
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_all_or_nothing(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 5;
        $question->name = 'All or nothing question';
        $question->questiontext = 'Record the transaction (all or nothing).';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Must be completely correct.';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 0;
        $question->maxentries = 1;
        $question->allornothinggrading = 1;

        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 1000.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 1000.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
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
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_split_amounts(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 6;
        $question->name = 'Split amounts question';
        $question->questiontext = 'Record the transaction (amounts can be split).';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Bank 600 / Revenue 600';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;
        $question->allornothinggrading = 0;

        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        // Single entry with total amounts.
        $question->entries = [
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 600.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 600.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
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
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_multiple_same_account(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 7;
        $question->name = 'Multiple same account question';
        $question->questiontext = 'Record two payments to bank.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Bank 300 + Bank 200 = Bank 500 total';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;
        $question->allornothinggrading = 0;

        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        // Two entries to the same account.
        $question->entries = [
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 300.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 300.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
                'explanation' => 'First payment',
            ],
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 200.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 200.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
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
     * @return qtype_accounting_question The test question.
     */
    public function make_accounting_question_extra_entry_deduction(): qtype_accounting_question {
        $question = new qtype_accounting_question();

        $question->id = 8;
        $question->name = 'Extra entry deduction question';
        $question->questiontext = 'Record the transaction. Extra accounts will be penalised.';
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = 'Bank 1000 / Revenue 1000';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = 1;
        $question->penalty = 0.3333333;
        $question->qtype = question_bank::get_qtype('accounting');

        $question->chartofaccountsid = 0;
        $question->allowmultipleentries = 1;
        $question->maxentries = 5;
        $question->allornothinggrading = 0;
        $question->extraentrydeduction = 0.05;

        $question->numberformat = 'de';
        $question->accountsmap = self::get_test_accounts_map();

        $question->entries = [
            [
                'debitaccountid' => self::ACCOUNT_BANK,
                'debitamount' => 1000.00,
                'creditaccountid' => self::ACCOUNT_ERLOESE,
                'creditamount' => 1000.00,
                'weight_debitaccount' => 1,
                'weight_debitamount' => 1,
                'weight_creditaccount' => 1,
                'weight_creditamount' => 1,
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
    public function get_accounting_question_form_data(): \stdClass {
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
        $fromform->debitaccount = [self::ACCOUNT_BANK];
        $fromform->debitamount = [1000.00];
        $fromform->creditaccount = [self::ACCOUNT_ERLOESE];
        $fromform->creditamount = [1000.00];
        $fromform->weight_debitaccount = [1];
        $fromform->weight_debitamount = [1];
        $fromform->weight_creditaccount = [1];
        $fromform->weight_creditamount = [1];

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
    public function get_accounting_question_form_data_simple_debit_credit(): \stdClass {
        $fromform = new \stdClass();

        $fromform->name = 'Simple debit/credit question';
        $fromform->questiontext = ['text' => 'A customer pays 1000 EUR in cash. Record this transaction.', 'format' => FORMAT_HTML];
        $fromform->generalfeedback = ['text' => 'The correct entry is: Bank 1000 / Revenue 1000', 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;

        $fromform->chartofaccountsid = 0;
        $fromform->allowmultipleentries = 0;
        $fromform->maxentries = 1;

        $fromform->debitaccount = [self::ACCOUNT_BANK];
        $fromform->debitamount = [1000.00];
        $fromform->creditaccount = [self::ACCOUNT_ERLOESE];
        $fromform->creditamount = [1000.00];
        $fromform->weight_debitaccount = [1];
        $fromform->weight_debitamount = [1];
        $fromform->weight_creditaccount = [1];
        $fromform->weight_creditamount = [1];

        return $fromform;
    }

    /**
     * Get form data for a multiple entries question.
     *
     * @return \stdClass The form data.
     */
    public function get_accounting_question_form_data_multiple_entries(): \stdClass {
        $fromform = new \stdClass();

        $fromform->name = 'Multiple entries question';
        $fromform->questiontext = [
            'text' => 'Record the following: Cash sale 500 EUR and bank sale 500 EUR.',
            'format' => FORMAT_HTML,
        ];
        $fromform->generalfeedback = ['text' => 'Two entries are needed.', 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;

        $fromform->chartofaccountsid = 0;
        $fromform->allowmultipleentries = 1;
        $fromform->maxentries = 5;

        $fromform->debitaccount = [self::ACCOUNT_KASSE, self::ACCOUNT_BANK];
        $fromform->debitamount = [500.00, 500.00];
        $fromform->creditaccount = [self::ACCOUNT_ERLOESE, self::ACCOUNT_ERLOESE];
        $fromform->creditamount = [500.00, 500.00];
        $fromform->weight_debitaccount = [1, 1];
        $fromform->weight_debitamount = [1, 1];
        $fromform->weight_creditaccount = [1, 1];
        $fromform->weight_creditamount = [1, 1];

        return $fromform;
    }

    /**
     * Get form data for a debit-only optional question.
     *
     * @return \stdClass The form data.
     */
    public function get_accounting_question_form_data_debit_only_optional(): \stdClass {
        $fromform = new \stdClass();

        $fromform->name = 'Debit optional question';
        $fromform->questiontext = ['text' => 'Record a credit entry to liability account.', 'format' => FORMAT_HTML];
        $fromform->generalfeedback = ['text' => 'Only credit account is required.', 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;

        $fromform->chartofaccountsid = 0;
        $fromform->allowmultipleentries = 0;
        $fromform->maxentries = 1;

        $fromform->debitaccount = [0];
        $fromform->debitamount = [0];
        $fromform->creditaccount = [self::ACCOUNT_VERBINDLICHKEITEN];
        $fromform->creditamount = [250.00];
        $fromform->weight_debitaccount = [1];
        $fromform->weight_debitamount = [1];
        $fromform->weight_creditaccount = [1];
        $fromform->weight_creditamount = [1];

        return $fromform;
    }

    /**
     * Create a response array for a simple entry.
     *
     * Note: The response field names remain as debitaccount_N / creditaccount_N
     * (Moodle question engine field names), but the values are integer account IDs.
     *
     * @param int $debitaccountid Debit account ID (0 for no debit account).
     * @param float $debitamount Debit amount.
     * @param int $creditaccountid Credit account ID.
     * @param float $creditamount Credit amount.
     * @param int $index Entry index (default 0).
     * @return array The response array.
     */
    public static function make_response(
        int $debitaccountid,
        float $debitamount,
        int $creditaccountid,
        float $creditamount,
        int $index = 0
    ): array {
        return [
            "debitaccount_{$index}" => $debitaccountid,
            "debitamount_{$index}" => $debitamount,
            "creditaccount_{$index}" => $creditaccountid,
            "creditamount_{$index}" => $creditamount,
        ];
    }

    /**
     * Create a response array with multiple entries.
     *
     * Each entry should use 'debitaccountid' and 'creditaccountid' keys with integer values.
     *
     * @param array $entries Array of entries, each with debitaccountid, debitamount, creditaccountid, creditamount.
     * @return array The response array.
     */
    public static function make_multi_response(array $entries): array {
        $response = [];
        foreach ($entries as $index => $entry) {
            $response["debitaccount_{$index}"] = $entry['debitaccountid'] ?? 0;
            $response["debitamount_{$index}"] = $entry['debitamount'] ?? 0;
            $response["creditaccount_{$index}"] = $entry['creditaccountid'] ?? 0;
            $response["creditamount_{$index}"] = $entry['creditamount'] ?? 0;
        }
        return $response;
    }
}
