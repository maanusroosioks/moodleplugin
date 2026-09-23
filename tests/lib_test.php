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

use advanced_testcase;
use cached_cm_info;
use completion_info;
use stdClass;

/**
 * Tests for the activity module callbacks in lib.php.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_add_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_update_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_update_completion_date_event')]
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_supports')]
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_get_coursemodule_info')]
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_reset_userdata')]
#[\PHPUnit\Framework\Attributes\CoversFunction('idetestfeedback_view')]
final class lib_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Builds mod_form data for a new instance.
     *
     * @param array $overrides mod_form-shaped fields to override the defaults with
     * @return stdClass the data as idetestfeedback_add_instance() would receive it
     */
    private function form_data(array $overrides = []): stdClass {
        $course = $this->getDataGenerator()->create_course();

        return (object) array_merge([
            'course' => $course->id,
            'coursemodule' => 0,
            'name' => 'A test activity',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'completionpassrun' => 0,
            'timeopen' => null,
            'timeclose' => null,
        ], $overrides);
    }

    public function test_add_instance_generates_a_32_character_hex_assignment_key(): void {
        global $DB;

        $id = idetestfeedback_add_instance($this->form_data());

        $key = $DB->get_field('idetestfeedback', 'assignmentkey', ['id' => $id]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
    }

    public function test_add_instance_generates_distinct_keys(): void {
        $data = $this->form_data();

        $first = idetestfeedback_add_instance(clone $data);
        $second = idetestfeedback_add_instance(clone $data);

        global $DB;
        $this->assertNotSame(
            $DB->get_field('idetestfeedback', 'assignmentkey', ['id' => $first]),
            $DB->get_field('idetestfeedback', 'assignmentkey', ['id' => $second])
        );
    }

    public function test_add_instance_defaults_null_timeopen_and_timeclose_to_zero(): void {
        global $DB;

        $id = idetestfeedback_add_instance($this->form_data());

        $instance = $DB->get_record('idetestfeedback', ['id' => $id]);
        $this->assertSame(0, (int) $instance->timeopen);
        $this->assertSame(0, (int) $instance->timeclose);
    }

    public function test_update_instance_keeps_the_assignment_key(): void {
        global $DB;

        $id = idetestfeedback_add_instance($this->form_data());
        $originalkey = $DB->get_field('idetestfeedback', 'assignmentkey', ['id' => $id]);

        $update = $this->form_data(['name' => 'Renamed']);
        $update->instance = $id;
        idetestfeedback_update_instance($update);

        $instance = $DB->get_record('idetestfeedback', ['id' => $id]);
        $this->assertSame($originalkey, $instance->assignmentkey);
        $this->assertSame('Renamed', $instance->name);
    }

    public function test_expected_completion_date_is_put_on_the_calendar_and_taken_off(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $expected = time() + DAYSECS;
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionexpected' => $expected,
        ]);
        $conditions = [
            'modulename' => 'idetestfeedback',
            'instance' => $instance->id,
            'eventtype' => \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED,
        ];

        $this->assertEquals($expected, $DB->get_field('event', 'timestart', $conditions, MUST_EXIST));

        $update = $this->form_data(['coursemodule' => $instance->cmid, 'course' => $course->id]);
        $update->instance = $instance->id;
        idetestfeedback_update_instance($update);

        $this->assertFalse($DB->record_exists('event', $conditions));
    }

    public function test_delete_instance_removes_the_instance_and_its_runs(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $run = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'results' => [['testname' => 'testAdd', 'status' => 'PASSED']],
        ]);

        $this->assertTrue(idetestfeedback_delete_instance($instance->id));

        $this->assertFalse($DB->record_exists('idetestfeedback', ['id' => $instance->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['id' => $run->id]));
    }

    /**
     * Data provider for test_supports().
     *
     * @return array[] [feature constant, expected value]
     */
    public static function supports_provider(): array {
        return [
            'mod_intro' => [FEATURE_MOD_INTRO, true],
            'show_description' => [FEATURE_SHOW_DESCRIPTION, true],
            'backup_moodle2' => [FEATURE_BACKUP_MOODLE2, true],
            'completion_tracks_views' => [FEATURE_COMPLETION_TRACKS_VIEWS, true],
            'completion_has_rules' => [FEATURE_COMPLETION_HAS_RULES, true],
            'mod_purpose' => [FEATURE_MOD_PURPOSE, MOD_PURPOSE_ASSESSMENT],
            'groups' => [FEATURE_GROUPS, true],
            'groupings' => [FEATURE_GROUPINGS, true],
            'unknown feature' => ['some_unknown_feature', null],
        ];
    }

    /**
     * The module reports the features it supports.
     *
     * @param string $feature a FEATURE_* constant, or an unknown string
     * @param string|bool|null $expected the expected support value
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('supports_provider')]
    public function test_supports(string $feature, string|bool|null $expected): void {
        $this->assertSame($expected, idetestfeedback_supports($feature));
    }

    public function test_get_coursemodule_info_returns_false_when_the_instance_is_gone(): void {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('idetestfeedback', $instance->id);

        global $DB;
        $DB->delete_records('idetestfeedback', ['id' => $instance->id]);

        $this->assertFalse(idetestfeedback_get_coursemodule_info($cm));
    }

    public function test_get_coursemodule_info_surfaces_the_completion_rule(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpassrun' => 1,
        ]);
        $cm = get_coursemodule_from_instance('idetestfeedback', $instance->id);

        $info = idetestfeedback_get_coursemodule_info($cm);

        $this->assertInstanceOf(cached_cm_info::class, $info);
        $this->assertSame($instance->name, $info->name);
        $this->assertSame(1, (int) $info->customdata['customcompletionrules']['completionpassrun']);
    }

    public function test_get_coursemodule_info_omits_completion_rules_when_tracking_is_manual(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionpassrun' => 1,
        ]);
        $cm = get_coursemodule_from_instance('idetestfeedback', $instance->id);

        $info = idetestfeedback_get_coursemodule_info($cm);

        $this->assertNull($info->customdata);
    }

    public function test_reset_userdata_deletes_runs_when_requested(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'results' => [['testname' => 'testAdd', 'status' => 'PASSED']],
        ]);

        $status = idetestfeedback_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_idetestfeedback' => 1,
        ]);

        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $instance->id]));
        $this->assertCount(1, $status);
        $this->assertFalse($status[0]['error']);
    }

    public function test_reset_userdata_leaves_runs_when_not_requested(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'results' => [['testname' => 'testAdd', 'status' => 'PASSED']],
        ]);

        $status = idetestfeedback_reset_userdata((object) ['courseid' => $course->id]);

        $this->assertSame(1, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $instance->id]));
        $this->assertSame([], $status);
    }

    public function test_reset_userdata_shifts_the_submission_window_dates(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', [
            'course' => $course->id,
            'timeopen' => 1000,
            'timeclose' => 2000,
        ]);

        $status = idetestfeedback_reset_userdata((object) [
            'courseid' => $course->id,
            'timeshift' => 500,
        ]);

        $updated = $DB->get_record('idetestfeedback', ['id' => $instance->id]);
        $this->assertSame(1500, (int) $updated->timeopen);
        $this->assertSame(2500, (int) $updated->timeclose);
        $this->assertCount(1, $status);
    }

    public function test_view_logs_the_view_and_marks_it_viewed_for_completion(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => COMPLETION_VIEW_REQUIRED,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        [, $cm] = get_course_and_cm_from_instance($instance->id, 'idetestfeedback');

        $sink = $this->redirectEvents();
        idetestfeedback_view($instance, $course, $cm, $cm->context);
        $events = $sink->get_events();

        $this->assertInstanceOf(\mod_idetestfeedback\event\course_module_viewed::class, reset($events));
        $completion = new completion_info($course);
        $this->assertSame(COMPLETION_COMPLETE, (int) $completion->get_data($cm, false, $student->id)->completionstate);
    }

    public function test_reset_course_form_defaults_enables_the_reset_by_default(): void {
        $defaults = idetestfeedback_reset_course_form_defaults((object) ['id' => 1]);

        $this->assertSame(['reset_idetestfeedback' => 1], $defaults);
    }
}
