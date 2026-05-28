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
 * Feedback rendering for the Buchungssatz question type.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_accounting;

/**
 * Renders the feedback summary alert from a feedback array produced by
 * {@see feedback_calculator::calculate_aggregated_feedback()}.
 *
 * Kept separate from feedback_calculator so the calculator stays focused on
 * pure aggregation/comparison and this class on HTML output.
 *
 * @package    qtype_accounting
 * @copyright  2024 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_renderer {
    /**
     * Render the feedback summary alert from a feedback array.
     *
     * The success path shows a single green alert; non-correct paths surface per-side
     * messages built from the status + extra-accounts flags.
     *
     * @param array $feedback The aggregated feedback array.
     * @return string The HTML for the feedback summary.
     */
    public function render(array $feedback): string {
        $html = \html_writer::start_div('accounting-feedback-summary mt-3');
        if ($feedback['all_correct']) {
            $html .= $this->all_correct_alert();
        } else {
            $messages = $this->build_messages($feedback);
            $haspartial = ($feedback['debit_status'] === 'partial' || $feedback['credit_status'] === 'partial');
            $alertclass = $haspartial ? 'alert alert-warning' : 'alert alert-danger';
            $icon = $haspartial ? 'fa-exclamation-triangle' : 'fa-times-circle';
            $html .= $this->alert($alertclass, $icon, $messages);
        }
        $html .= \html_writer::end_div();
        return $html;
    }

    /**
     * Render the success alert shown when the student's response is fully correct.
     *
     * @return string The HTML for the alert.
     */
    protected function all_correct_alert(): string {
        return \html_writer::tag(
            'div',
            '<i class="fa fa-check-circle"></i> ' . get_string('allcorrect', 'qtype_accounting'),
            ['class' => 'alert alert-success']
        );
    }

    /**
     * Build the list of localised feedback messages for a non-correct response.
     *
     * @param array $feedback The aggregated feedback data.
     * @return array Localised message strings (may be empty if no per-side issue was detected).
     */
    protected function build_messages(array $feedback): array {
        return array_merge(
            $this->side_messages($feedback, 'debit'),
            $this->side_messages($feedback, 'credit')
        );
    }

    /**
     * Localised messages for one side ('debit' or 'credit') based on its status + extra-accounts flag.
     *
     * @param array $feedback The aggregated feedback data.
     * @param string $side Either 'debit' or 'credit'.
     * @return array Zero or one message strings depending on side status.
     */
    protected function side_messages(array $feedback, string $side): array {
        $status = $feedback[$side . '_status'];
        if ($status === 'incorrect') {
            return [get_string($side . 'incorrect', 'qtype_accounting')];
        }
        if ($status !== 'partial') {
            return [];
        }
        $key = !empty($feedback['has_extra_' . $side])
            ? $side . 'hasextraaccounts'
            : $side . 'partiallyincorrect';
        return [get_string($key, 'qtype_accounting')];
    }

    /**
     * Render a Bootstrap alert with a Font Awesome icon and joined message text.
     *
     * @param string $alertclass The full CSS class (e.g. 'alert alert-warning').
     * @param string $icon The Font Awesome icon name (e.g. 'fa-exclamation-triangle').
     * @param array $messages Localised messages to join with spaces.
     * @return string The alert HTML.
     */
    protected function alert(string $alertclass, string $icon, array $messages): string {
        return \html_writer::tag(
            'div',
            '<i class="fa ' . $icon . '"></i> ' . implode(' ', $messages),
            ['class' => $alertclass]
        );
    }
}
