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

namespace qtype_accounting\output;

/**
 * Summary of what is wrong on the debit and credit side of a response.
 *
 * Rendered by the template qtype_accounting/feedback_summary from the result of
 * {@see \qtype_accounting\feedback_calculator::calculate_aggregated_feedback()}.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_summary implements \renderable, \templatable {
    /**
     * Create the summary.
     *
     * @param array $feedback The aggregated feedback.
     */
    public function __construct(
        /** @var array The aggregated feedback. */
        protected array $feedback
    ) {
    }

    /**
     * Export the summary for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array The template context.
     */
    public function export_for_template(\renderer_base $output): array {
        if ($this->feedback['all_correct']) {
            return ['allcorrect' => true];
        }
        $messages = [];
        foreach (['debit', 'credit'] as $side) {
            $status = $this->feedback[$side . '_status'];
            if ($status === 'incorrect') {
                $messages[] = get_string($side . 'incorrect', 'qtype_accounting');
            } else if ($status === 'partial') {
                $key = !empty($this->feedback['has_extra_' . $side]) ? 'hasextraaccounts' : 'partiallyincorrect';
                $messages[] = get_string($side . $key, 'qtype_accounting');
            }
        }
        $partial = $this->feedback['debit_status'] === 'partial' || $this->feedback['credit_status'] === 'partial';
        return [
            'allcorrect' => false,
            'partial' => $partial,
            'message' => implode(' ', $messages),
        ];
    }
}
