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
 * @covers     \mod_idetestfeedback\local\repository
 */
final class repository_test extends \advanced_testcase {

    /** @var repository */
    private repository $repository;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $instance;

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
    }

    /**
     * @param int $userid the student to attribute the run to
     * @param array $results the test case results, defaults to a single passing test
     * @param array $overrides extra run fields, e.g. 'status' or 'timecreated'
     * @return \stdClass the stored run
     */
    private function create_run(int $userid, array $results = [['testname' => 'testAdd', 'status' => 'PASSED']],
                                array $overrides = []): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run(array_merge([
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $userid,
            'results' => $results,
        ], $overrides));
    }

    public function test_get_course_returns_the_course(): void {
        $course = $this->repository->get_course($this->course->id);

        $this->assertSame((int) $this->course->id, (int) $course->id);
    }

    public function test_get_course_throws_when_missing(): void {
        $this->expectException(\dml_missing_record_exception::class);

        $this->repository->get_course(0);
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
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $run = $this->create_run($student->id);

        $this->assertNotNull($this->repository->get_run($run->id, $this->instance->id));
        $this->assertNull($this->repository->get_run($run->id, $otherinstance->id));
        $this->assertNull($this->repository->get_run(0, $this->instance->id));
    }

    public function test_get_results_are_returned_in_insertion_order(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $run = $this->create_run($student->id, [
            ['testname' => 'testFirst', 'status' => 'PASSED'],
            ['testname' => 'testSecond', 'status' => 'FAILED'],
            ['testname' => 'testThird', 'status' => 'SKIPPED'],
        ]);

        $results = array_values($this->repository->get_results($run->id));

        $this->assertSame(['testFirst', 'testSecond', 'testThird'], array_column($results, 'testname'));
    }

    public function test_get_run_ids_for_user_are_newest_first_and_scoped_to_the_user(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $first = $this->create_run($student->id, overrides: ['timecreated' => 1000]);
        $second = $this->create_run($student->id, overrides: ['timecreated' => 2000]);
        $this->create_run($other->id, overrides: ['timecreated' => 3000]);

        $this->assertSame(
            [(int) $second->id, (int) $first->id],
            $this->repository->get_run_ids_for_user($this->instance->id, $student->id)
        );
    }

    public function test_get_feedback_authored_by_excludes_other_authors_and_instances(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $otherteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $run = $this->create_run($student->id);
        [$result] = array_values($this->repository->get_results($run->id));

        $this->repository->update_result_feedback((int) $result->id, 'Nice work', FORMAT_PLAIN, $teacher->id);

        $mine = $this->repository->get_feedback_authored_by($this->instance->id, $teacher->id);
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
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->create_run($student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->create_run($student->id, [['testname' => 'a', 'status' => 'FAILED']]);
        $this->create_run($other->id, [['testname' => 'a', 'status' => 'PASSED']]);

        $this->assertCount(3, $this->repository->get_runs_for_instance($this->instance->id));
        $this->assertCount(2, $this->repository->get_runs_for_instance($this->instance->id, ['userid' => $student->id]));
        $this->assertCount(2, $this->repository->get_runs_for_instance($this->instance->id, ['status' => 'PASSED']));
        $this->assertCount(1, $this->repository->get_runs_for_instance(
            $this->instance->id,
            ['userid' => $student->id, 'status' => 'FAILED']
        ));
    }

    public function test_get_runs_for_instance_orders_newest_first_and_paginates(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $first = $this->create_run($student->id, overrides: ['timecreated' => 1000]);
        $second = $this->create_run($student->id, overrides: ['timecreated' => 2000]);

        $runs = array_values($this->repository->get_runs_for_instance($this->instance->id));
        $this->assertSame([(int) $second->id, (int) $first->id], array_map(fn($r) => (int) $r->id, $runs));

        $page = array_values($this->repository->get_runs_for_instance($this->instance->id, [], 1, 1));
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
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->create_run($student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->create_run($student->id, [['testname' => 'a', 'status' => 'FAILED']]);

        $this->assertSame(2, $this->repository->count_runs_for_instance($this->instance->id));
        $this->assertSame(1, $this->repository->count_runs_for_instance($this->instance->id, ['status' => 'PASSED']));
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

    public function test_get_runs_for_user_is_scoped_and_paginated(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->create_run($student->id, overrides: ['timecreated' => 1000]);
        $this->create_run($student->id, overrides: ['timecreated' => 2000]);
        $this->create_run($other->id);

        $this->assertCount(2, $this->repository->get_runs_for_user($this->instance->id, $student->id));
        $this->assertCount(1, $this->repository->get_runs_for_user($this->instance->id, $student->id, 0, 1));
    }

    public function test_get_pass_stats_counts_total_and_passed(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->create_run($student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->create_run($student->id, [['testname' => 'a', 'status' => 'FAILED']]);
        $this->create_run($student->id, [['testname' => 'a', 'status' => 'PASSED']]);

        [$total, $passed] = $this->repository->get_pass_stats($this->instance->id, $student->id);

        $this->assertSame(3, $total);
        $this->assertSame(2, $passed);
    }

    public function test_get_pass_stats_for_a_student_with_no_runs(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->assertSame([0, 0], $this->repository->get_pass_stats($this->instance->id, $student->id));
    }

    public function test_has_fully_passing_run_requires_no_skips(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->assertFalse($this->repository->has_fully_passing_run($this->instance->id, $student->id));

        $this->create_run($student->id, [
            ['testname' => 'a', 'status' => 'PASSED'],
            ['testname' => 'b', 'status' => 'SKIPPED'],
        ]);
        $this->assertFalse($this->repository->has_fully_passing_run($this->instance->id, $student->id));

        $this->create_run($student->id, [['testname' => 'a', 'status' => 'PASSED']]);
        $this->assertTrue($this->repository->has_fully_passing_run($this->instance->id, $student->id));
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

    public function test_insert_run_with_results_fills_in_the_runid(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $run = (object) [
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $student->id,
            'ide' => 'VSCODE',
            'status' => status::PASSED->value,
            'passedcount' => 1,
            'failedcount' => 0,
            'skippedcount' => 0,
            'errorcount' => 0,
            'timecreated' => time(),
        ];
        $results = [(object) [
            'testname' => 'testAdd',
            'status' => status::PASSED->value,
            'timecreated' => time(),
        ]];

        $runid = $this->repository->insert_run_with_results($run, $results);

        $this->assertSame($runid, (int) $results[0]->runid);
        $this->assertSame(1, $DB->count_records('idetestfeedback_result', ['runid' => $runid]));
    }

    public function test_insert_run_with_results_rolls_back_the_run_on_failure(): void {
        global $DB;

        // Moodle nests delegated transactions inside phpunit's own test transaction, so a
        // rolled-back nested transaction only becomes visible once the outer one ends too.
        // Ending the test transaction here makes the repository's transaction the real
        // outermost one, so the rollback below takes effect immediately.
        $this->preventResetByRollback();

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $run = (object) [
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $student->id,
            'ide' => 'VSCODE',
            'status' => status::PASSED->value,
            'passedcount' => 1,
            'failedcount' => 0,
            'skippedcount' => 0,
            'errorcount' => 0,
            'timecreated' => time(),
        ];
        // A status wider than the column can hold fails the insert at the database level.
        $results = [(object) [
            'testname' => 'testAdd',
            'status' => str_repeat('X', 5000),
            'timecreated' => time(),
        ]];

        $before = $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id]);

        try {
            $this->repository->insert_run_with_results($run, $results);
            $this->fail('Expected the oversized result row to raise an exception.');
        } catch (\Throwable $e) {
            // Expected; the transaction must have rolled back the run insert too.
        }

        $this->assertSame(
            $before,
            $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id])
        );
    }

    public function test_update_result_feedback_stores_and_clears(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $run = $this->create_run($student->id);
        [$result] = array_values($this->repository->get_results($run->id));

        $this->repository->update_result_feedback((int) $result->id, '  Great job  ', FORMAT_PLAIN, $teacher->id);
        [$updated] = array_values($this->repository->get_results($run->id));
        $this->assertSame('  Great job  ', $updated->feedback);
        $this->assertSame((int) $teacher->id, (int) $updated->feedbackby);
        $this->assertNotNull($updated->feedbackmodified);

        $this->repository->update_result_feedback((int) $result->id, '   ', FORMAT_PLAIN, $teacher->id);
        [$cleared] = array_values($this->repository->get_results($run->id));
        $this->assertNull($cleared->feedback);
        $this->assertNull($cleared->feedbackby);
        $this->assertNull($cleared->feedbackmodified);
    }

    public function test_delete_runs_removes_everything_for_the_instance_by_default(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $run = $this->create_run($student->id);

        $this->repository->delete_runs($this->instance->id);

        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_result', ['runid' => $run->id]));
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

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->create_run($student->id);

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

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $run = $this->create_run($student->id);

        $this->repository->delete_instance($this->instance->id);

        $this->assertFalse($DB->record_exists('idetestfeedback', ['id' => $this->instance->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['id' => $run->id]));
    }

    public function test_anonymise_feedback_authors_detaches_only_the_given_users(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $keepauthor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $removeauthor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $run = $this->create_run($student->id, [
            ['testname' => 'a', 'status' => 'PASSED'],
            ['testname' => 'b', 'status' => 'PASSED'],
        ]);
        [$resulta, $resultb] = array_values($this->repository->get_results($run->id));
        $this->repository->update_result_feedback((int) $resulta->id, 'kept', FORMAT_PLAIN, $keepauthor->id);
        $this->repository->update_result_feedback((int) $resultb->id, 'removed', FORMAT_PLAIN, $removeauthor->id);

        $this->repository->anonymise_feedback_authors($this->instance->id, [$removeauthor->id]);

        [$resulta, $resultb] = array_values($this->repository->get_results($run->id));
        $this->assertSame((int) $keepauthor->id, (int) $resulta->feedbackby);
        $this->assertSame('kept', $resulta->feedback);
        $this->assertNull($resultb->feedbackby);
        $this->assertSame('removed', $resultb->feedback);
    }

    public function test_anonymise_feedback_authors_with_an_empty_user_list_does_nothing(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $run = $this->create_run($student->id);
        [$result] = array_values($this->repository->get_results($run->id));
        $this->repository->update_result_feedback((int) $result->id, 'kept', FORMAT_PLAIN, $teacher->id);

        $this->repository->anonymise_feedback_authors($this->instance->id, []);

        [$result] = array_values($this->repository->get_results($run->id));
        $this->assertSame((int) $teacher->id, (int) $result->feedbackby);
    }
}
