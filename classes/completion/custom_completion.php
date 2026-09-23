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
        foreach ($repository->get_run_ids_for_user($instanceid, $this->userid) as $runid) {
            $tally = required_tests::evaluate($entries, $repository->get_results($runid));
            if ($tally['passed'] === $tally['total']) {
                return true;
            }
        }

        return false;
    }

    /**
     * The custom completion rules this activity defines.
     *
     * @return string[] the rules this activity defines
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
     * @return string[] the order the rules are displayed in
     */
    #[\Override]
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionpassrun',
        ];
    }
}
