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

namespace mod_idetestfeedback;

use cm_info;
use mod_idetestfeedback\completion\custom_completion;

/**
 * Tests for the "passing test run" completion rule.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\completion\custom_completion
 */
final class custom_completion_test extends \advanced_testcase {
    /**
     * Creates an activity with the rule switched on, plus an enrolled student.
     *
     * @param string $requiredtests the defined test case list
     * @return array{0:cm_info,1:int} [the course module, the student's id]
     */
    private function setup_activity(string $requiredtests = ''): array {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');

        $instance = $generator->create_module('idetestfeedback', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpassrun' => 1,
            'requiredtests' => $requiredtests,
        ]);

        return [cm_info::create(get_coursemodule_from_instance('idetestfeedback', $instance->id)), $student->id];
    }

    /**
     * The completion state of the custom rule for a student.
     *
     * @param cm_info $cm the activity
     * @param int $userid the student
     * @return int the rule's state for that student
     */
    private function state(cm_info $cm, int $userid): int {
        return (new custom_completion($cm, $userid))->get_state('completionpassrun');
    }

    /**
     * Stores a run for the student.
     *
     * @param cm_info $cm the activity
     * @param int $userid the student
     * @param array $results the test case results to record
     */
    private function submit(cm_info $cm, int $userid, array $results): void {
        $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $cm->instance,
            'userid' => $userid,
            'results' => $results,
        ]);
    }

    public function test_incomplete_without_any_run(): void {
        [$cm, $userid] = $this->setup_activity();

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($cm, $userid));
    }

    public function test_no_defined_tests_completes_on_an_all_passing_run(): void {
        [$cm, $userid] = $this->setup_activity();

        $this->submit($cm, $userid, [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'PASSED'],
        ]);

        $this->assertSame(COMPLETION_COMPLETE, $this->state($cm, $userid));
    }

    public function test_no_defined_tests_stays_incomplete_when_a_test_was_skipped(): void {
        [$cm, $userid] = $this->setup_activity();

        // The run's overall status is PASSED, because nothing failed. It still
        // does not complete the activity: the skipped test was never proven.
        $this->submit($cm, $userid, [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'SKIPPED'],
        ]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($cm, $userid));
    }

    public function test_no_defined_tests_stays_incomplete_when_a_test_failed(): void {
        [$cm, $userid] = $this->setup_activity();

        $this->submit($cm, $userid, [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'FAILED'],
        ]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($cm, $userid));
    }

    public function test_defined_tests_ignore_extra_tests_that_did_not_pass(): void {
        [$cm, $userid] = $this->setup_activity("testAdd\ntestSub");

        $this->submit($cm, $userid, [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'PASSED'],
            ['testname' => 'testExperimental', 'status' => 'FAILED'],
        ]);

        $this->assertSame(COMPLETION_COMPLETE, $this->state($cm, $userid));
    }

    public function test_defined_tests_require_every_one_of_them(): void {
        [$cm, $userid] = $this->setup_activity("testAdd\ntestSub");

        $this->submit($cm, $userid, [['testname' => 'testAdd', 'status' => 'PASSED']]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($cm, $userid));
    }

    public function test_any_earlier_run_can_satisfy_the_rule(): void {
        [$cm, $userid] = $this->setup_activity("testAdd");

        $this->submit($cm, $userid, [['testname' => 'testAdd', 'status' => 'PASSED']]);
        $this->submit($cm, $userid, [['testname' => 'testAdd', 'status' => 'FAILED']]);

        // The rule asks whether the student ever passed, not whether they still do.
        $this->assertSame(COMPLETION_COMPLETE, $this->state($cm, $userid));
    }

    public function test_another_students_run_does_not_complete_the_activity(): void {
        [$cm, $userid] = $this->setup_activity();
        $other = $this->getDataGenerator()->create_and_enrol(
            get_course($cm->course),
            'student'
        );

        $this->submit($cm, $other->id, [['testname' => 'testAdd', 'status' => 'PASSED']]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($cm, $userid));
        $this->assertSame(COMPLETION_COMPLETE, $this->state($cm, $other->id));
    }
}
