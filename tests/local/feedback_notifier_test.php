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
    public function test_notify_sends_a_message_naming_the_run_owner_and_feedback(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        [, $cm] = get_course_and_cm_from_instance($instance->id, 'idetestfeedback');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $run = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'results' => [['testname' => 'testAdd', 'status' => 'FAILED']],
        ]);
        $repository = new repository($DB);
        [$result] = array_values($repository->get_results($run->id));
        $result->feedback = 'Check your edge cases';

        $notifier = new feedback_notifier($cm);
        $sent = $notifier->notify($run, [$result], $teacher);

        $this->assertTrue($sent);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame((int) $student->id, (int) $message->useridto);
        $this->assertSame((int) $teacher->id, (int) $message->useridfrom);
        $this->assertStringContainsString('Check your edge cases', $message->fullmessage);
        $this->assertStringContainsString('testAdd', $message->fullmessage);
    }

    public function test_notify_labels_a_result_with_its_suite(): void {
        $this->resetAfterTest();
        $sink = $this->redirectMessages();

        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        [, $cm] = get_course_and_cm_from_instance($instance->id, 'idetestfeedback');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $run = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'results' => [['testname' => 'testAdd', 'testsuite' => 'CalcTest', 'status' => 'FAILED']],
        ]);
        $repository = new repository($DB);
        [$result] = array_values($repository->get_results($run->id));
        $result->feedback = 'See above';

        $notifier = new feedback_notifier($cm);
        $notifier->notify($run, [$result], $teacher);

        $messages = $sink->get_messages();
        $message = reset($messages);
        $this->assertStringContainsString('CalcTest#testAdd', $message->fullmessage);
    }
}
