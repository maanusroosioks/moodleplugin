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
 * Tests for comparing a test case against the same test on earlier runs.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\source_history::class)]
final class source_history_test extends \basic_testcase {
    /** @var int The run being shown, for feedback written before it. */
    private const NOW = 1000;

    /**
     * Builds a result row the way the database hands it back.
     *
     * @param array $overrides the columns to set
     * @return \stdClass
     */
    private static function build_result(array $overrides = []): \stdClass {
        return (object) array_merge([
            'id' => 1,
            'runid' => 1,
            'testsuite' => 'CalcTest',
            'testname' => 'testAdd',
            'sourcefilepath' => 'tests/test_calculator.py',
            'sourcestartline' => 12,
            'sourceendline' => 13,
            'sourcecodehash' => 'aaaa',
            'feedback' => null,
            'feedbackmodified' => null,
        ], $overrides);
    }

    /**
     * Builds a file row.
     *
     * @param int $runid the run the file was captured with
     * @param int $blobid the body it captured there
     * @param string $path the repo-relative path
     * @return \stdClass
     */
    private static function build_file(int $runid, int $blobid, string $path = 'tests/test_calculator.py'): \stdClass {
        return (object) ['id' => $runid, 'runid' => $runid, 'path' => $path, 'blobid' => $blobid];
    }

    /**
     * The source columns of a result that names a whole file.
     *
     * @return array the columns of a result naming a whole file, not a declaration
     */
    private static function wholefile(): array {
        return ['sourcestartline' => null, 'sourceendline' => null];
    }

    /**
     * Builds the history under test.
     *
     * @param \stdClass[] $priorresults earlier result rows, newest run first
     * @param \stdClass[] $priorfiles the file rows of those runs
     * @return source_history
     */
    private static function history(array $priorresults, array $priorfiles = []): source_history {
        return new source_history($priorresults, $priorfiles, self::NOW);
    }

    public function test_an_equal_hash_on_the_previous_run_is_unchanged(): void {
        $history = self::history([self::build_result()]);

        $this->assertSame(source_change::UNCHANGED, $history->since_last_run(self::build_result()));
    }

    public function test_a_different_hash_is_changed(): void {
        $history = self::history([self::build_result(['sourcecodehash' => 'bbbb'])]);

        $this->assertSame(source_change::CHANGED, $history->since_last_run(self::build_result()));
    }

    public function test_a_test_missing_from_the_nearest_run_compares_against_an_older_one(): void {
        $history = self::history([
            self::build_result(['runid' => 3, 'testname' => 'testOther']),
            self::build_result(['runid' => 2, 'sourcecodehash' => 'bbbb']),
        ]);

        $this->assertSame(source_change::CHANGED, $history->since_last_run(self::build_result()));
    }

    public function test_the_newest_occurrence_wins_over_an_older_one(): void {
        $history = self::history([
            self::build_result(['runid' => 3, 'sourcecodehash' => 'aaaa']),
            self::build_result(['runid' => 2, 'sourcecodehash' => 'bbbb']),
        ]);

        $this->assertSame(source_change::UNCHANGED, $history->since_last_run(self::build_result()));
    }

    public function test_no_earlier_occurrence_is_unknown(): void {
        $history = self::history([self::build_result(['testname' => 'testOther'])]);

        $this->assertSame(source_change::UNKNOWN, $history->since_last_run(self::build_result()));
    }

    public function test_no_prior_rows_at_all_is_unknown(): void {
        $this->assertSame(source_change::UNKNOWN, self::history([])->since_last_run(self::build_result()));
    }

    public function test_a_missing_hash_on_either_side_is_unknown(): void {
        $this->assertSame(
            source_change::UNKNOWN,
            self::history([self::build_result()])->since_last_run(self::build_result(['sourcecodehash' => null]))
        );
        $this->assertSame(
            source_change::UNKNOWN,
            self::history([self::build_result(['sourcecodehash' => null])])->since_last_run(self::build_result())
        );
    }

    public function test_a_test_hash_is_never_compared_with_a_file_hash(): void {
        $history = self::history([self::build_result(self::wholefile())]);

        $this->assertSame(source_change::UNKNOWN, $history->since_last_run(self::build_result()));
    }

    public function test_two_file_hashes_do_compare(): void {
        $history = self::history([
            self::build_result(array_merge(self::wholefile(), ['sourcecodehash' => 'bbbb'])),
        ]);

        $this->assertSame(
            source_change::CHANGED,
            $history->since_last_run(self::build_result(self::wholefile()))
        );
    }

    public function test_a_test_that_was_never_found_is_unknown(): void {
        $notfound = ['sourcefilepath' => null, 'sourcestartline' => null, 'sourceendline' => null];
        $history = self::history([self::build_result($notfound)]);

        $this->assertSame(source_change::UNKNOWN, $history->since_last_run(self::build_result($notfound)));
    }

    public function test_hashes_compare_regardless_of_case_or_padding(): void {
        $history = self::history([self::build_result(['sourcecodehash' => ' AAAA '])]);

        $this->assertSame(source_change::UNCHANGED, $history->since_last_run(self::build_result()));
    }

    public function test_the_test_key_ignores_case_and_padding(): void {
        $history = self::history([
            self::build_result(['testsuite' => ' CALCTEST ', 'testname' => ' TESTADD ', 'sourcecodehash' => 'bbbb']),
        ]);

        $this->assertSame(source_change::CHANGED, $history->since_last_run(self::build_result()));
    }

    public function test_a_null_suite_and_an_empty_suite_are_one_test(): void {
        $history = self::history([self::build_result(['testsuite' => null, 'sourcecodehash' => 'bbbb'])]);

        $this->assertSame(
            source_change::CHANGED,
            $history->since_last_run(self::build_result(['testsuite' => '']))
        );
    }

