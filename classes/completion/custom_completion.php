<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\completion;

use core_completion\activity_custom_completion;

defined('MOODLE_INTERNAL') || die();

class custom_completion extends activity_custom_completion {

    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $instanceid    = $this->cm->instance;
        $requiredtests = $DB->get_field('idetestfeedback', 'requiredtests', ['id' => $instanceid]);

        if (is_string($requiredtests) && trim($requiredtests) !== '') {
            $passed = $DB->record_exists_select(
                'idetestfeedback_run',
                'idetestfeedbackid = :id AND userid = :userid
                     AND requiredtotal > 0 AND requiredpassed = requiredtotal',
                ['id' => $instanceid, 'userid' => $this->userid]
            );
        } else {
            $passed = $DB->record_exists('idetestfeedback_run', [
                'idetestfeedbackid' => $instanceid,
                'userid'            => $this->userid,
                'status'            => 'PASSED',
            ]);
        }

        return $passed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
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
