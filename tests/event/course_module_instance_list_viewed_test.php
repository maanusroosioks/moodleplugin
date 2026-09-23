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
 * Tests for the "course module instance list viewed" event.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\event\course_module_instance_list_viewed::class)]
final class course_module_instance_list_viewed_test extends \advanced_testcase {
    /**
     * The event can be created and triggered in a course context.
     */
    public function test_it_can_be_triggered_and_captured(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);

        $sink = $this->redirectEvents();

        course_module_instance_list_viewed::create(['context' => $context])->trigger();

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(course_module_instance_list_viewed::class, $event);
        $this->assertSame((int) $context->id, (int) $event->contextid);
    }
}
