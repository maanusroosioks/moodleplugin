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

namespace mod_idetestfeedback\output;

use core\url;
use mod_idetestfeedback\local\repository;

/**
 * Tests for the run list table.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\output\run_list::class)]
final class run_list_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $instance;

    /** @var \stdClass */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($course, 'student');
    }

    /**
     * Stores a run and exports it as the one row of a run list.
     *
     * @param array $record run columns and 'results', as the generator takes them; one passing test by default
     * @param bool $showstudent whether the list has the student column
     * @param url|null $detailurl the run detail page, or null for the bare view.php
     * @return array the exported row
     */
    private function row(array $record, bool $showstudent = false, ?url $detailurl = null): array {
        global $DB, $PAGE;

        $run = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run($record + [
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $this->student->id,
        ]);
        $runs = (new repository($DB))->get_runs_for_instance($this->instance->id);

        $list = new run_list(
            runs: $runs,
            detailurl: $detailurl ?? new url('/mod/idetestfeedback/view.php', ['id' => $this->instance->cmid]),
            showstudent: $showstudent,
            viewfullnames: true
        );
        $rows = $list->export_for_template($PAGE->get_renderer('core'))['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame((string) $run->id, (new url($rows[0]['detailurl']))->param('runid'));

        return $rows[0];
    }

    public function test_a_commit_on_a_browsable_remote_links_to_its_page(): void {
        $row = $this->row(['commithash' => 'abc1234def', 'repourl' => 'https://github.com/ada/calc.git']);

        $this->assertSame('abc1234', $row['shorthash']);
        $this->assertSame('https://github.com/ada/calc/commit/abc1234def', $row['commiturl']);
    }

    public function test_a_commit_without_a_remote_is_shown_but_not_linked(): void {
        $row = $this->row(['commithash' => 'abc1234def']);

        $this->assertSame('abc1234', $row['shorthash']);
        $this->assertSame('', $row['commiturl']);
    }

    public function test_a_run_without_a_commit_shows_none(): void {
        $row = $this->row(['repourl' => 'https://github.com/ada/calc.git']);

        $this->assertSame('', $row['shorthash']);
        $this->assertSame('', $row['commiturl']);
    }

    public function test_errors_count_as_failures(): void {
        $row = $this->row(['results' => [
            ['testname' => 'testAdd', 'status' => 'PASSED'],
            ['testname' => 'testSub', 'status' => 'FAILED'],
            ['testname' => 'testDiv', 'status' => 'ERROR'],
            ['testname' => 'testMul', 'status' => 'SKIPPED'],
        ]]);

        $this->assertSame(1, $row['passed']);
        $this->assertSame(2, $row['failed']);
        $this->assertTrue($row['hasfailed']);
    }

    public function test_a_passing_run_has_no_failures_to_highlight(): void {
        $row = $this->row([]);

        $this->assertSame(0, $row['failed']);
        $this->assertFalse($row['hasfailed']);
    }

    public function test_each_row_links_to_its_run_keeping_the_list_state(): void {
        $row = $this->row([], false, new url('/mod/idetestfeedback/view.php', [
            'id' => $this->instance->cmid,
            'filterstatus' => 'FAILED',
            'listpage' => 2,
        ]));

        $params = (new url($row['detailurl']))->params();
        $this->assertSame((string) $this->instance->cmid, $params['id']);
        $this->assertSame('FAILED', $params['filterstatus']);
        $this->assertSame('2', $params['listpage']);
    }

    public function test_the_student_is_named_when_the_column_is_shown(): void {
        $this->assertSame(fullname($this->student, true), $this->row([], true)['student']);
    }

    public function test_the_student_is_left_out_when_the_column_is_hidden(): void {
        $this->assertSame('', $this->row([], false)['student']);
    }
}
