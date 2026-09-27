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
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\completion\custom_completion::class)]
final class custom_completion_test extends \advanced_testcase {
    /** @var cm_info The activity, with the rule switched on */
    private cm_info $cm;

    /** @var int The enrolled student */
    private int $userid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $this->userid = (int) $generator->create_and_enrol($course, 'student')->id;

        $instance = $generator->create_module('idetestfeedback', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpassrun' => 1,
        ]);
        $this->cm = cm_info::create(get_coursemodule_from_instance('idetestfeedback', $instance->id));
    }

    /**
     * The completion state of the custom rule for a student.
     *
     * @param int $userid the student
     * @return int the rule's state for that student
     */
    private function state(int $userid): int {
        return (new custom_completion($this->cm, $userid))->get_state('completionpassrun');
    }

    /**
     * Stores a run for a student.
     *
     * @param int $userid the student
     * @param array $results the test case results to record
     */
    private function submit(int $userid, array $results): void {
        $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $this->cm->instance,
            'userid' => $userid,
            'results' => $results,
        ]);
    }

    public function test_incomplete_without_any_run(): void {
        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($this->userid));
    }

    public function test_completes_on_an_all_passing_run(): void {
        $this->submit($this->userid, [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'PASSED'],
        ]);

        $this->assertSame(COMPLETION_COMPLETE, $this->state($this->userid));
    }

    public function test_stays_incomplete_when_a_test_was_skipped(): void {
        // The run's overall status is PASSED, because nothing failed. It still
        // does not complete the activity: the skipped test was never proven.
        $this->submit($this->userid, [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'SKIPPED'],
        ]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($this->userid));
    }

    public function test_stays_incomplete_when_a_test_failed(): void {
        $this->submit($this->userid, [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'FAILED'],
        ]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($this->userid));
    }

    public function test_any_earlier_run_can_satisfy_the_rule(): void {
        $this->submit($this->userid, [['testname' => 'testAdd', 'status' => 'PASSED']]);
        $this->submit($this->userid, [['testname' => 'testAdd', 'status' => 'FAILED']]);

        // The rule asks whether the student ever passed, not whether they still do.
        $this->assertSame(COMPLETION_COMPLETE, $this->state($this->userid));
    }

    public function test_another_students_run_does_not_complete_the_activity(): void {
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->cm->course), 'student');

        $this->submit($other->id, [['testname' => 'testAdd', 'status' => 'PASSED']]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->state($this->userid));
        $this->assertSame(COMPLETION_COMPLETE, $this->state($other->id));
    }
}
