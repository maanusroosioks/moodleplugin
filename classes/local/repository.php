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

namespace mod_idetestfeedback\local;

/**
 * Every data read and write for this activity; the privacy API keeps its own context SQL.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository {

    /**
     * @param \moodle_database $db the database to read and write through
     */
    public function __construct(protected \moodle_database $db) {
    }

    /**
     * @param int $courseid the course id
     * @return \stdClass the course
     */
    public function get_course(int $courseid): \stdClass {
        return $this->db->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    }

    /**
     * @param int $instanceid the activity instance id
     * @return \stdClass the activity instance
     */
    public function get_instance(int $instanceid): \stdClass {
        return $this->db->get_record('idetestfeedback', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * @param string $assignmentkey the key the IDE submits under
     * @return \stdClass|null the activity instance, or null when no activity uses that key
     */
    public function get_instance_by_assignmentkey(string $assignmentkey): ?\stdClass {
        return $this->db->get_record('idetestfeedback', ['assignmentkey' => $assignmentkey]) ?: null;
    }

    /**
     * A run, scoped to the activity it must belong to.
     *
     * @param int $runid the run id
     * @param int $instanceid the activity instance the run must belong to
     * @return \stdClass|null the run, or null when this activity has no such run
     */
    public function get_run(int $runid, int $instanceid): ?\stdClass {
        return $this->db->get_record(
            'idetestfeedback_run',
            ['id' => $runid, 'idetestfeedbackid' => $instanceid]
        ) ?: null;
    }

    /**
     * @param int $runid the run id
     * @return \stdClass[] the run's results, in insertion order
     */
    public function get_results(int $runid): array {
        return $this->db->get_records('idetestfeedback_result', ['runid' => $runid], 'id ASC');
    }

    /**
     * Every result of every run the user has in this activity, in one query.
     *
     * @param int $instanceid the activity instance id
     * @param int $userid the student
     * @return array<int, \stdClass[]> run id => that run's results, newest run first
     */
    public function get_results_by_run_for_user(int $instanceid, int $userid): array {
        $rows = $this->db->get_records_sql(
            "SELECT res.*
               FROM {idetestfeedback_result} res
               JOIN {idetestfeedback_run} run ON run.id = res.runid
              WHERE run.idetestfeedbackid = :instanceid
                AND run.userid = :userid
              ORDER BY run.timecreated DESC, run.id DESC, res.id ASC",
            ['instanceid' => $instanceid, 'userid' => $userid]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->runid][] = $row;
        }

        return $grouped;
    }

    /**
     * The feedback one user wrote on results in this activity, without the runs' owners.
     *
     * @param int $instanceid the activity instance id
     * @param int $userid the feedback author
     * @return \stdClass[] result rows keyed by id
     */
    public function get_feedback_authored_by(int $instanceid, int $userid): array {
        return $this->db->get_records_sql(
            "SELECT res.id, res.runid, res.testsuite, res.testname, res.feedback, res.feedbackmodified
               FROM {idetestfeedback_result} res
               JOIN {idetestfeedback_run} run ON run.id = res.runid
              WHERE run.idetestfeedbackid = :instanceid
                AND res.feedbackby = :userid
              ORDER BY res.id ASC",
            ['instanceid' => $instanceid, 'userid' => $userid]
        );
    }

    /**
     * @param int $userid the user id
     * @return \stdClass|null id and name fields only, or null when there is no such user
     */
    public function get_user_brief(int $userid): ?\stdClass {
        $namefields = \core_user\fields::for_name()->get_sql()->selects;

        return $this->db->get_record('user', ['id' => $userid], "id{$namefields}") ?: null;
    }

    /**
     * A page of runs for the whole activity, newest first.
     *
     * @param int $instanceid the activity instance id
     * @param array $filters optional 'userid' and/or 'status' to narrow the list
     * @param int $limitfrom the first row to return
     * @param int $limitnum how many rows to return, 0 for all of them
     * @return \stdClass[] runs, each carrying the submitting user's name fields
     */
    public function get_runs_for_instance(int $instanceid, array $filters = [],
                                          int $limitfrom = 0, int $limitnum = 0): array {
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        [$where, $params] = $this->run_filter_sql($instanceid, $filters, 'r');

        return $this->db->get_records_sql(
            "SELECT r.*{$namefields}
               FROM {idetestfeedback_run} r
               JOIN {user} u ON u.id = r.userid
              WHERE {$where}
              ORDER BY r.timecreated DESC, r.id DESC",
            $params, $limitfrom, $limitnum
        );
    }

    /**
     * @param int $instanceid the activity instance id
     * @param array $filters optional 'userid' and/or 'status' to narrow the count
     * @return int how many runs match
     */
    public function count_runs_for_instance(int $instanceid, array $filters = []): int {
        [$where, $params] = $this->run_filter_sql($instanceid, $filters);

        return $this->db->count_records_select('idetestfeedback_run', $where, $params);
    }

    /**
     * Distinct users who have at least one run in this activity, for the filter menu.
     *
     * @param int $instanceid the activity instance id
     * @return \stdClass[] id and name fields, ordered by name
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

    /**
     * @param int $instanceid the activity instance id
     * @param int $userid the student
     * @param int $limitfrom the first row to return
     * @param int $limitnum how many rows to return, 0 for all of them
     * @return \stdClass[] the user's runs, newest first
     */
    public function get_runs_for_user(int $instanceid, int $userid,
                                      int $limitfrom = 0, int $limitnum = 0): array {
        return $this->db->get_records(
            'idetestfeedback_run',
            ['idetestfeedbackid' => $instanceid, 'userid' => $userid],
            'timecreated DESC, id DESC',
            '*', $limitfrom, $limitnum
        );
    }

    /**
     * @param int $instanceid the activity instance id
     * @param int $userid the student
     * @return array [total runs, passing runs] for the user, computed in the DB
     */
    public function get_pass_stats(int $instanceid, int $userid): array {
        $row = $this->db->get_record_sql(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = :passed THEN 1 ELSE 0 END), 0) AS passed
               FROM {idetestfeedback_run}
              WHERE idetestfeedbackid = :instanceid
                AND userid = :userid",
            [
                'instanceid' => $instanceid,
                'userid'     => $userid,
                'passed'     => status::PASSED->value,
            ]
        );

        return [(int) $row->total, (int) $row->passed];
    }

    /**
     * Whether the user has a run in which every reported test passed; a run is
     * PASSED as soon as nothing failed, so skips have to be excluded too.
     *
     * @param int $instanceid the activity instance id
     * @param int $userid the student
     * @return bool
     */
    public function has_fully_passing_run(int $instanceid, int $userid): bool {
        return $this->db->record_exists('idetestfeedback_run', [
            'idetestfeedbackid' => $instanceid,
            'userid'            => $userid,
            'status'            => status::PASSED->value,
            'skippedcount'      => 0,
        ]);
    }

    /**
     * Builds the shared WHERE clause for the run list/count.
     *
     * @param int $instanceid the activity instance id
     * @param array $filters optional 'userid' and/or 'status'
     * @param string $alias the table alias to qualify columns with, '' for none
     * @return array [string $where, array $params]
     */
    private function run_filter_sql(int $instanceid, array $filters, string $alias = ''): array {
        $prefix = $alias === '' ? '' : $alias . '.';

        $where = "{$prefix}idetestfeedbackid = :instanceid";
        $params = ['instanceid' => $instanceid];

        if (!empty($filters['userid'])) {
            $where .= " AND {$prefix}userid = :fuserid";
            $params['fuserid'] = $filters['userid'];
        }
        if (!empty($filters['status'])) {
            $where .= " AND {$prefix}status = :fstatus";
            $params['fstatus'] = $filters['status'];
        }

        return [$where, $params];
    }

    /**
     * More than one is possible when $CFG->allowaccountssameemail is on.
     *
     * @param string $email the address the middleware asserted
     * @return \stdClass[] id only, keyed by id
     */
    public function get_active_users_by_email(string $email): array {
        global $CFG;

        $emailmatch = $this->db->sql_equal('email', ':email', false);

        return $this->db->get_records_select(
            'user',
            "{$emailmatch}
               AND mnethostid = :mnethostid
               AND deleted = 0
               AND suspended = 0
               AND confirmed = 1",
            ['email' => $email, 'mnethostid' => $CFG->mnet_localhost_id],
            '',
            'id'
        );
    }

    /**
     * Stores a run and its results atomically, so a rejected result cannot
     * leave behind a run whose counts describe rows that were never written.
     *
     * @param \stdClass $run the run to insert
     * @param \stdClass[] $results its results; runid is filled in here
     * @return int the new run id
     */
    public function insert_run_with_results(\stdClass $run, array $results): int {
        $transaction = $this->db->start_delegated_transaction();

        try {
            $runid = $this->db->insert_record('idetestfeedback_run', $run);
            foreach ($results as $result) {
                $result->runid = $runid;
            }
            $this->db->insert_records('idetestfeedback_result', $results);

            $transaction->allow_commit();

            return $runid;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Stores (or clears) a teacher's feedback on a single test case result.
     *
     * @param int $resultid the idetestfeedback_result id
     * @param string $feedback the feedback text; '' clears it
     * @param int $format the text format the feedback is stored in
     * @param int $byuserid the teacher writing the feedback
     */
    public function update_result_feedback(int $resultid, string $feedback, int $format, int $byuserid): void {
        $cleared = trim($feedback) === '';

        $this->db->update_record('idetestfeedback_result', (object) [
            'id'               => $resultid,
            'feedback'         => $cleared ? null : $feedback,
            'feedbackformat'   => $cleared ? 0 : $format,
            'feedbackby'       => $cleared ? null : $byuserid,
            'feedbackmodified' => $cleared ? null : time(),
        ]);
    }

    /**
     * Deletes runs and their results, optionally limited to specific users.
     *
     * @param int $instanceid the activity instance id
     * @param int[]|null $userids null for every user, otherwise only these users
     */
    public function delete_runs(int $instanceid, ?array $userids = null): void {
        $select = 'idetestfeedbackid = :instanceid';
        $params = ['instanceid' => $instanceid];

        if ($userids !== null) {
            if (!$userids) {
                return;
            }
            [$insql, $inparams] = $this->db->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'duser');
            $select .= " AND userid {$insql}";
            $params += $inparams;
        }

        $transaction = $this->db->start_delegated_transaction();

        try {
            $this->db->delete_records_select(
                'idetestfeedback_result',
                "runid IN (SELECT id FROM {idetestfeedback_run} WHERE {$select})",
                $params
            );
            $this->db->delete_records_select('idetestfeedback_run', $select, $params);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Deletes an activity instance, with every run and result it holds.
     *
     * @param int $instanceid the activity instance id
     */
    public function delete_instance(int $instanceid): void {
        $this->delete_runs($instanceid);
        $this->db->delete_records('idetestfeedback', ['id' => $instanceid]);
    }

    /**
     * Detaches teachers from the feedback they wrote, keeping the feedback.
     *
     * @param int $instanceid the activity instance id
     * @param int[] $userids the teachers to detach
     */
    public function anonymise_feedback_authors(int $instanceid, array $userids): void {
        if (!$userids) {
            return;
        }

        [$insql, $params] = $this->db->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'auser');
        $params['instanceid'] = $instanceid;

        $this->db->execute(
            "UPDATE {idetestfeedback_result}
                SET feedbackby = NULL
              WHERE feedbackby {$insql}
                AND runid IN (SELECT id FROM {idetestfeedback_run} WHERE idetestfeedbackid = :instanceid)",
            $params
        );
    }
}
