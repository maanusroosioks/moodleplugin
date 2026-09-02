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

    /**
     * A page of runs for the whole activity, newest first.
     *
     * @param array $filters optional 'userid' and/or 'status' to narrow the list
     */
    public function get_runs_for_instance(int $instanceid, array $filters = [],
                                          int $limitfrom = 0, int $limitnum = 0): array {
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        [$where, $params] = $this->run_filter_sql($instanceid, $filters);

        return $this->db->get_records_sql(
            "SELECT r.*{$namefields}
               FROM {idetestfeedback_run} r
               JOIN {user} u ON u.id = r.userid
              WHERE {$where}
              ORDER BY r.timecreated DESC, r.id DESC",
            $params, $limitfrom, $limitnum
        );
    }

    public function count_runs_for_instance(int $instanceid, array $filters = []): int {
        [$where, $params] = $this->run_filter_sql($instanceid, $filters);

        return $this->db->count_records_select('idetestfeedback_run', $where, $params);
    }

    /**
     * Distinct users who have at least one run in this activity, for the filter menu.
     */
    public function get_students_with_runs(int $instanceid): array {
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;

        return $this->db->get_records_sql(
            "SELECT DISTINCT u.id{$namefields}
               FROM {idetestfeedback_run} r
               JOIN {user} u ON u.id = r.userid
              WHERE r.idetestfeedbackid = :instanceid
              ORDER BY u.lastname, u.firstname",
            ['instanceid' => $instanceid]
        );
    }

    public function get_runs_for_user(int $instanceid, int $userid,
                                      int $limitfrom = 0, int $limitnum = 0): array {
        return $this->db->get_records(
            'idetestfeedback_run',
            ['idetestfeedbackid' => $instanceid, 'userid' => $userid],
            'timecreated DESC, id DESC',
            '*', $limitfrom, $limitnum
        );
    }

    public function count_runs_for_user(int $instanceid, int $userid): int {
        return $this->db->count_records('idetestfeedback_run',
            ['idetestfeedbackid' => $instanceid, 'userid' => $userid]);
    }

    /**
     * @return array [total runs, passing runs] for the user, computed in the DB
     */
    public function get_pass_stats(int $instanceid, int $userid): array {
        $total  = $this->count_runs_for_user($instanceid, $userid);
        $passed = $this->db->count_records('idetestfeedback_run',
            ['idetestfeedbackid' => $instanceid, 'userid' => $userid, 'status' => 'PASSED']);

        return [$total, $passed];
    }

    /**
     * Builds the shared WHERE clause (no table alias) for the run list/count.
     *
     * @return array [string $where, array $params]
     */
    private function run_filter_sql(int $instanceid, array $filters): array {
        $where = 'idetestfeedbackid = :instanceid';
        $params = ['instanceid' => $instanceid];

        if (!empty($filters['userid'])) {
            $where .= ' AND userid = :fuserid';
            $params['fuserid'] = $filters['userid'];
        }
        if (!empty($filters['status'])) {
            $where .= ' AND status = :fstatus';
            $params['fstatus'] = $filters['status'];
        }

        return [$where, $params];
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
