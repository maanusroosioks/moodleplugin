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
 * Tests for the activity's database access.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\repository::class)]
final class repository_test extends \advanced_testcase {
    /** @var array A captured test file, as run overrides for {@see create_run()}. */
    private const FILES = ['files' => [['path' => 'tests/test_calculator.py', 'content' => 'import pytest']]];

    /** @var repository */
    private repository $repository;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $instance;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $teacher;

    /** @var \mod_idetestfeedback_generator */
    private \mod_idetestfeedback_generator $generator;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $this->repository = new repository($DB);

        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('idetestfeedback', [
            'course' => $this->course->id,
        ]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback');
    }

    /**
     * Stores a run through the repository.
     *
     * @param int $userid the student to attribute the run to
     * @param array|null $results the test case results, or null for a single passing test
     * @param array $overrides extra run fields, e.g. 'files' or 'timecreated'
     * @return \stdClass the stored run
     */
    private function create_run(int $userid, ?array $results = null, array $overrides = []): \stdClass {
        return $this->generator->create_run(array_filter(['results' => $results]) + $overrides + [
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $userid,
        ]);
    }

    /**
     * A run row ready to insert, for a single passing test.
     *
     * @return \stdClass
     */
    private function run_row(): \stdClass {
        return (object) [
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $this->student->id,
            'ide' => 'VSCODE',
            'status' => status::PASSED->value,
            'passedcount' => 1,
            'failedcount' => 0,
            'skippedcount' => 0,
            'errorcount' => 0,
            'timecreated' => time(),
        ];
    }

