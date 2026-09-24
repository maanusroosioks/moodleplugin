<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_idetestfeedback\completion;

use core_completion\activity_custom_completion;
use mod_idetestfeedback\local\repository;

/**
 * The activity completion rules this activity defines.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Evaluates a completion rule for the user.
     *
     * @param string $rule the completion rule to evaluate
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    #[\Override]
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $passed = (new repository($DB))->has_fully_passing_run((int) $this->cm->instance, $this->userid);

        return $passed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The custom completion rules this activity defines.
     *
     * @return string[]
     */
    #[\Override]
    public static function get_defined_custom_rules(): array {
        return ['completionpassrun'];
    }

    /**
     * The descriptions of the custom completion rules.
     *
     * @return array<string, string> rule => the description shown to users
     */
    #[\Override]
    public function get_custom_rule_descriptions(): array {
        return [
            'completionpassrun' => get_string('completionpassrun_desc', 'mod_idetestfeedback'),
        ];
    }

    /**
     * The order the completion rules are displayed in.
     *
     * @return string[]
     */
    #[\Override]
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionpassrun',
        ];
    }
}
