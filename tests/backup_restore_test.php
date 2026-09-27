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
 * Tests that a backed up activity restores with its runs, feedback and captured code intact.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_idetestfeedback_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_idetestfeedback_activity_structure_step::class)]
final class backup_restore_test extends \advanced_testcase {
    /** @var string The file both runs capture. */
    private const BODY = "def testAdd():\n    assert add(1, 2) == 3\n";

    /** @var string Where it lives. */
    private const PATH = 'tests/test_calculator.py';

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $instance;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var repository */
    private repository $repository;

    #[\Override]
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->repository = new repository($DB);
    }

    /**
     * Stores a run for the student in the activity.
     *
     * @param array $record other run fields, as the generator takes them
     * @return \stdClass the stored run
     */
    private function create_run(array $record = []): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run($record + [
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $this->student->id,
        ]);
    }

    /**
     * Backs the activity up with user data and restores it into a new course.
     *
     * @return \stdClass the restored activity instance
     */
    private function roundtrip(): \stdClass {
        return $this->restore($this->backup());
    }

    /**
     * Backs the activity up, ready to restore.
     *
     * @param bool $users whether to include user data
     * @return string the backup id
     */
    private function backup(bool $users = true): string {
        global $USER;

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $this->instance->cmid,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($users);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $file = $bc->get_results()['backup_destination'];
        $file->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupid)
        );
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restores a backup into a new course.
     *
     * @param string $backupid from {@see backup()}
     * @return \stdClass the restored activity instance
     */
    private function restore(string $backupid): \stdClass {
        global $DB, $USER;

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

    /**
     * The restored activity's only run.
     *
     * @param \stdClass $copy the restored activity instance
     * @return \stdClass
     */
    private function restored_run(\stdClass $copy): \stdClass {
        global $DB;

        $runs = $DB->get_records('idetestfeedback_run', ['idetestfeedbackid' => $copy->id]);
        $this->assertCount(1, $runs);

        return reset($runs);
    }

    public function test_two_runs_sharing_a_body_still_share_one_after_a_restore(): void {
        global $DB;

        $run = [
            'results' => [[
                'testname' => 'testAdd',
                'status' => 'PASSED',
                'sourcefilepath' => self::PATH,
                'sourcestartline' => 1,
                'sourceendline' => 2,
            ]],
            'files' => [['path' => self::PATH, 'content' => self::BODY]],
            'timecreated' => 1000,
        ];
        $this->create_run($run);
        $this->create_run($run);

        $this->assertSame(1, $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $this->instance->id]));

        $copy = $this->roundtrip();

        $this->assertSame(1, $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $copy->id]));
        $this->assertSame(1000, (int) $DB->get_field('idetestfeedback_blob', 'timecreated', ['idetestfeedbackid' => $copy->id]));

        $runids = array_keys($DB->get_records('idetestfeedback_run', ['idetestfeedbackid' => $copy->id]));
        $this->assertCount(2, $runids);

        $blobids = [];
        foreach ($runids as $runid) {
            $files = array_values($this->repository->get_files((int) $runid));
            $this->assertCount(1, $files);
            $this->assertSame(source_code::canonicalise(self::BODY), $files[0]->content);
            $blobids[] = (int) $DB->get_field('idetestfeedback_file', 'blobid', ['id' => $files[0]->id]);
        }

        $this->assertSame($blobids[0], $blobids[1]);
    }

    public function test_a_run_that_captured_nothing_restores_without_a_body(): void {
        global $DB;

        $this->create_run([
            'capturedisabled' => 1,
            'results' => [['testname' => 'testAdd', 'status' => 'PASSED', 'sourcefilepath' => self::PATH]],
            'files' => [['path' => self::PATH, 'content' => null]],
        ]);

        $copy = $this->roundtrip();

        $this->assertSame(0, $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $copy->id]));

        $files = array_values($this->repository->get_files((int) $this->restored_run($copy)->id));
        $this->assertCount(1, $files);
        $this->assertSame(self::PATH, $files[0]->path);
        $this->assertNull($DB->get_field('idetestfeedback_file', 'blobid', ['id' => $files[0]->id]));
        $this->assertNull($files[0]->content);
    }

    public function test_runs_results_and_feedback_restore_against_the_same_users(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $run = $this->create_run([
            'commithash' => 'abc1234',
            'results' => [['testname' => 'testAdd', 'status' => 'FAILED', 'message' => 'expected 3']],
        ]);
        $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')
            ->add_feedback($run, $teacher->id, 'Off by one');

        $restoredrun = $this->restored_run($this->roundtrip());

        $this->assertSame((int) $this->student->id, (int) $restoredrun->userid);
        $this->assertSame('abc1234', $restoredrun->commithash);
        $this->assertSame('FAILED', $restoredrun->status);

        $results = array_values($this->repository->get_results((int) $restoredrun->id));
        $this->assertCount(1, $results);
        $this->assertSame('testAdd', $results[0]->testname);
        $this->assertSame('expected 3', $results[0]->message);
        $this->assertSame('Off by one', $results[0]->feedback);
        $this->assertSame((int) $teacher->id, (int) $results[0]->feedbackby);
    }

    public function test_a_duplicate_is_given_a_key_of_its_own(): void {
        $copy = $this->roundtrip();

        $this->assertNotSame($this->instance->assignmentkey, $copy->assignmentkey);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $copy->assignmentkey);
    }

    public function test_a_restore_keeps_the_key_once_the_original_is_gone(): void {
        $backupid = $this->backup();
        \core_courseformat\formatactions::cm($this->course)->delete($this->instance->cmid);

        $copy = $this->restore($backupid);

        $this->assertSame($this->instance->assignmentkey, $copy->assignmentkey);
    }

    public function test_a_backup_without_user_data_restores_no_runs(): void {
        global $DB;

        $this->create_run(['files' => [['path' => self::PATH, 'content' => self::BODY]]]);

        $copy = $this->restore($this->backup(false));

        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $copy->id]));
        $this->assertSame(0, $DB->count_records('idetestfeedback_blob', ['idetestfeedbackid' => $copy->id]));
    }
}