    /**
     * How many file bodies an activity stores.
     *
     * @param int $instanceid the activity
     * @return int
     */
    private function blob_count(int $instanceid): int {
        global $DB;

        return $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $instanceid]);
    }

    public function test_get_instance_returns_the_instance(): void {
        $instance = $this->repository->get_instance($this->instance->id);

        $this->assertSame((int) $this->instance->id, (int) $instance->id);
    }

    public function test_get_instance_throws_when_missing(): void {
        $this->expectException(\dml_missing_record_exception::class);

        $this->repository->get_instance(0);
    }

    public function test_get_instance_by_assignmentkey_finds_the_owning_instance(): void {
        $instance = $this->repository->get_instance_by_assignmentkey($this->instance->assignmentkey);

        $this->assertSame((int) $this->instance->id, (int) $instance->id);
    }

    public function test_get_instance_by_assignmentkey_returns_null_when_unknown(): void {
        $this->assertNull($this->repository->get_instance_by_assignmentkey('nosuchkey'));
    }

    public function test_get_run_is_scoped_to_its_own_instance(): void {
        $otherinstance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $run = $this->create_run($this->student->id);

        $this->assertNotNull($this->repository->get_run($run->id, $this->instance->id));
        $this->assertNull($this->repository->get_run($run->id, $otherinstance->id));
        $this->assertNull($this->repository->get_run(0, $this->instance->id));
    }

    public function test_get_results_are_ordered_by_status_then_suite_then_name(): void {
        $run = $this->create_run($this->student->id, [
            ['testsuite' => 'A', 'testname' => 'passed', 'status' => 'PASSED'],
            ['testsuite' => 'B', 'testname' => 'skipped', 'status' => 'SKIPPED'],
            ['testsuite' => 'B', 'testname' => 'errorB', 'status' => 'ERROR'],
            ['testsuite' => 'A', 'testname' => 'errorA', 'status' => 'ERROR'],
            ['testsuite' => 'B', 'testname' => 'failedB2', 'status' => 'FAILED'],
            ['testsuite' => 'B', 'testname' => 'failedB1', 'status' => 'FAILED'],
            ['testsuite' => 'A', 'testname' => 'failedA', 'status' => 'FAILED'],
        ]);

        $results = array_values($this->repository->get_results($run->id));

        $this->assertSame(
            ['errorA', 'errorB', 'failedA', 'failedB1', 'failedB2', 'skipped', 'passed'],
            array_column($results, 'testname')
        );
    }

    public function test_get_results_put_a_result_without_a_suite_first_within_its_status(): void {
        $run = $this->create_run($this->student->id, [
            ['testsuite' => 'A', 'testname' => 'inSuite', 'status' => 'FAILED'],
            ['testname' => 'noSuite', 'status' => 'FAILED'],
        ]);

        $results = array_values($this->repository->get_results($run->id));

        $this->assertSame(['noSuite', 'inSuite'], array_column($results, 'testname'));
    }

    public function test_get_results_put_an_unknown_status_last(): void {
        global $DB;
        $run = $this->create_run($this->student->id, [
            ['testname' => 'legacy', 'status' => 'PASSED'],
            ['testname' => 'passed', 'status' => 'PASSED'],
        ]);
        $DB->set_field('idetestfeedback_result', 'status', 'OLD', ['runid' => $run->id, 'testname' => 'legacy']);

        $results = array_values($this->repository->get_results($run->id));

        $this->assertSame(['passed', 'legacy'], array_column($results, 'testname'));
    }

    public function test_get_files_are_returned_by_path(): void {
        $run = $this->create_run($this->student->id, overrides: ['files' => [
            ['path' => 'tests/test_zeta.py', 'content' => 'z'],
            ['path' => 'tests/test_alpha.py', 'content' => 'a'],
        ]]);

        $files = array_values($this->repository->get_files($run->id));

        $this->assertSame(['tests/test_alpha.py', 'tests/test_zeta.py'], array_column($files, 'path'));
        $this->assertSame(source_code::hash_canonical('a'), $files[0]->contenthash);
        $this->assertSame('a', $files[0]->content);
    }

    public function test_one_student_running_twice_stores_the_body_once(): void {
        global $DB;

        $first = $this->create_run($this->student->id, overrides: self::FILES);
        $second = $this->create_run($this->student->id, overrides: self::FILES);

        $this->assertSame(1, $this->blob_count($this->instance->id));
        $this->assertSame(
            $DB->get_field('idetestfeedback_file', 'blobid', ['runid' => $first->id]),
            $DB->get_field('idetestfeedback_file', 'blobid', ['runid' => $second->id])
        );
    }

    public function test_two_students_submitting_the_same_body_share_one_row(): void {
        $one = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $two = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->create_run($one->id, overrides: self::FILES);
        $this->create_run($two->id, overrides: self::FILES);

        $this->assertSame(1, $this->blob_count($this->instance->id));
    }

    public function test_the_same_body_in_two_activities_is_stored_once_each(): void {
        $other = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);

        $this->create_run($this->student->id, overrides: self::FILES);
        $this->create_run($this->student->id, overrides: array_merge(self::FILES, ['idetestfeedbackid' => $other->id]));

        $this->assertSame(1, $this->blob_count($this->instance->id));
        $this->assertSame(1, $this->blob_count($other->id));
    }

    public function test_a_body_outlives_the_run_that_stored_it_while_another_points_at_it(): void {
        $one = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $two = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->create_run($one->id, overrides: self::FILES);
        $this->create_run($two->id, overrides: self::FILES);

        $this->repository->delete_runs($this->instance->id, [$one->id]);
        $this->assertSame(1, $this->blob_count($this->instance->id));

        $this->repository->delete_runs($this->instance->id, [$two->id]);
        $this->assertSame(0, $this->blob_count($this->instance->id));
    }

    public function test_deleting_an_instance_reclaims_its_bodies(): void {
        $this->create_run($this->student->id, overrides: self::FILES);

        $this->repository->delete_instance($this->instance->id);

        $this->assertSame(0, $this->blob_count($this->instance->id));
    }

    public function test_the_sweep_leaves_another_activitys_bodies_alone(): void {
        $other = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);

        $this->create_run($this->student->id, overrides: self::FILES);
        $this->create_run($this->student->id, overrides: array_merge(self::FILES, ['idetestfeedbackid' => $other->id]));

        $this->repository->delete_runs($this->instance->id);

        $this->assertSame(0, $this->blob_count($this->instance->id));
        $this->assertSame(1, $this->blob_count($other->id));
    }

    public function test_get_files_is_empty_for_a_run_with_none(): void {
        $run = $this->create_run($this->student->id);

        $this->assertSame([], $this->repository->get_files($run->id));
    }

    public function test_get_feedback_authored_by_excludes_other_authors_and_instances(): void {
        $otherteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->generator->add_feedback($this->create_run($this->student->id), $this->teacher->id, 'Nice work');

        $mine = $this->repository->get_feedback_authored_by($this->instance->id, $this->teacher->id);
        $theirs = $this->repository->get_feedback_authored_by($this->instance->id, $otherteacher->id);

        $this->assertCount(1, $mine);
        $this->assertSame('Nice work', reset($mine)->feedback);
        $this->assertCount(0, $theirs);
    }

    public function test_get_user_brief_returns_id_and_name_fields(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Ann']);

        $brief = $this->repository->get_user_brief($student->id);

        $this->assertSame((int) $student->id, (int) $brief->id);
        $this->assertSame('Ann', $brief->firstname);
    }

    public function test_get_user_brief_returns_null_when_missing(): void {
        $this->assertNull($this->repository->get_user_brief(0));
    }

    public function test_get_runs_for_instance_filters_by_userid_and_status(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'FAILED']]);
        $this->create_run($other->id, [['testname' => 'a', 'status' => 'PASSED']]);

        $this->assertCount(3, $this->repository->get_runs_for_instance($this->instance->id));
        $this->assertCount(2, $this->repository->get_runs_for_instance($this->instance->id, userid: $this->student->id));
        $this->assertCount(2, $this->repository->get_runs_for_instance($this->instance->id, status: status::PASSED));
        $this->assertCount(1, $this->repository->get_runs_for_instance(
            $this->instance->id,
            userid: $this->student->id,
            status: status::FAILED
        ));
    }

    public function test_get_runs_for_instance_orders_newest_first_and_paginates(): void {
        $first = $this->create_run($this->student->id, overrides: ['timecreated' => 1000]);
        $second = $this->create_run($this->student->id, overrides: ['timecreated' => 2000]);

        $runs = array_values($this->repository->get_runs_for_instance($this->instance->id));
        $this->assertSame([(int) $second->id, (int) $first->id], array_map(fn($r) => (int) $r->id, $runs));

        $page = array_values($this->repository->get_runs_for_instance($this->instance->id, limitfrom: 1, limitnum: 1));
        $this->assertCount(1, $page);
        $this->assertSame((int) $first->id, (int) $page[0]->id);
    }

    public function test_get_runs_for_instance_carries_the_submitters_name(): void {
        $student = $this->getDataGenerator()->create_and_enrol(
            $this->course,
            'student',
            ['firstname' => 'Ann', 'lastname' => 'Example']
        );
        $this->create_run($student->id);

        $runs = $this->repository->get_runs_for_instance($this->instance->id);
        $run = reset($runs);

        $this->assertSame('Ann', $run->firstname);
        $this->assertSame('Example', $run->lastname);
    }

    public function test_count_runs_for_instance_matches_the_same_filters(): void {
        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'FAILED']]);

        $this->assertSame(2, $this->repository->count_runs_for_instance($this->instance->id));
        $this->assertSame(1, $this->repository->count_runs_for_instance($this->instance->id, status: status::PASSED));
    }

    public function test_get_students_with_runs_is_distinct_and_ordered_by_name(): void {
        $zed = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['lastname' => 'Zed']);
        $ann = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['lastname' => 'Ann']);
        $this->create_run($zed->id);
        $this->create_run($ann->id);
        $this->create_run($ann->id);

        $students = array_values($this->repository->get_students_with_runs($this->instance->id));

        $this->assertSame([(int) $ann->id, (int) $zed->id], array_map(fn($u) => (int) $u->id, $students));
    }

    public function test_run_list_queries_narrow_to_a_group(): void {
        $member = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $outsider = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        $this->create_run($member->id);
        $this->create_run($outsider->id);

        $runs = $this->repository->get_runs_for_instance($this->instance->id, groupid: (int) $group->id);
        $students = $this->repository->get_students_with_runs($this->instance->id, (int) $group->id);

        $this->assertSame([(int) $member->id], array_values(array_map(fn($r) => (int) $r->userid, $runs)));
        $this->assertSame(1, $this->repository->count_runs_for_instance($this->instance->id, groupid: (int) $group->id));
        $this->assertSame([(int) $member->id], array_map('intval', array_keys($students)));
        $this->assertCount(2, $this->repository->get_students_with_runs($this->instance->id));
    }

    public function test_get_runs_for_user_is_scoped_and_paginated(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->create_run($this->student->id, overrides: ['timecreated' => 1000]);
        $this->create_run($this->student->id, overrides: ['timecreated' => 2000]);
        $this->create_run($other->id);

        $this->assertCount(2, $this->repository->get_runs_for_user($this->instance->id, $this->student->id));
        $this->assertCount(1, $this->repository->get_runs_for_user($this->instance->id, $this->student->id, 0, 1));
    }

    public function test_get_pass_stats_counts_total_and_passed(): void {
        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'FAILED']]);
        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'PASSED']]);

        [$total, $passed] = $this->repository->get_pass_stats($this->instance->id, $this->student->id);

        $this->assertSame(3, $total);
        $this->assertSame(2, $passed);
    }

    public function test_get_pass_stats_for_a_student_with_no_runs(): void {

        $this->assertSame([0, 0], $this->repository->get_pass_stats($this->instance->id, $this->student->id));
    }

    public function test_has_fully_passing_run_requires_no_skips(): void {

        $this->assertFalse($this->repository->has_fully_passing_run($this->instance->id, $this->student->id));

        $this->create_run($this->student->id, [
            ['testname' => 'a', 'status' => 'PASSED'],
            ['testname' => 'b', 'status' => 'SKIPPED'],
        ]);
        $this->assertFalse($this->repository->has_fully_passing_run($this->instance->id, $this->student->id));

        $this->create_run($this->student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->assertTrue($this->repository->has_fully_passing_run($this->instance->id, $this->student->id));
    }

    public function test_get_active_users_by_email_matches_case_insensitively(): void {
        $user = $this->getDataGenerator()->create_user(['email' => 'student@example.com']);

        $found = $this->repository->get_active_users_by_email('STUDENT@example.com');

        $this->assertArrayHasKey($user->id, $found);
    }

    public function test_get_active_users_by_email_excludes_deleted_suspended_and_unconfirmed(): void {
        global $DB;

        $active = $this->getDataGenerator()->create_user(['email' => 'a@example.com']);
        $deleted = $this->getDataGenerator()->create_user(['email' => 'b@example.com']);
        $suspended = $this->getDataGenerator()->create_user(['email' => 'c@example.com', 'suspended' => 1]);
        $unconfirmed = $this->getDataGenerator()->create_user(['email' => 'd@example.com', 'confirmed' => 0]);
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);

        $this->assertArrayHasKey($active->id, $this->repository->get_active_users_by_email('a@example.com'));
        $this->assertSame([], $this->repository->get_active_users_by_email('b@example.com'));
        $this->assertSame([], $this->repository->get_active_users_by_email('c@example.com'));
        $this->assertSame([], $this->repository->get_active_users_by_email('d@example.com'));
    }

    public function test_get_active_users_by_email_can_return_more_than_one_match(): void {
        $one = $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);
        $two = $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);

        $found = $this->repository->get_active_users_by_email('shared@example.com');

        $this->assertCount(2, $found);
        $this->assertArrayHasKey($one->id, $found);
        $this->assertArrayHasKey($two->id, $found);
    }

    public function test_insert_run_fills_in_the_runid(): void {
        global $DB;

        $run = $this->run_row();
        $results = [(object) [
            'testname' => 'testAdd',
            'status' => status::PASSED->value,
        ]];

        $runid = $this->repository->insert_run($run, $results);

        $this->assertSame($runid, (int) $results[0]->runid);
        $this->assertSame(1, $DB->count_records('idetestfeedback_result', ['runid' => $runid]));
    }

    public function test_insert_run_links_no_blob_for_a_file_without_content(): void {
        global $DB;

        $run = $this->run_row();
        $results = [(object) ['testname' => 'testAdd', 'status' => status::PASSED->value]];
        $files = [
            (object) ['path' => 'tests/empty.py', 'content' => null],
            (object) ['path' => 'tests/test.py', 'content' => "x = 1\n"],
        ];

        $runid = $this->repository->insert_run($run, $results, $files);

        $blobids = $DB->get_records_menu('idetestfeedback_file', ['runid' => $runid], '', 'path, blobid');
        $this->assertNull($blobids['tests/empty.py']);
        $this->assertNotNull($blobids['tests/test.py']);
        $this->assertSame(1, $this->blob_count($this->instance->id));
    }

    public function test_insert_run_rolls_back_the_run_on_failure(): void {
        global $DB;

        // Without phpunit's own transaction, the repository's is outermost and its rollback takes effect.
        $this->preventResetByRollback();

        $run = $this->run_row();
        // A status wider than the column can hold fails the insert at the database level.
        $results = [(object) [
            'testname' => 'testAdd',
            'status' => str_repeat('X', 5000),
        ]];

        $before = $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id]);

        $thrown = null;
        try {
            $this->repository->insert_run($run, $results);
        } catch (\dml_exception $e) {
            $thrown = $e;
        }
        $this->assertInstanceOf(\dml_exception::class, $thrown);

        // The transaction must have rolled back the run insert too.
        $this->assertSame(
            $before,
            $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id])
        );
    }

    public function test_update_result_feedback_stores_and_clears(): void {
        $run = $this->create_run($this->student->id);
        [$result] = array_values($this->repository->get_results($run->id));

        $this->repository->update_result_feedback((int) $result->id, '  Great job  ', $this->teacher->id);
        [$updated] = array_values($this->repository->get_results($run->id));
        $this->assertSame('  Great job  ', $updated->feedback);
        $this->assertSame((int) $this->teacher->id, (int) $updated->feedbackby);
        $this->assertNotNull($updated->feedbackmodified);

        $this->repository->update_result_feedback((int) $result->id, '   ', $this->teacher->id);
        [$cleared] = array_values($this->repository->get_results($run->id));
        $this->assertNull($cleared->feedback);
        $this->assertNull($cleared->feedbackby);
        $this->assertNull($cleared->feedbackmodified);
    }

    public function test_delete_runs_removes_everything_for_the_instance_by_default(): void {
        global $DB;

        $run = $this->create_run($this->student->id, overrides: ['files' => [
            ['path' => 'tests/test_calculator.py', 'content' => 'x'],
        ]]);

        $this->repository->delete_runs($this->instance->id);

        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_result', ['runid' => $run->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_file', ['runid' => $run->id]));
    }

    public function test_delete_runs_can_be_limited_to_specific_users(): void {
        global $DB;

        $keep = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $remove = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $keptrun = $this->create_run($keep->id);
        $this->create_run($remove->id);

        $this->repository->delete_runs($this->instance->id, [$remove->id]);

        $this->assertSame(1, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id]));
        $this->assertTrue($DB->record_exists('idetestfeedback_run', ['id' => $keptrun->id]));
    }

    public function test_delete_runs_with_an_empty_user_list_does_nothing(): void {
        global $DB;

        $this->create_run($this->student->id);

        $this->repository->delete_runs($this->instance->id, []);

        $this->assertSame(1, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id]));
    }

    public function test_get_instance_ids_in_course(): void {
        $second = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $othercourse->id]);

        $ids = $this->repository->get_instance_ids_in_course($this->course->id);

        $this->assertEqualsCanonicalizing([(int) $this->instance->id, (int) $second->id], $ids);
    }

    public function test_delete_instance_removes_the_instance_and_its_runs(): void {
        global $DB;

        $run = $this->create_run($this->student->id, overrides: ['files' => [
            ['path' => 'tests/test_calculator.py', 'content' => 'x'],
        ]]);

        $this->repository->delete_instance($this->instance->id);

        $this->assertFalse($DB->record_exists('idetestfeedback', ['id' => $this->instance->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['id' => $run->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_file', ['runid' => $run->id]));
    }

    public function test_anonymise_feedback_authors_detaches_only_the_given_users(): void {
        $keepauthor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $removeauthor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $run = $this->create_run($this->student->id, [
            ['testname' => 'a', 'status' => 'PASSED'],
            ['testname' => 'b', 'status' => 'PASSED'],
        ]);
        [$resulta, $resultb] = array_values($this->repository->get_results($run->id));
        $this->repository->update_result_feedback((int) $resulta->id, 'kept', $keepauthor->id);
        $this->repository->update_result_feedback((int) $resultb->id, 'removed', $removeauthor->id);

        $this->repository->anonymise_feedback_authors($this->instance->id, [$removeauthor->id]);

        [$resulta, $resultb] = array_values($this->repository->get_results($run->id));
        $this->assertSame((int) $keepauthor->id, (int) $resulta->feedbackby);
        $this->assertSame('kept', $resulta->feedback);
        $this->assertNull($resultb->feedbackby);
        $this->assertSame('removed', $resultb->feedback);
    }

    public function test_anonymise_feedback_authors_with_an_empty_user_list_does_nothing(): void {
        $run = $this->create_run($this->student->id);
        $this->generator->add_feedback($run, $this->teacher->id, 'kept');

        $this->repository->anonymise_feedback_authors($this->instance->id, []);

        [$result] = array_values($this->repository->get_results($run->id));
        $this->assertSame((int) $this->teacher->id, (int) $result->feedbackby);
    }

    /**
     * Stores a run of the student's with a single failing test.
     *
     * @param string $testname the test that failed
     * @param int $timecreated when the run was submitted
     * @return \stdClass the stored run
     */
    private function failing_run(string $testname, int $timecreated): \stdClass {
        $results = [['testname' => $testname, 'status' => 'FAILED']];

        return $this->create_run($this->student->id, $results, ['timecreated' => $timecreated]);
    }

    public function test_get_feedback_history_returns_commented_earlier_runs_newest_first(): void {
        $older = $this->failing_run('testOlder', 100);
        $this->failing_run('testUncommented', 150);
        $newer = $this->create_run($this->student->id, [
            ['testname' => 'testNewerA', 'status' => 'PASSED'],
            ['testname' => 'testNewerB', 'status' => 'ERROR'],
        ], ['timecreated' => 200]);
        $current = $this->create_run($this->student->id, overrides: ['timecreated' => 300]);
        $later = $this->failing_run('testLater', 400);
        $this->generator->add_feedback($older, $this->teacher->id, modified: 110);
        $this->generator->add_feedback($newer, $this->teacher->id, modified: 210);
        $this->generator->add_feedback($later, $this->teacher->id, modified: 410);

        $rows = array_values($this->repository->get_feedback_history((int) $current->id));

        $this->assertSame(['testNewerA', 'testNewerB', 'testOlder'], array_column($rows, 'testname'));
        $this->assertSame(['PASSED', 'ERROR', 'FAILED'], array_column($rows, 'status'));
        $this->assertFalse(property_exists($rows[0], 'feedback'));
    }

    public function test_get_feedback_history_breaks_ties_on_the_same_second_by_id(): void {
        $first = $this->failing_run('testFirst', 100);
        $second = $this->failing_run('testSecond', 100);
        $third = $this->failing_run('testThird', 100);
        $this->generator->add_feedback($first, $this->teacher->id, modified: 50);
        $this->generator->add_feedback($second, $this->teacher->id, modified: 50);
        $this->generator->add_feedback($third, $this->teacher->id, modified: 50);

        $rows = array_values($this->repository->get_feedback_history((int) $second->id));

        $this->assertSame(['testFirst'], array_column($rows, 'testname'));
    }

    public function test_get_feedback_history_ignores_feedback_written_after_the_run(): void {
        $commented = $this->failing_run('testAdd', 100);
        $current = $this->create_run($this->student->id, overrides: ['timecreated' => 200]);
        $this->generator->add_feedback($commented, $this->teacher->id, modified: 250);

        $this->assertSame([], $this->repository->get_feedback_history((int) $current->id));
    }

    public function test_get_feedback_history_ignores_cleared_feedback(): void {
        $commented = $this->failing_run('testAdd', 100);
        $current = $this->create_run($this->student->id, overrides: ['timecreated' => 200]);
        [$result] = array_values($this->repository->get_results((int) $commented->id));
        $this->repository->update_result_feedback((int) $result->id, '', $this->teacher->id);

        $this->assertSame([], $this->repository->get_feedback_history((int) $current->id));
    }

    public function test_get_feedback_history_is_scoped_to_the_user_and_instance(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $otherinstance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $otherstudents = $this->create_run($other->id, overrides: ['timecreated' => 100]);
        $otheractivitys = $this->create_run($this->student->id, overrides: [
            'idetestfeedbackid' => $otherinstance->id,
            'timecreated' => 100,
        ]);
        $this->generator->add_feedback($otherstudents, $this->teacher->id, modified: 110);
        $this->generator->add_feedback($otheractivitys, $this->teacher->id, modified: 110);
        $current = $this->create_run($this->student->id, overrides: ['timecreated' => 200]);

        $this->assertSame([], $this->repository->get_feedback_history((int) $current->id));
        $this->assertSame([], $this->repository->get_feedback_history(0));
    }

    public function test_find_or_create_blob_stores_one_canonical_body_per_activity(): void {
        global $DB;

        $first = $this->repository->find_or_create_blob($this->instance->id, "x = 1  \r\n", time());
        $second = $this->repository->find_or_create_blob($this->instance->id, "x = 1\n", time());

        $this->assertSame($first, $second);
        $blob = $DB->get_record('idetestfeedback_blob', ['id' => $first]);
        $this->assertSame('x = 1', $blob->content);
        $this->assertSame(source_code::hash_canonical('x = 1'), $blob->contenthash);
    }

    public function test_a_blob_is_hashed_over_exactly_the_content_it_stores(): void {
        global $DB;

        $marker = "\u{2026} [truncated]";
        $id = $this->repository->find_or_create_blob($this->instance->id, "x = 1\n{$marker}\n{$marker}", time());

        $blob = $DB->get_record('idetestfeedback_blob', ['id' => $id]);
        $this->assertSame("x = 1\n{$marker}", $blob->content);
        $this->assertSame(hash('sha256', $blob->content), $blob->contenthash);
    }

    public function test_find_or_create_blob_stores_nothing_for_an_empty_body(): void {
        $this->assertNull($this->repository->find_or_create_blob($this->instance->id, " \n\n", time()));
        $this->assertSame(0, $this->blob_count($this->instance->id));
    }
}
