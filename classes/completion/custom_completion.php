<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\completion;

use core_completion\activity_custom_completion;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\required_tests;

defined('MOODLE_INTERNAL') || die();

class custom_completion extends activity_custom_completion {

    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $instanceid    = $this->cm->instance;
        $requiredtests = $DB->get_field('idetestfeedback', 'requiredtests', ['id' => $instanceid]);

        if (is_string($requiredtests) && trim($requiredtests) !== '') {
            $passed = $this->has_run_passing_required_tests($instanceid, $requiredtests);
        } else {
            $passed = $DB->record_exists('idetestfeedback_run', [
                'idetestfeedbackid' => $instanceid,
                'userid'            => $this->userid,
                'status'            => 'PASSED',
            ]);
        }

        return $passed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    private function has_run_passing_required_tests(int $instanceid, string $requiredtests): bool {
        global $DB;

        $entries = required_tests::parse($requiredtests);
        if ($entries === []) {
            return false;
        }

        $repository = new repository($DB);
        $runs = $repository->get_runs_for_user($instanceid, $this->userid);

        foreach ($runs as $run) {
            $tally = required_tests::evaluate($entries, $repository->get_results($run->id));
            if ($tally['total'] > 0 && $tally['passed'] === $tally['total']) {
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
