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
 * Submission validator for the Buchungssatz edit form.
 *
 * @package    qtype_buchungssatz
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_buchungssatz;

/**
 * Validates the entry rows submitted from the edit form.
 *
 * Extracted from {@see \qtype_buchungssatz_edit_form} so the form class can stay
 * focused on building the form definition.
 *
 * @package    qtype_buchungssatz
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
        $sollkontoarray = $data['sollkonto'] ?? [];
        $habenkontoarray = $data['habenkonto'] ?? [];
        $allindices = array_unique(array_merge(array_keys($sollkontoarray), array_keys($habenkontoarray)));

        $errors = $this->validate_completeness($data, $allindices, $errors);
        return $this->validate_balance($data, $allindices, $errors);
    }

    /**
     * Per-entry checks: account ↔ amount pairing rules.
     *
     * @param array $data Form data.
     * @param array $allindices Union of indices across sollkonto and habenkonto arrays.
     * @param array $errors Errors accumulated so far.
     * @return array Errors after this check.
     */
    protected function validate_completeness(array $data, array $allindices, array $errors): array {
        foreach ($allindices as $i) {
            $sollkontoid = (int)($data['sollkonto'][$i] ?? 0);
            $habenkontoid = (int)($data['habenkonto'][$i] ?? 0);
            $sollbetragraw = trim($data['sollbetrag'][$i] ?? '');
            $habenbetragraw = trim($data['habenbetrag'][$i] ?? '');

            // Skip completely empty entries.
            if ($sollkontoid === 0 && $sollbetragraw === '' && $habenkontoid === 0 && $habenbetragraw === '') {
                continue;
            }
            $errors = $this->validate_side(
                $errors,
                $i,
                'sollbetrag',
                $sollkontoid,
                $sollbetragraw,
                'err_sollbetragrequired',
                'err_sollkontorequired'
            );
            $errors = $this->validate_side(
                $errors,
                $i,
                'habenbetrag',
                $habenkontoid,
                $habenbetragraw,
                'err_habenamountrequired',
                'err_habenkontorequired'
            );
        }
        return $errors;
    }

    /**
     * Validate the account/amount pair on one side of one entry row.
     *
     * @param array $errors Errors accumulated so far.
     * @param int|string $index Entry row index.
     * @param string $amountfield 'sollbetrag' or 'habenbetrag'.
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
                $errors[$amountfield . '[' . $index . ']'] = get_string($amountmissingkey, 'qtype_buchungssatz');
            } else if (floatval($amountraw) < 0) {
                $errors[$amountfield . '[' . $index . ']'] = get_string('err_negativeamount', 'qtype_buchungssatz');
            }
        } else if ($amountraw !== '') {
            $errors['balancevalidation'] = get_string($accountmissingkey, 'qtype_buchungssatz');
        }
        return $errors;
    }

    /**
     * Balance check: sum of debit amounts must equal sum of credit amounts (within tolerance).
     *
     * @param array $data Form data.
     * @param array $allindices Union of indices across sollkonto and habenkonto arrays.
     * @param array $errors Errors accumulated so far.
     * @return array Errors after this check.
     */
    protected function validate_balance(array $data, array $allindices, array $errors): array {
        $numberformat = $data['numberformat'] ?? 'de';
        $totaldebit = 0.0;
        $totalcredit = 0.0;
        foreach ($allindices as $i) {
            $totaldebit += amount_helper::parse_amount(
                trim($data['sollbetrag'][$i] ?? ''),
                $numberformat
            );
            $totalcredit += amount_helper::parse_amount(
                trim($data['habenbetrag'][$i] ?? ''),
                $numberformat
            );
        }
        if (abs($totaldebit - $totalcredit) > 0.001) {
            $errors['balancevalidation'] = get_string('err_balancemismatch', 'qtype_buchungssatz');
        }
        return $errors;
    }
}
