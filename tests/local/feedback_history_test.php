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
 * Tests for comparing results against the status they had when commented on.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\feedback_history::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\feedback_outcome::class)]
final class feedback_history_test extends \basic_testcase {
    /**
     * Builds a result row.
     *
     * @param array $overrides the columns to set
     * @return \stdClass a result row
     */
    private static function build_result(array $overrides = []): \stdClass {
        return (object) array_merge([
            'testsuite' => 'CalcTest',
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'feedback' => null,
        ], $overrides);
    }

    /**
     * Status pairs and the outcome between them.
     *
     * @return array[]
     */
    public static function outcome_provider(): array {
        return [
            'failed to passed' => ['FAILED', 'PASSED', feedback_outcome::FIXED],
            'error to passed' => ['ERROR', 'PASSED', feedback_outcome::FIXED],
            'failed to failed' => ['FAILED', 'FAILED', feedback_outcome::STILLFAILING],
            'failed to error' => ['FAILED', 'ERROR', feedback_outcome::STILLFAILING],
            'passed to failed' => ['PASSED', 'FAILED', feedback_outcome::REGRESSED],
            'passed to error' => ['PASSED', 'ERROR', feedback_outcome::REGRESSED],
            'passed to passed' => ['PASSED', 'PASSED', null],
            'failed to skipped' => ['FAILED', 'SKIPPED', null],
            'skipped to failed' => ['SKIPPED', 'FAILED', null],
            'unknown then' => ['BOGUS', 'PASSED', null],
            'unknown now' => ['FAILED', 'BOGUS', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('outcome_provider')]
    public function test_the_outcome_follows_the_status_pair(string $then, string $now, ?feedback_outcome $expected): void {
        $history = new feedback_history([self::build_result(['status' => $then])]);

        $this->assertSame($expected, $history->outcome(self::build_result(['status' => $now])));
    }

    public function test_a_test_never_commented_on_has_no_outcome(): void {
        $history = new feedback_history([self::build_result(['testname' => 'testOther', 'status' => 'FAILED'])]);

        $this->assertNull($history->outcome(self::build_result()));
    }

    public function test_the_newest_comment_wins_over_an_older_one(): void {
        $history = new feedback_history([
            self::build_result(['status' => 'PASSED']),
            self::build_result(['status' => 'FAILED']),
        ]);

        $this->assertNull($history->outcome(self::build_result()));
    }

    public function test_a_result_with_feedback_of_its_own_has_no_outcome(): void {
        $history = new feedback_history([self::build_result(['status' => 'FAILED'])]);

        $this->assertNull($history->outcome(self::build_result(['feedback' => 'Nice'])));
        $this->assertSame(
            feedback_outcome::FIXED,
            $history->outcome(self::build_result(['feedback' => '  ']))
        );
    }

    public function test_the_test_key_ignores_case_and_padding(): void {
        $history = new feedback_history([
            self::build_result(['testsuite' => ' CALCTEST ', 'testname' => ' TESTADD ', 'status' => 'FAILED']),
        ]);

        $this->assertSame(feedback_outcome::FIXED, $history->outcome(self::build_result()));
    }

    public function test_a_null_suite_and_an_empty_suite_are_one_test(): void {
        $history = new feedback_history([self::build_result(['testsuite' => null, 'status' => 'FAILED'])]);

        $this->assertSame(feedback_outcome::FIXED, $history->outcome(self::build_result(['testsuite' => ''])));
    }

    public function test_the_same_name_in_another_suite_is_another_test(): void {
        $history = new feedback_history([self::build_result(['testsuite' => 'OtherTest', 'status' => 'FAILED'])]);

        $this->assertNull($history->outcome(self::build_result()));
    }
}
