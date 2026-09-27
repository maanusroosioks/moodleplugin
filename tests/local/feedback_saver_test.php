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
 * Tests for saving teacher feedback on test case results.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\feedback_saver::class)]
final class feedback_saver_test extends \advanced_testcase {
    /** @var repository */
    private repository $repository;

    /** @var feedback_saver */
    private feedback_saver $saver;

    /** @var \stdClass */
    private \stdClass $teacher;

    /** @var \stdClass */
    private \stdClass $run;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $this->repository = new repository($DB);
        $this->saver = new feedback_saver($this->repository);

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $this->run = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'results' => [
                ['testname' => 'testAdd', 'status' => 'PASSED'],
                ['testname' => 'testSub', 'status' => 'FAILED'],
            ],
        ]);
    }

    /**
     * The results of the test run, by test name.
     *
     * @return array<string, \stdClass>
     */
    private function results_by_name(): array {
        $byid = [];
        foreach ($this->repository->get_results($this->run->id) as $result) {
            $byid[$result->testname] = $result;
        }

        return $byid;
    }

    public function test_save_stores_new_feedback_and_reports_it_as_changed(): void {
        $results = $this->results_by_name();

        $changed = $this->saver->save((int) $this->run->id, [
            (int) $results['testAdd']->id => 'Well done',
        ], $this->teacher->id);

        $this->assertCount(1, $changed);
        $this->assertSame('Well done', reset($changed)->feedback);
        $this->assertSame('Well done', $this->results_by_name()['testAdd']->feedback);
    }

    public function test_save_ignores_results_the_form_did_not_submit(): void {
        $results = $this->results_by_name();
        $this->repository->update_result_feedback((int) $results['testSub']->id, 'existing', $this->teacher->id);

        $changed = $this->saver->save((int) $this->run->id, [
            (int) $results['testAdd']->id => 'new feedback',
        ], $this->teacher->id);

        $this->assertCount(1, $changed);
        $this->assertSame('existing', $this->results_by_name()['testSub']->feedback);
    }

    public function test_save_persists_a_cleared_value_but_does_not_report_it_as_changed(): void {
        $results = $this->results_by_name();
        $this->repository->update_result_feedback((int) $results['testAdd']->id, 'existing', $this->teacher->id);

        $changed = $this->saver->save((int) $this->run->id, [
            (int) $results['testAdd']->id => '   ',
        ], $this->teacher->id);

        $this->assertCount(0, $changed);
        $this->assertNull($this->results_by_name()['testAdd']->feedback);
    }

    public function test_save_skips_results_whose_trimmed_value_is_unchanged(): void {
        $results = $this->results_by_name();
        $this->repository->update_result_feedback((int) $results['testAdd']->id, 'same', $this->teacher->id);

        $changed = $this->saver->save((int) $this->run->id, [
            (int) $results['testAdd']->id => '  same  ',
        ], $this->teacher->id);

        $this->assertCount(0, $changed);
    }

    public function test_save_ignores_a_result_of_another_run(): void {
        $otherrun = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $this->run->idetestfeedbackid,
            'userid' => $this->run->userid,
            'results' => [['testname' => 'testMul', 'status' => 'FAILED']],
        ]);
        $otherresult = current($this->repository->get_results($otherrun->id));

        $changed = $this->saver->save((int) $this->run->id, [
            (int) $otherresult->id => 'Injected',
        ], $this->teacher->id);

        $this->assertSame([], $changed);
        $this->assertNull(current($this->repository->get_results($otherrun->id))->feedback);
    }

    public function test_save_with_nothing_submitted_changes_nothing(): void {
        $changed = $this->saver->save((int) $this->run->id, [], $this->teacher->id);

        $this->assertSame([], $changed);
    }
}
