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

use mod_idetestfeedback\local\feedback_history;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/testable_run_detail_feedback.php');

/**
 * Tests for how the run detail reports a test case against the feedback it was given.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\output\run_detail::class)]
final class run_detail_feedback_test extends \advanced_testcase {
    /**
     * Builds a result row.
     *
     * @param array $overrides the columns to set
     * @return \stdClass a result row
     */
    private static function testresult(array $overrides = []): \stdClass {
        return (object) array_merge([
            'id' => 1,
            'testsuite' => 'CalcTest',
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'feedback' => null,
        ], $overrides);
    }

    /**
     * Builds the run detail under test.
     *
     * @param \stdClass[] $commented earlier results carrying feedback, newest run first
     * @return testable_run_detail_feedback
     */
    private function detail(array $commented): testable_run_detail_feedback {
        return new testable_run_detail_feedback(
            run: (object) [],
            results: [],
            files: [],
            studentname: null,
            context: \context_system::instance(),
            cancomment: false,
            history: new feedback_history($commented),
            backurl: new \core\url('/'),
            formurl: new \core\url('/')
        );
    }

    /**
     * Each outcome maps to its label and classes.
     *
     * @return array[]
     */
    public static function outcome_provider(): array {
        return [
            'fixed' => ['FAILED', 'PASSED', 'fixedsincefeedback', 'bg-success text-white'],
            'still failing' => ['ERROR', 'FAILED', 'stillfailingsincefeedback', 'bg-warning text-dark'],
            'regressed' => ['PASSED', 'ERROR', 'failingsincefeedback', 'bg-danger text-white'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('outcome_provider')]
    public function test_an_outcome_is_badged(string $then, string $now, string $identifier, string $classes): void {
        $badges = $this->detail([self::testresult(['status' => $then])])
            ->badges(self::testresult(['status' => $now]));

        $this->assertSame(
            [['label' => get_string($identifier, 'mod_idetestfeedback'), 'classes' => $classes]],
            $badges
        );
    }

    public function test_a_test_passing_then_and_now_is_not_badged(): void {
        $this->assertSame([], $this->detail([self::testresult()])->badges(self::testresult()));
    }

    public function test_a_test_with_no_feedback_is_not_badged(): void {
        $this->assertSame([], $this->detail([])->badges(self::testresult(['status' => 'FAILED'])));
    }

    public function test_the_status_feedback_was_written_on_is_what_the_run_is_compared_with(): void {
        global $DB;
        $this->resetAfterTest();

        $repository = new \mod_idetestfeedback\local\repository($DB);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback');

        $commented = $generator->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'timecreated' => 100,
            'results' => [
                ['testname' => 'testFixed', 'status' => 'FAILED'],
                ['testname' => 'testStuck', 'status' => 'FAILED'],
            ],
        ]);
        foreach ($repository->get_results((int) $commented->id) as $result) {
            $repository->update_result_feedback((int) $result->id, 'Check the edge case', FORMAT_PLAIN, $teacher->id);
        }
        $DB->set_field('idetestfeedback_result', 'feedbackmodified', 150, ['runid' => $commented->id]);

        $current = $generator->create_run([
            'idetestfeedbackid' => $instance->id,
            'userid' => $student->id,
            'timecreated' => 200,
            'results' => [
                ['testname' => 'testFixed', 'status' => 'PASSED'],
                ['testname' => 'testStuck', 'status' => 'FAILED'],
            ],
        ]);

        $detail = $this->detail($repository->get_feedback_history((int) $current->id));

        $badges = [];
        foreach ($repository->get_results((int) $current->id) as $result) {
            $badges[$result->testname] = array_column($detail->badges($result), 'label');
        }

        $this->assertSame([get_string('fixedsincefeedback', 'mod_idetestfeedback')], $badges['testFixed']);
        $this->assertSame([get_string('stillfailingsincefeedback', 'mod_idetestfeedback')], $badges['testStuck']);
    }
}
