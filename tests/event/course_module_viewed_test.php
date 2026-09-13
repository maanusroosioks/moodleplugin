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

namespace mod_idetestfeedback\event;

/**
 * Tests for the "course module viewed" event.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\event\course_module_viewed
 */
final class course_module_viewed_test extends \advanced_testcase {

    public function test_it_can_be_triggered_and_captured(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('idetestfeedback', $instance->id);
        $context = \context_module::instance($cm->id);

        $sink = $this->redirectEvents();

        course_module_viewed::create([
            'objectid' => $instance->id,
            'context' => $context,
        ])->trigger();

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(course_module_viewed::class, $event);
        $this->assertSame((int) $instance->id, (int) $event->objectid);
        $this->assertSame((int) $context->id, (int) $event->contextid);
    }
}
