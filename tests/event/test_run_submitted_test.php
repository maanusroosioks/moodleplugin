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
 * Tests for the "test run submitted" event.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\event\test_run_submitted::class)]
final class test_run_submitted_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $instance;

    /** @var \context_module */
    private \context_module $context;

    /** @var \stdClass */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $this->context = \context_module::instance($this->instance->cmid);
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
    }

    /**
     * Creates the event, with valid defaults.
     *
     * @param array $overrides event data to override the valid defaults with
     * @param string[] $without keys to leave out of the event data
     * @return test_run_submitted
     */
    private function create_event(array $overrides = [], array $without = []): test_run_submitted {
        $data = array_merge([
            'objectid' => 42,
            'context' => $this->context,
            'relateduserid' => $this->student->id,
            'other' => ['status' => 'FAILED'],
        ], $overrides);

        return test_run_submitted::create(array_diff_key($data, array_flip($without)));
    }

    public function test_it_requires_relateduserid(): void {
        $this->expectException(\coding_exception::class);

        $this->create_event(without: ['relateduserid']);
    }

    public function test_it_requires_a_status_in_other(): void {
        $this->expectException(\coding_exception::class);

        $this->create_event(without: ['other']);
    }

    public function test_get_url_points_at_the_run_detail_page(): void {
        $event = $this->create_event(['objectid' => 99]);

        $url = $event->get_url();

        $this->assertSame((int) $this->context->instanceid, (int) $url->get_param('id'));
        $this->assertSame(99, (int) $url->get_param('runid'));
    }

    public function test_get_description_names_the_actor_run_and_activity(): void {
        $this->setAdminUser();
        $event = $this->create_event(['objectid' => 7, 'other' => ['status' => 'PASSED']]);

        $description = $event->get_description();

        $this->assertStringContainsString("'7'", $description);
        $this->assertStringContainsString('PASSED', $description);
        $this->assertStringContainsString((string) $this->student->id, $description);
    }

    public function test_it_can_be_triggered_and_captured(): void {
        $sink = $this->redirectEvents();

        $this->create_event()->trigger();

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(test_run_submitted::class, reset($events));
    }
}
