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

use mod_idetestfeedback\local\source_history;

/**
 * Exposes the badge builder.
 */
class testable_run_detail_history extends run_detail {

    /**
     * @param \stdClass $result one test case result
     * @return array[] the exported badges
     */
    public function badges(\stdClass $result): array {
        return $this->history_badges($result);
    }
}

/**
 * Tests for how the run detail reports a test case against earlier runs.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\output\run_detail
 */
final class run_detail_history_test extends \advanced_testcase {

    /** @var int The run being shown, for feedback written before it. */
    private const NOW = 1000;

    /** @var string The file every test in here lives in. */
    private const PATH = 'tests/test_calculator.py';

    /**
     * @param array $overrides the columns to set
     * @return \stdClass a result row
     */
    private static function testresult(array $overrides = []): \stdClass {
        return (object) array_merge([
            'id' => 1,
            'runid' => 1,
            'testsuite' => 'CalcTest',
            'testname' => 'testAdd',
            'sourcefilepath' => self::PATH,
            'sourcestartline' => 1,
            'sourceendline' => 2,
            'sourcecodehash' => 'aaaa',
            'feedback' => null,
            'feedbackmodified' => null,
        ], $overrides);
    }

    /**
     * @param array $priorresults earlier result rows, newest run first
     * @param array $priorfiles the file rows of those runs
     * @param int|null $currentblobid the body this run captured for the file
     * @return testable_run_detail_history
     */
    private function detail(array $priorresults, array $priorfiles = [],
                            ?int $currentblobid = null): testable_run_detail_history {
        return new testable_run_detail_history(
            (object) ['requiredtests' => null],
            (object) [],
            [],
            [(object) ['path' => self::PATH, 'blobid' => $currentblobid, 'content' => null]],
            null,
            \context_system::instance(),
            1,
            false,
            new source_history($priorresults, $priorfiles, self::NOW)
        );
    }

    /**
     * @param int $runid the run the file was captured with
     * @param int $blobid the body it captured there
     * @return \stdClass
     */
    private static function file(int $runid, int $blobid): \stdClass {
        return (object) ['id' => $runid, 'runid' => $runid, 'path' => self::PATH, 'blobid' => $blobid];
    }

    /**
     * @param string $identifier the expected label's string identifier
     * @param array[] $badges what the run detail exported
     */
    private function assert_badge(string $identifier, array $badges): void {
        $this->assertCount(1, $badges);
        $this->assertSame(get_string($identifier, 'mod_idetestfeedback'), $badges[0]['label']);
    }

    public function test_a_body_that_changed_since_the_last_run_is_badged(): void {
        $detail = $this->detail([self::testresult(['sourcecodehash' => 'bbbb'])]);

        $this->assert_badge('sourcechanged', $detail->badges(self::testresult()));
    }

    public function test_an_unchanged_body_with_no_feedback_is_not_badged(): void {
        $detail = $this->detail([self::testresult()]);

        $this->assertSame([], $detail->badges(self::testresult()));
    }

    public function test_a_test_with_no_history_is_not_badged(): void {
        $this->assertSame([], $this->detail([])->badges(self::testresult()));
    }

    public function test_a_body_that_changed_since_feedback_is_badged(): void {
        $detail = $this->detail([
            self::testresult(['sourcecodehash' => 'bbbb', 'feedback' => 'A note', 'feedbackmodified' => 900]),
        ]);

        $this->assert_badge('sourcefeedbackchanged', $detail->badges(self::testresult()));
    }

    public function test_an_untouched_file_earns_the_wider_claim(): void {
        $detail = $this->detail(
            [self::testresult(['feedback' => 'A note', 'feedbackmodified' => 900])],
            [self::file(1, 88)],
            88
        );

        $this->assert_badge('sourcefeedbackunchangedfile', $detail->badges(self::testresult()));
    }

    public function test_an_edited_file_keeps_the_claim_to_the_body(): void {
        $detail = $this->detail(
            [self::testresult(['feedback' => 'A note', 'feedbackmodified' => 900])],
            [self::file(1, 88)],
            77
        );

        $this->assert_badge('sourcefeedbackunchanged', $detail->badges(self::testresult()));
    }

