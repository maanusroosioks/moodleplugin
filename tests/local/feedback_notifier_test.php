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
 * Tests for the "feedback is waiting" student notification.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\feedback_notifier::class)]
final class feedback_notifier_test extends \advanced_testcase {
    /** @var \cm_info */
    private \cm_info $cm;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $teacher;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        [, $this->cm] = get_course_and_cm_from_instance($instance->id, 'idetestfeedback');
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
    }

    /**
     * Stores a one-test run for the student and notifies them of feedback on it.
     *
     * @param array $testresult the run's only test case result
     * @param string $feedback the feedback the teacher wrote on it
     * @return \stdClass the one message sent
     */
    private function notify(array $testresult, string $feedback): \stdClass {
        global $DB;

        $sink = $this->redirectMessages();
        $run = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $this->cm->instance,
            'userid' => $this->student->id,
            'results' => [$testresult],
        ]);
        [$result] = array_values((new repository($DB))->get_results($run->id));
        $result->feedback = $feedback;

        $this->assertTrue((new feedback_notifier($this->cm))->notify($run, [$result], $this->teacher));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);

        return reset($messages);
    }

    public function test_notify_sends_a_message_naming_the_run_owner_and_feedback(): void {
        $message = $this->notify(['testname' => 'testAdd', 'status' => 'FAILED'], 'Check your edge cases');

        $this->assertSame((int) $this->student->id, (int) $message->useridto);
        $this->assertSame((int) $this->teacher->id, (int) $message->useridfrom);
        $this->assertStringContainsString('Check your edge cases', $message->fullmessage);
        $this->assertStringContainsString('testAdd', $message->fullmessage);
    }

    public function test_notify_labels_a_result_with_its_suite(): void {
        $message = $this->notify(['testname' => 'testAdd', 'testsuite' => 'CalcTest', 'status' => 'FAILED'], 'See above');

        $this->assertStringContainsString('CalcTest#testAdd', $message->fullmessage);
    }
}