    public function test_the_same_name_in_another_suite_is_another_test(): void {
        $history = self::history([self::build_result(['testsuite' => 'OtherTest', 'sourcecodehash' => 'bbbb'])]);

        $this->assertSame(source_change::UNKNOWN, $history->since_last_run(self::build_result()));
    }

    public function test_a_name_reported_twice_in_one_run_takes_the_first_row(): void {
        $history = self::history([
            self::build_result(['id' => 1, 'sourcecodehash' => 'aaaa']),
            self::build_result(['id' => 2, 'sourcecodehash' => 'bbbb']),
        ]);

        $this->assertSame(source_change::UNCHANGED, $history->since_last_run(self::build_result()));
    }

    public function test_feedback_on_an_earlier_run_anchors_the_comparison(): void {
        $history = self::history([self::build_result(['feedback' => 'Assert the error too', 'feedbackmodified' => 900])]);

        $this->assertSame(source_change::UNCHANGED, $history->since_feedback(self::build_result()));
        $this->assertSame(
            source_change::CHANGED,
            $history->since_feedback(self::build_result(['sourcecodehash' => 'bbbb']))
        );
    }

    public function test_an_earlier_occurrence_without_feedback_anchors_nothing(): void {
        $history = self::history([self::build_result()]);

        $this->assertSame(source_change::UNKNOWN, $history->since_feedback(self::build_result()));
    }

    public function test_the_feedback_anchor_reaches_past_a_newer_uncommented_run(): void {
        $history = self::history([
            self::build_result(['runid' => 3, 'sourcecodehash' => 'aaaa']),
            self::build_result([
                'runid' => 2,
                'sourcecodehash' => 'bbbb',
                'feedback' => 'Assert the error too',
                'feedbackmodified' => 900,
            ]),
        ]);

        $this->assertSame(source_change::UNCHANGED, $history->since_last_run(self::build_result()));
        $this->assertSame(source_change::CHANGED, $history->since_feedback(self::build_result()));
    }

    public function test_feedback_written_after_this_run_was_submitted_anchors_nothing(): void {
        $history = self::history([
            self::build_result(['feedback' => 'Assert the error too', 'feedbackmodified' => self::NOW]),
        ]);

        $this->assertSame(source_change::UNKNOWN, $history->since_feedback(self::build_result()));
    }

    public function test_feedback_with_no_recorded_time_still_anchors(): void {
        $history = self::history([
            self::build_result(['feedback' => 'Assert the error too', 'feedbackmodified' => null]),
        ]);

        $this->assertSame(source_change::UNCHANGED, $history->since_feedback(self::build_result()));
    }

    public function test_a_result_carrying_its_own_feedback_is_unknown(): void {
        $history = self::history([self::build_result(['feedback' => 'Older note', 'feedbackmodified' => 900])]);

        $this->assertSame(
            source_change::UNKNOWN,
            $history->since_feedback(self::build_result(['feedback' => 'The note on this very run']))
        );
    }

    public function test_whitespace_on_an_earlier_row_anchors_nothing(): void {
        $history = self::history([self::build_result(['feedback' => "  \n ", 'feedbackmodified' => 900])]);

        $this->assertSame(source_change::UNKNOWN, $history->since_feedback(self::build_result()));
    }

    public function test_whitespace_on_this_row_does_not_suppress_the_verdict(): void {
        $history = self::history([self::build_result(['feedback' => 'A note', 'feedbackmodified' => 900])]);

        $this->assertSame(
            source_change::UNCHANGED,
            $history->since_feedback(self::build_result(['feedback' => "  \n "]))
        );
    }

    public function test_the_file_check_reads_the_body_the_anchor_run_captured(): void {
        $history = self::history(
            [
                self::build_result(['runid' => 3, 'sourcecodehash' => 'aaaa']),
                self::build_result([
                    'runid' => 2,
                    'feedback' => 'Assert the error too',
                    'feedbackmodified' => 900,
                ]),
            ],
            [self::build_file(3, 77), self::build_file(2, 88)]
        );

        $this->assertSame(source_change::UNCHANGED, $history->file_since_feedback(self::build_result(), 88));
        $this->assertSame(source_change::CHANGED, $history->file_since_feedback(self::build_result(), 77));
    }

    public function test_the_file_check_is_unknown_without_a_body_on_either_side(): void {
        $anchor = [self::build_result(['feedback' => 'A note', 'feedbackmodified' => 900])];

        $this->assertSame(
            source_change::UNKNOWN,
            self::history($anchor, [self::build_file(1, 88)])->file_since_feedback(self::build_result(), null)
        );
        $this->assertSame(
            source_change::UNKNOWN,
            self::history($anchor)->file_since_feedback(self::build_result(), 88)
        );
        $this->assertSame(
            source_change::UNKNOWN,
            self::history($anchor, [self::build_file(1, 88, 'tests/other.py')])
                ->file_since_feedback(self::build_result(), 88)
        );
    }

    public function test_the_file_check_is_unknown_without_a_feedback_anchor(): void {
        $history = self::history([self::build_result()], [self::build_file(1, 88)]);

        $this->assertSame(source_change::UNKNOWN, $history->file_since_feedback(self::build_result(), 88));
    }

    public function test_the_file_check_is_unknown_when_the_anchor_names_no_file(): void {
        $history = self::history(
            [self::build_result(['sourcefilepath' => null, 'feedback' => 'A note', 'feedbackmodified' => 900])],
            [self::build_file(1, 88)]
        );

        $this->assertSame(source_change::UNKNOWN, $history->file_since_feedback(self::build_result(), 88));
    }
}
