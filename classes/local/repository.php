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
    /** @var int Seconds a write waits for another write to the same activity to finish */
    private const LOCK_TIMEOUT = 30;

    /**
     * Creates the repository.
     *
     * @param \moodle_database $db the database to read and write through
     */
    public function __construct(
        /** @var \moodle_database The database to read and write through */
        protected \moodle_database $db
    ) {
    }

    /**
     * Fetches an activity instance.
     *
     * @param int $instanceid the activity instance id
     * @return \stdClass the activity instance
     */
    public function get_instance(int $instanceid): \stdClass {
        return $this->db->get_record('idetestfeedback', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * Fetches the activity instance that uses a key.
     *
     * @param string $assignmentkey the key the IDE submits under
     * @return \stdClass|null the activity instance, or null when no activity uses that key
     */
    public function get_instance_by_assignmentkey(string $assignmentkey): ?\stdClass {
        return $this->db->get_record('idetestfeedback', ['assignmentkey' => $assignmentkey]) ?: null;
    }

    /**
     * Fetches a run, scoped to the activity it must belong to.
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
     * Fetches the results of a run.
     *
     * @param int $runid the run id
     * @return \stdClass[] the run's results, by status in {@see status::listing_order()}, then test suite and name
     */
    public function get_results(int $runid): array {
        $params = ['runid' => $runid];
        $ranks = '';
        foreach (status::listing_order() as $rank => $status) {
            $ranks .= " WHEN :status{$rank} THEN {$rank}";
            $params["status{$rank}"] = $status->value;
        }
        $unranked = count(status::listing_order());

        return $this->db->get_records_sql(
            "SELECT *
               FROM {idetestfeedback_result}
              WHERE runid = :runid
           ORDER BY CASE status{$ranks} ELSE {$unranked} END,
                    COALESCE(testsuite, ''), testname, id",
            $params
        );
    }

    /**
     * Fetches the test files captured with a run, each with the body it points at.
     *
     * @param int $runid the run id
     * @return \stdClass[] the test files captured with the run, by path
     */
    public function get_files(int $runid): array {
        return $this->db->get_records_sql(
            "SELECT f.id, f.path, b.contenthash, b.content, f.truncated
               FROM {idetestfeedback_file} f
          LEFT JOIN {idetestfeedback_blob} b ON b.id = f.blobid
              WHERE f.runid = :runid
           ORDER BY f.path ASC",
            ['runid' => $runid]
        );
    }

    /**
     * Fetches one file captured with a run, with the body it points at.
     *
     * @param int $fileid the file id
     * @param int $runid the run the file must belong to
     * @return \stdClass|null the file, or null when the run has no such file
     */
    public function get_file(int $fileid, int $runid): ?\stdClass {
        return $this->db->get_record_sql(
            "SELECT f.id, f.path, b.contenthash, b.content, f.truncated
               FROM {idetestfeedback_file} f
          LEFT JOIN {idetestfeedback_blob} b ON b.id = f.blobid
              WHERE f.id = :fileid
                AND f.runid = :runid",
            ['fileid' => $fileid, 'runid' => $runid]
        ) ?: null;
    }

    /**
     * Fetches the results a teacher commented on before a run was submitted, across
     * the same user's earlier runs in the same activity.
     *
     * @param int $runid the run to look back from
     * @return \stdClass[] result rows keyed by id, newest run first
     */
    public function get_feedback_history(int $runid): array {
        return $this->db->get_records_sql(
            "SELECT res.id, res.testsuite, res.testname, res.status
               FROM {idetestfeedback_result} res
               JOIN {idetestfeedback_run} r ON r.id = res.runid
               JOIN {idetestfeedback_run} cur ON cur.id = :runid
                    AND cur.idetestfeedbackid = r.idetestfeedbackid
                    AND cur.userid = r.userid
              WHERE (r.timecreated < cur.timecreated
                     OR (r.timecreated = cur.timecreated AND r.id < cur.id))
                AND res.feedback IS NOT NULL
                AND res.feedbackmodified < cur.timecreated
           ORDER BY r.timecreated DESC, r.id DESC, res.id ASC",
            ['runid' => $runid]
        );
    }

    /**
     * Fetches the feedback one user wrote on results in this activity, without the runs' owners.
     *
     * @param int $instanceid the activity instance id
     * @param int $authorid the feedback author
     * @return \stdClass[] result rows keyed by id
     */
    public function get_feedback_authored_by(int $instanceid, int $authorid): array {
        return $this->db->get_records_sql(
            "SELECT res.id, res.runid, res.testsuite, res.testname, res.feedback, res.feedbackmodified
               FROM {idetestfeedback_result} res
               JOIN {idetestfeedback_run} run ON run.id = res.runid
              WHERE run.idetestfeedbackid = :instanceid
                AND res.feedbackby = :authorid
           ORDER BY res.id ASC",
            ['instanceid' => $instanceid, 'authorid' => $authorid]
        );
    }

    /**
     * Fetches a user's id and name fields.
     *
     * @param int $userid the user id
     * @return \stdClass|null id and name fields only, or null when there is no such user
     */
    public function get_user_brief(int $userid): ?\stdClass {
        $namefields = \core_user\fields::for_name()->get_sql()->selects;

        return $this->db->get_record('user', ['id' => $userid], "id{$namefields}") ?: null;
    }

    /**
     * Fetches a page of runs for the whole activity, newest first.
     *
     * @param int $instanceid the activity instance id
     * @param int $userid only this user's runs, or 0 for every user
     * @param status|null $status only runs with this status, or null for any
     * @param int $groupid only runs by members of this group, or 0 for every group
     * @param int $limitfrom the first row to return
     * @param int $limitnum how many rows to return, 0 for all of them
     * @return \stdClass[] runs, each carrying the submitting user's name fields
     */
    public function get_runs_for_instance(
        int $instanceid,
        int $userid = 0,
        ?status $status = null,
        int $groupid = 0,
        int $limitfrom = 0,
        int $limitnum = 0
    ): array {
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        [$where, $params] = $this->run_filter_sql($instanceid, $userid, $status, $groupid, 'r');

        return $this->db->get_records_sql(
            "SELECT r.*{$namefields}
               FROM {idetestfeedback_run} r
               JOIN {user} u ON u.id = r.userid
              WHERE {$where}
           ORDER BY r.timecreated DESC, r.id DESC",
            $params,
            $limitfrom,
            $limitnum
        );
    }

    /**
     * Counts the runs in the activity.
     *
     * @param int $instanceid the activity instance id
     * @param int $userid only this user's runs, or 0 for every user
     * @param status|null $status only runs with this status, or null for any
     * @param int $groupid only runs by members of this group, or 0 for every group
     * @return int how many runs match
     */
    public function count_runs_for_instance(
        int $instanceid,
        int $userid = 0,
        ?status $status = null,
        int $groupid = 0
    ): int {
        [$where, $params] = $this->run_filter_sql($instanceid, $userid, $status, $groupid);

        return $this->db->count_records_select('idetestfeedback_run', $where, $params);
    }

    /**
     * Fetches the distinct users who have at least one run in this activity, for the filter menu.
     *
     * @param int $instanceid the activity instance id
     * @param int $groupid only members of this group, or 0 for everyone
     * @return \stdClass[] id and name fields, ordered by name
     */
    public function get_students_with_runs(int $instanceid, int $groupid = 0): array {
        $namefields = \core_user\fields::for_name()->get_sql('u')->selects;
        [$where, $params] = $this->run_filter_sql($instanceid, 0, null, $groupid, 'r');

        return $this->db->get_records_sql(
            "SELECT DISTINCT u.id{$namefields}
               FROM {idetestfeedback_run} r
               JOIN {user} u ON u.id = r.userid
              WHERE {$where}
           ORDER BY u.lastname, u.firstname",
            $params
        );
    }

    /**
     * Fetches a page of one user's runs, newest first.
     *
     * @param int $instanceid the activity instance id
     * @param int $userid the student
     * @param int $limitfrom the first row to return
     * @param int $limitnum how many rows to return, 0 for all of them
     * @return \stdClass[] the user's runs, newest first
     */
    public function get_runs_for_user(
        int $instanceid,
        int $userid,
        int $limitfrom = 0,
        int $limitnum = 0
    ): array {
        return $this->db->get_records(
            'idetestfeedback_run',
            ['idetestfeedbackid' => $instanceid, 'userid' => $userid],
            'timecreated DESC, id DESC',
            '*',
            $limitfrom,
            $limitnum
        );
    }

    /**
     * Counts a user's runs and passing runs.
     *
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
     * Checks whether the user has a run in which every reported test passed; a run is
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
     * Fetches the active local users with an email address, matched case-insensitively.
     *
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
     * Stores a run with its results and files atomically, so a rejected result
     * cannot leave behind a run whose counts describe rows that were never written.
     *
     * Every hash is derived here, so no caller can supply one of its own.
     *
     * @param \stdClass $run the run to insert
     * @param \stdClass[] $results its results; runid is filled in here
     * @param \stdClass[] $files its captured test files, left unchanged
     * @return int the new run id
     */
    public function insert_run(\stdClass $run, array $results, array $files = []): int {
        $instanceid = (int) $run->idetestfeedbackid;
        $bodies = self::canonicalise_files(array_map(fn($file) => clone $file, $files));

        return $this->write_locked($instanceid, function () use ($instanceid, $run, $results, $bodies): int {
            $runid = $this->db->insert_record('idetestfeedback_run', $run);

            $blobs = $this->store_blobs($instanceid, $bodies, (int) $run->timecreated);

            foreach ($results as $result) {
                $result->runid = $runid;
            }
            $this->db->insert_records('idetestfeedback_result', $results);

            $links = [];
            foreach ($bodies as $path => $file) {
                $links[] = (object) [
                    'runid'       => $runid,
                    'path'        => $path,
                    'blobid'      => $file->contenthash === null ? null : $blobs[$file->contenthash],
                    'truncated'   => (int) ($file->truncated ?? 0),
                ];
            }
            $this->db->insert_records('idetestfeedback_file', $links);

            return $runid;
        });
    }

    /**
     * Rewrites each file's body in its canonical form and hashes it. A body
     * that canonicalises to nothing is left without one.
     *
     * @param \stdClass[] $files rows carrying path and content
     * @return array<string, \stdClass> the same rows, by path, with contenthash set
     */
    private static function canonicalise_files(array $files): array {
        $bypath = [];

        foreach ($files as $file) {
            $content = source_code::canonicalise((string) ($file->content ?? ''));

            $file->content = $content === '' ? null : $content;
            $file->contenthash = $content === '' ? null : source_code::hash_canonical($content);
            $bypath[(string) $file->path] = $file;
        }

        return $bypath;
    }

    /**
     * Finds or stores one blob per distinct body, within the activity.
     *
     * @param int $instanceid the activity the bodies belong to
     * @param array<string, \stdClass> $bodies canonicalised files by path
     * @param int $timecreated when the run was submitted
     * @return array<string, int> blob id by content hash
     */
    private function store_blobs(int $instanceid, array $bodies, int $timecreated): array {
        $blobs = [];

        foreach ($bodies as $file) {
            $hash = $file->contenthash;
            if ($hash !== null && !isset($blobs[$hash])) {
                $blobs[$hash] = $this->find_or_create_canonical_blob($instanceid, $hash, $file->content, $timecreated);
            }
        }

        return $blobs;
    }

    /**
     * Finds the blob holding a body within the activity, storing it first if it is new.
     *
     * @param int $instanceid the activity the body belongs to
     * @param string $content the body as it was received
     * @param int $timecreated when the body was first seen, if it is new
     * @return int|null the blob id, or null when the body canonicalises to nothing
     */
    public function find_or_create_blob(int $instanceid, string $content, int $timecreated): ?int {
        $canonical = source_code::canonicalise($content);
        if ($canonical === '') {
            return null;
        }

        return $this->find_or_create_canonical_blob(
            $instanceid,
            source_code::hash_canonical($canonical),
            $canonical,
            $timecreated
        );
    }

    /**
     * Finds the blob for an already canonicalised body, storing it first if it is new.
     *
     * @param int $instanceid the activity the body belongs to
     * @param string $hash the hash of the canonical body
     * @param string $canonical the canonical body
     * @param int $timecreated when the body was first seen, if it is new
     * @return int the blob id
     */
    private function find_or_create_canonical_blob(
        int $instanceid,
        string $hash,
        string $canonical,
        int $timecreated
    ): int {
        $existing = $this->db->get_field(
            'idetestfeedback_blob',
            'id',
            ['idetestfeedbackid' => $instanceid, 'contenthash' => $hash]
        );

        return (int) ($existing ?: $this->db->insert_record('idetestfeedback_blob', (object) [
            'idetestfeedbackid' => $instanceid,
            'contenthash'       => $hash,
            'content'           => $canonical,
            'timecreated'       => $timecreated,
        ]));
    }

    /**
     * Stores (or clears) a teacher's feedback on a single test case result.
     *
     * @param int $resultid the idetestfeedback_result id
     * @param string $feedback the feedback text, in plain text; '' clears it
     * @param int $authorid the teacher writing the feedback
     */
    public function update_result_feedback(int $resultid, string $feedback, int $authorid): void {
        $cleared = trim($feedback) === '';

        $this->db->update_record('idetestfeedback_result', (object) [
            'id'               => $resultid,
            'feedback'         => $cleared ? null : $feedback,
            'feedbackby'       => $cleared ? null : $authorid,
            'feedbackmodified' => $cleared ? null : time(),
        ]);
    }

    /**
     * Deletes runs and their results, optionally limited to specific users.
     *
     * A body outlives the run that stored it while another run points at it.
     *
     * @param int $instanceid the activity instance id
     * @param int[]|null $userids null for every user, otherwise only these users
     */
    public function delete_runs(int $instanceid, ?array $userids = null): void {
        if ($userids === []) {
            return;
        }

        $this->write_locked($instanceid, fn() => $this->purge_runs($instanceid, $userids));
    }

    /**
     * Deletes runs and every body no remaining run points at, under a lock the caller holds.
     *
     * @param int $instanceid the activity instance id
     * @param int[]|null $userids null for every user, otherwise only these users
     */
    private function purge_runs(int $instanceid, ?array $userids): void {
        $select = 'idetestfeedbackid = :instanceid';
        $params = ['instanceid' => $instanceid];

        if ($userids !== null) {
            [$insql, $inparams] = $this->db->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'duser');
            $select .= " AND userid {$insql}";
            $params += $inparams;
        }

        foreach (['idetestfeedback_result', 'idetestfeedback_file'] as $child) {
            $this->db->delete_records_select(
                $child,
                "runid IN (SELECT id FROM {idetestfeedback_run} WHERE {$select})",
                $params
            );
        }
        $this->db->delete_records_select('idetestfeedback_run', $select, $params);

        $this->db->delete_records_select(
            'idetestfeedback_blob',
            "idetestfeedbackid = :binstanceid
             AND NOT EXISTS (SELECT 1
                               FROM {idetestfeedback_file} f
                              WHERE f.blobid = {idetestfeedback_blob}.id)",
            ['binstanceid' => $instanceid]
        );
    }

    /**
     * Fetches the ids of the activity instances in a course.
     *
     * @param int $courseid the course id
     * @return int[] the ids of every activity instance in the course
     */
    public function get_instance_ids_in_course(int $courseid): array {
        return array_map('intval', $this->db->get_fieldset_select(
            'idetestfeedback',
            'id',
            'course = :courseid',
            ['courseid' => $courseid]
        ));
    }

    /**
     * Deletes an activity instance, with every run and result it holds.
     *
     * @param int $instanceid the activity instance id
     */
    public function delete_instance(int $instanceid): void {
        $this->write_locked($instanceid, function () use ($instanceid): void {
            $this->purge_runs($instanceid, null);
            $this->db->delete_records('idetestfeedback', ['id' => $instanceid]);
        });
    }

    /**
     * Detaches teachers from the feedback they wrote, keeping the feedback.
     *
     * @param int $instanceid the activity instance id
     * @param int[] $authorids the teachers to detach
     */
    public function anonymise_feedback_authors(int $instanceid, array $authorids): void {
        if (!$authorids) {
            return;
        }

        [$insql, $params] = $this->db->get_in_or_equal($authorids, SQL_PARAMS_NAMED, 'auser');
        $params['instanceid'] = $instanceid;

        $this->db->execute(
            "UPDATE {idetestfeedback_result}
                SET feedbackby = NULL
              WHERE feedbackby {$insql}
                AND runid IN (SELECT id FROM {idetestfeedback_run} WHERE idetestfeedbackid = :instanceid)",
            $params
        );
    }

    /**
     * Runs a write to an activity's runs and blobs in one transaction, one writer per
     * activity at a time, so no body is deleted as unused while a new run links to it.
     *
     * Nested in a caller's transaction, the lock is released before that transaction commits.
     *
     * @param int $instanceid the activity instance id
     * @param callable $write the write to run
     * @return mixed whatever the write returns
     */
    private function write_locked(int $instanceid, callable $write): mixed {
        $lock = \core\lock\lock_config::get_lock_factory('mod_idetestfeedback')
            ->get_lock("instance_{$instanceid}", self::LOCK_TIMEOUT, MINSECS);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'moodle');
        }

        try {
            $transaction = $this->db->start_delegated_transaction();

            try {
                $result = $write();
                $transaction->allow_commit();

                return $result;
            } catch (\Throwable $e) {
                $transaction->rollback($e);

                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Builds the shared WHERE clause for the run list/count.
     *
     * @param int $instanceid the activity instance id
     * @param int $userid only this user's runs, or 0 for every user
     * @param status|null $status only runs with this status, or null for any
     * @param int $groupid only runs by members of this group, or 0 for every group
     * @param string $alias the table alias to qualify columns with, '' for none
     * @return array [string $where, array $params]
     */
    private function run_filter_sql(
        int $instanceid,
        int $userid,
        ?status $status,
        int $groupid,
        string $alias = ''
    ): array {
        $prefix = $alias === '' ? '' : $alias . '.';

        $where = "{$prefix}idetestfeedbackid = :instanceid";
        $params = ['instanceid' => $instanceid];

        if ($userid !== 0) {
            $where .= " AND {$prefix}userid = :fuserid";
            $params['fuserid'] = $userid;
        }
        if ($status !== null) {
            $where .= " AND {$prefix}status = :fstatus";
            $params['fstatus'] = $status->value;
        }
        if ($groupid !== 0) {
            $where .= " AND {$prefix}userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :fgroupid)";
            $params['fgroupid'] = $groupid;
        }

        return [$where, $params];
    }
}
