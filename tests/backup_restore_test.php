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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_idetestfeedback;

use backup;
use backup_controller;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\source_code;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests that a backed up activity restores with its captured code intact.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \restore_idetestfeedback_activity_structure_step
 * @covers     \backup_idetestfeedback_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var string The file both runs capture. */
    private const BODY = "def testAdd():\n    assert add(1, 2) == 3\n";

    /** @var string Where it lives. */
    private const PATH = 'tests/test_calculator.py';

    /**
     * Backs the activity up with user data and restores it into a new course.
     *
     * @param \stdClass $instance the activity to copy
     * @param \stdClass $course the course it lives in
     * @return \stdClass the restored activity instance
     */
    private function roundtrip(\stdClass $instance, \stdClass $course): \stdClass {
        global $DB, $USER;

        $cm = get_coursemodule_from_instance('idetestfeedback', $instance->id, $course->id, false, MUST_EXIST);

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $file = $bc->get_results()['backup_destination'];
        $file->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupid)
        );
        $bc->destroy();

        $target = $this->getDataGenerator()->create_course();
        $rc = new restore_controller(
            $backupid,
            $target->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_records('idetestfeedback', ['course' => $target->id]);
        $this->assertCount(1, $restored);

        return reset($restored);
    }

    public function test_two_runs_sharing_a_body_still_share_one_after_a_restore(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback');

        $run = [
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'results' => [[
                'testname' => 'testAdd',
                'status' => 'PASSED',
                'sourcefilepath' => self::PATH,
                'sourcestartline' => 1,
                'sourceendline' => 2,
            ]],
            'files' => [['path' => self::PATH, 'content' => self::BODY]],
        ];
        $generator->create_run($run);
        $generator->create_run($run);

        $this->assertSame(1, $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $instance->id]));

        $copy = $this->roundtrip($instance, $course);

        $this->assertSame(1, $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $copy->id]));

        $repository = new repository($DB);
        $runids = array_keys($DB->get_records('idetestfeedback_run', ['idetestfeedbackid' => $copy->id]));
        $this->assertCount(2, $runids);

        $blobids = [];
        foreach ($runids as $runid) {
            $files = array_values($repository->get_files((int) $runid));
            $this->assertCount(1, $files);
            $this->assertSame(source_code::canonicalise(self::BODY), $files[0]->content);
            $blobids[] = (int) $files[0]->blobid;

            foreach ($repository->get_results((int) $runid) as $result) {
                $this->assertSame(source_code::hash(self::BODY), $result->sourcecodehash);
            }
        }

        $this->assertSame($blobids[0], $blobids[1]);
    }

    public function test_a_run_that_captured_nothing_restores_without_a_body(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'capturedisabled' => 1,
            'results' => [['testname' => 'testAdd', 'status' => 'PASSED', 'sourcefilepath' => self::PATH]],
            'files' => [['path' => self::PATH, 'content' => null]],
        ]);

        $copy = $this->roundtrip($instance, $course);

        $this->assertSame(0, $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $copy->id]));

        $runid = (int) array_key_first($DB->get_records('idetestfeedback_run', ['idetestfeedbackid' => $copy->id]));
        $files = array_values((new repository($DB))->get_files($runid));

        $this->assertCount(1, $files);
        $this->assertSame(self::PATH, $files[0]->path);
        $this->assertNull($files[0]->blobid);
        $this->assertNull($files[0]->content);
    }
}
