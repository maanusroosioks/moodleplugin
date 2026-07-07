<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\local;

defined('MOODLE_INTERNAL') || die();

class repository {
    public function __construct(protected \moodle_database $db) {
    }

    public function get_course(int $courseid): \stdClass {
        return $this->db->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    }

    public function get_instance(int $instanceid): \stdClass {
        return $this->db->get_record('idetestfeedback', ['id' => $instanceid], '*', MUST_EXIST);
    }

    public function get_run(int $runid, int $instanceid): object|false {
        return $this->db->get_record('idetestfeedback_run', ['id' => $runid, 'idetestfeedbackid' => $instanceid]);
    }

    public function get_results(int $runid): array {
        return $this->db->get_records('idetestfeedback_result', ['runid' => $runid], 'id ASC');
    }

    public function get_user_brief(int $userid): object|false {
        $namefields = \core_user\fields::for_name()->get_sql()->selects;

        return $this->db->get_record('user', ['id' => $userid], "id{$namefields}");
    }

    public function get_runs_for_instance(int $instanceid): array {
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;

        return $this->db->get_records_sql(
            "SELECT r.*{$namefields}
               FROM {idetestfeedback_run} r
               JOIN {user} u ON u.id = r.userid
              WHERE r.idetestfeedbackid = :instanceid
              ORDER BY r.timecreated DESC",
            ['instanceid' => $instanceid]
        );
    }

    public function get_runs_for_user(int $instanceid, int $userid): array {
        return $this->db->get_records(
            'idetestfeedback_run',
            ['idetestfeedbackid' => $instanceid, 'userid' => $userid],
            'timecreated DESC'
        );
    }

    public function get_active_user_by_email(string $email): object|false {
        $users = $this->db->get_records_select(
            'user',
            'LOWER(email) = LOWER(:email) AND deleted = 0 AND suspended = 0',
            ['email' => $email]
        );

        if (count($users) !== 1) {
            return false;
        }

        return reset($users);
    }

    public function get_instance_by_assignmentkey(string $assignmentkey): object|false {
        return $this->db->get_record('idetestfeedback', ['assignmentkey' => $assignmentkey]);
    }

    public function insert_run(\stdClass $run): int {
        return $this->db->insert_record('idetestfeedback_run', $run);
    }

    public function insert_result(\stdClass $result): void {
        $this->db->insert_record('idetestfeedback_result', $result);
    }
}
