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
use mod_idetestfeedback\local\required_tests;

/**
 * The activity completion rules this activity defines.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $repository = new repository($DB);
        $instanceid = $this->cm->instance;
        $entries    = required_tests::parse($repository->get_instance($instanceid)->requiredtests);

        $passed = $entries === []
            ? $repository->has_fully_passing_run($instanceid, $this->userid)
            : $this->has_run_passing_required_tests($repository, $instanceid, $entries);

        return $passed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Whether any of the user's runs passed every defined test case.
     *
     * @param repository $repository the activity's database access
     * @param int $instanceid the activity instance id
     * @param string[] $entries the defined test cases, from required_tests::parse()
     * @return bool
     */
    private function has_run_passing_required_tests(repository $repository, int $instanceid, array $entries): bool {
        foreach ($repository->get_results_by_run_for_user($instanceid, $this->userid) as $results) {
            $tally = required_tests::evaluate($entries, $results);
            if ($tally['passed'] === $tally['total']) {
                return true;
            }
        }

        return false;
    }

    public static function get_defined_custom_rules(): array {
        return ['completionpassrun'];
    }

    public function get_custom_rule_descriptions(): array {
        return [
            'completionpassrun' => get_string('completionpassrun_desc', 'mod_idetestfeedback'),
        ];
    }

    public function get_sort_order(): array {
        return [
            'completionview',
            'completionpassrun',
        ];
    }
}