    public function test_an_uncaptured_file_keeps_the_claim_to_the_body(): void {
        $detail = $this->detail([self::testresult(['feedback' => 'A note', 'feedbackmodified' => 900])]);

        $this->assert_badge('sourcefeedbackunchanged', $detail->badges(self::testresult()));
    }

    public function test_a_file_kind_result_needs_no_second_hash(): void {
        $detail = $this->detail([
            self::testresult(['sourcestartline' => null, 'sourceendline' => null,
                'feedback' => 'A note', 'feedbackmodified' => 900]),
        ]);

        $this->assert_badge(
            'sourcefeedbackunchangedfile',
            $detail->badges(self::testresult(['sourcestartline' => null, 'sourceendline' => null]))
        );
    }

    public function test_an_edit_elsewhere_in_the_file_leaves_an_untouched_test_unchanged(): void {
        global $DB;
        $this->resetAfterTest();

        $repository = new \mod_idetestfeedback\local\repository($DB);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback');

        $before = "def testStill():\n    assert add(1, 2) == 3\n\ndef testMoved():\n    assert sub(2, 1) == 1\n";
        $after  = "def testStill():\n    assert add(1, 2) == 3\n\ndef testMoved():\n    assert sub(5, 1) == 4\n";

        $source = ['status' => 'FAILED', 'sourcefilepath' => self::PATH];
        $still = array_merge($source, ['testname' => 'testStill', 'sourcestartline' => 1, 'sourceendline' => 2]);
        $moved = array_merge($source, ['testname' => 'testMoved', 'sourcestartline' => 4, 'sourceendline' => 5]);

        $commented = $generator->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'timecreated' => 100,
            'results' => [$still, $moved],
            'files' => [['path' => self::PATH, 'content' => $before]],
        ]);

        foreach ($repository->get_results((int) $commented->id) as $result) {
            $repository->update_result_feedback((int) $result->id, 'Assert the error too', FORMAT_PLAIN, $teacher->id);
        }
        $DB->set_field('idetestfeedback_result', 'feedbackmodified', 150, ['runid' => $commented->id]);

        $current = $generator->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'timecreated' => 200,
            'results' => [$still, $moved],
            'files' => [['path' => self::PATH, 'content' => $after]],
        ]);

        $priorids = $repository->get_prior_run_ids(
            (int) $instance->id,
            (int) $student->id,
            (int) $current->id,
            source_history::LOOKBACK_RUNS
        );
        $this->assertSame([(int) $commented->id], $priorids);

        $detail = new testable_run_detail_history(
            $instance,
            $current,
            $repository->get_results((int) $current->id),
            $repository->get_files((int) $current->id),
            null,
            \context_module::instance(
                get_coursemodule_from_instance('idetestfeedback', $instance->id, $course->id, false, MUST_EXIST)->id
            ),
            1,
            false,
            new source_history(
                $repository->get_source_history($priorids),
                $repository->get_file_history($priorids),
                (int) $current->timecreated
            )
        );

        $badges = [];
        foreach ($repository->get_results((int) $current->id) as $result) {
            $badges[$result->testname] = $detail->badges($result);
        }

        $this->assert_badge('sourcefeedbackunchanged', $badges['testStill']);
        $this->assert_badge('sourcefeedbackchanged', $badges['testMoved']);
    }

    public function test_a_run_that_captured_nothing_is_no_anchor_for_a_later_one(): void {
        $detail = $this->detail(
            [self::testresult(['sourcecodehash' => null, 'feedback' => 'A note', 'feedbackmodified' => 900])],
            [self::file(1, 0)],
            88
        );

        $this->assertSame([], $detail->badges(self::testresult()));
    }

    public function test_the_feedback_axis_wins_over_the_run_to_run_one(): void {
        $detail = $this->detail([
            self::testresult(['runid' => 3, 'sourcecodehash' => 'bbbb']),
            self::testresult(['runid' => 2, 'sourcecodehash' => 'aaaa', 'feedback' => 'A note', 'feedbackmodified' => 900]),
        ], [self::file(2, 88)], 88);

        $this->assert_badge('sourcefeedbackunchangedfile', $detail->badges(self::testresult()));
    }
}
