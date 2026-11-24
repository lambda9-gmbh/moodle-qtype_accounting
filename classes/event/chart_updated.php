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

namespace qtype_accounting\event;

/**
 * Event triggered when a chart of accounts template is updated.
 *
 * @package    qtype_accounting
 * @copyright  2026 Hochschule Flensburg / lambda9
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_updated extends \core\event\base {
    /**
     * Create the event for a template.
     *
     * @param int $chartid The chart ID.
     * @return self The event.
     */
    public static function create_from_chartid(int $chartid): self {
        return self::create([
            'objectid' => $chartid,
            'context' => \context_system::instance(),
        ]);
    }

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'qtype_accounting_charts';
    }

    /**
     * Get the localised event name.
     *
     * @return string The name.
     */
    public static function get_name() {
        return get_string('event_chart_updated', 'qtype_accounting');
    }

    /**
     * Get the non-localised description.
     *
     * @return string The description.
     */
    public function get_description() {
        return "The user with id '$this->userid' has changed the chart of accounts template with id '$this->objectid'.";
    }

    /**
     * Get the URL of the template.
     *
     * @return \moodle_url The URL.
     */
    public function get_url() {
        return new \moodle_url('/question/type/accounting/edit_chart.php', ['chartid' => $this->objectid]);
    }

    /**
     * Map the object ID for restore; templates are not part of backups.
     *
     * @return array The mapping.
     */
    public static function get_objectid_mapping() {
        return ['db' => 'qtype_accounting_charts', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
