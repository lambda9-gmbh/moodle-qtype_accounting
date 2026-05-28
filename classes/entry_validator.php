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
 * Submission validator for the Buchungssatz edit form.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Validates the entry rows submitted from the edit form.
 *
 * Extracted from {@see \qtype_accounting_edit_form} so the form class can stay
 * focused on building the form definition.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_validator {
    /**
     * Validate all entry rows in the submitted form data.
     *
     * Combines per-entry account ↔ amount completeness checks with the debit/credit
     * balance check.
     *
     * @param array $data Form data.
     * @param array $errors Errors accumulated so far (e.g. accountsindropdown).
     * @return array Errors after this validation pass.
     */
    public function validate(array $data, array $errors): array {
        $debitaccountarray = $data['debitaccount'] ?? [];
        $creditaccountarray = $data['creditaccount'] ?? [];
        $allindices = array_unique(array_merge(array_keys($debitaccountarray), array_keys($creditaccountarray)));

        $errors = $this->validate_completeness($data, $allindices, $errors);
        return $this->validate_balance($data, $allindices, $errors);
    }

    /**
     * Per-entry checks: account ↔ amount pairing rules.
     *
     * @param array $data Form data.
     * @param array $allindices Union of indices across debitaccount and creditaccount arrays.
     * @param array $errors Errors accumulated so far.
     * @return array Errors after this check.
     */
    protected function validate_completeness(array $data, array $allindices, array $errors): array {
        foreach ($allindices as $i) {
            $debitaccountid = (int)($data['debitaccount'][$i] ?? 0);
            $creditaccountid = (int)($data['creditaccount'][$i] ?? 0);
            $debitamountraw = trim($data['debitamount'][$i] ?? '');
            $creditamountraw = trim($data['creditamount'][$i] ?? '');

            // Skip completely empty entries.
            if ($debitaccountid === 0 && $debitamountraw === '' && $creditaccountid === 0 && $creditamountraw === '') {
                continue;
            }
            $errors = $this->validate_side(
                $errors,
                $i,
                'debitamount',
                $debitaccountid,
                $debitamountraw,
                'err_debitamountrequired',
                'err_debitaccountrequired'
            );
            $errors = $this->validate_side(
                $errors,
                $i,
                'creditamount',
                $creditaccountid,
                $creditamountraw,
                'err_creditamountrequired',
                'err_creditaccountrequired'
            );
        }
        return $errors;
    }

    /**
     * Validate the account/amount pair on one side of one entry row.
     *
     * @param array $errors Errors accumulated so far.
     * @param int|string $index Entry row index.
     * @param string $amountfield 'debitamount' or 'creditamount'.
     * @param int $accountid Account ID from the form (0 = none).
     * @param string $amountraw Raw amount input (already trim()ed).
     * @param string $amountmissingkey Lang key when account is set but amount is empty.
     * @param string $accountmissingkey Lang key when amount is set but account is empty.
     * @return array Errors after this check.
     */
    protected function validate_side(
        array $errors,
        $index,
        string $amountfield,
        int $accountid,
        string $amountraw,
        string $amountmissingkey,
        string $accountmissingkey
    ): array {
        if ($accountid > 0) {
            if ($amountraw === '') {
                $errors[$amountfield . '[' . $index . ']'] = get_string($amountmissingkey, 'qtype_accounting');
            } else if (floatval($amountraw) < 0) {
                $errors[$amountfield . '[' . $index . ']'] = get_string('err_negativeamount', 'qtype_accounting');
            }
        } else if ($amountraw !== '') {
            $errors['balancevalidation'] = get_string($accountmissingkey, 'qtype_accounting');
        }
        return $errors;
    }

    /**
     * Balance check: sum of debit amounts must equal sum of credit amounts (within tolerance).
     *
     * @param array $data Form data.
     * @param array $allindices Union of indices across debitaccount and creditaccount arrays.
     * @param array $errors Errors accumulated so far.
     * @return array Errors after this check.
     */
    protected function validate_balance(array $data, array $allindices, array $errors): array {
        $numberformat = $data['numberformat'] ?? 'de';
        $totaldebit = 0.0;
        $totalcredit = 0.0;
        foreach ($allindices as $i) {
            $totaldebit += amount_helper::parse_amount(
                trim($data['debitamount'][$i] ?? ''),
                $numberformat
            );
            $totalcredit += amount_helper::parse_amount(
                trim($data['creditamount'][$i] ?? ''),
                $numberformat
            );
        }
        if (abs($totaldebit - $totalcredit) > 0.001) {
            $errors['balancevalidation'] = get_string('err_balancemismatch', 'qtype_accounting');
        }
        return $errors;
    }
}
