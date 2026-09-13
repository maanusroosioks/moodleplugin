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
 * Tests for the defined test case list.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\local\required_tests
 */
final class required_tests_test extends \basic_testcase {

    /**
     * @return array[] [raw input, expected entries]
     */
    public static function parse_provider(): array {
        return [
            'empty' => [null, []],
            'blank' => ["  \n\n  ", []],
            'plain names' => ["testAdd\ntestSub", ['testAdd', 'testSub']],
            'trims each line' => ["  testAdd  \n\ttestSub ", ['testAdd', 'testSub']],
            'qualified' => ["CalcTest#testAdd", ['CalcTest#testAdd']],
            'normalises spacing around the qualifier' => [' CalcTest # testAdd ', ['CalcTest#testAdd']],
            'drops entries with no name' => ["#\nSuite#\ntestAdd", ['testAdd']],
            'drops case-insensitive duplicates, keeping the first' => [
                "testAdd\nTESTADD\ntestAdd",
                ['testAdd'],
            ],
            'a bare name and a qualified one are different entries' => [
                "testAdd\nCalcTest#testAdd",
                ['testAdd', 'CalcTest#testAdd'],
            ],
            'only the last qualifier splits' => ['a#b#c', ['a#b#c']],
            'handles CRLF' => ["testAdd\r\ntestSub", ['testAdd', 'testSub']],
        ];
    }

    /**
     * @dataProvider parse_provider
     * @param string|null $raw the teacher's list as typed
     * @param string[] $expected the canonical entries
     */
    public function test_parse(?string $raw, array $expected): void {
        $this->assertSame($expected, required_tests::parse($raw));
    }

    public function test_normalize_round_trips_through_parse(): void {
        $normalized = required_tests::normalize("  B#two \n one\n one\n");

        $this->assertSame("B#two\none", $normalized);
        $this->assertSame(['B#two', 'one'], required_tests::parse($normalized));
    }

    /**
     * Builds a result row the way the database hands it back.
     *
     * @param string $name the test name
     * @param string $status the reported status
     * @param string|null $suite the test suite
     * @return \stdClass
     */
    private static function result(string $name, string $status, ?string $suite = null): \stdClass {
        return (object) ['testname' => $name, 'status' => $status, 'testsuite' => $suite];
    }

    public function test_evaluate_buckets_every_entry_exactly_once(): void {
        $entries = required_tests::parse("testPass\ntestFail\ntestSkip\ntestGone\ntestError");

        $tally = required_tests::evaluate($entries, [
            self::result('testPass', 'PASSED'),
            self::result('testFail', 'FAILED'),
            self::result('testSkip', 'SKIPPED'),
            self::result('testError', 'ERROR'),
            self::result('testNotRequired', 'PASSED'),
        ]);

        $this->assertSame(
            ['total' => 5, 'passed' => 1, 'failed' => 2, 'skipped' => 1, 'missing' => 1],
            $tally
        );
        $this->assertSame(
            $tally['total'],
            $tally['passed'] + $tally['failed'] + $tally['skipped'] + $tally['missing']
        );
    }

    public function test_evaluate_ignores_case_and_surrounding_space(): void {
        $tally = required_tests::evaluate(
            required_tests::parse('CalcTest#testAdd'),
            [self::result('  TESTADD ', 'PASSED', ' calctest ')]
        );

        $this->assertSame(1, $tally['passed']);
    }

    public function test_evaluate_matches_a_bare_name_in_any_suite(): void {
        $tally = required_tests::evaluate(
            required_tests::parse('testAdd'),
            [self::result('testAdd', 'PASSED', 'SomeOtherSuite')]
        );

        $this->assertSame(1, $tally['passed']);
    }

    public function test_evaluate_requires_the_suite_to_match_when_one_is_given(): void {
        $tally = required_tests::evaluate(
            required_tests::parse('CalcTest#testAdd'),
            [self::result('testAdd', 'PASSED', 'StringTest')]
        );

        $this->assertSame(['total' => 1, 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'missing' => 1], $tally);
    }

    public function test_evaluate_lets_a_failure_outweigh_a_pass_of_the_same_name(): void {
        $tally = required_tests::evaluate(
            required_tests::parse('testAdd'),
            [
                self::result('testAdd', 'PASSED', 'SuiteA'),
                self::result('testAdd', 'FAILED', 'SuiteB'),
            ]
        );

        $this->assertSame(1, $tally['failed']);
        $this->assertSame(0, $tally['passed']);
    }

    public function test_evaluate_counts_a_pass_over_a_skip_of_the_same_name(): void {
        $tally = required_tests::evaluate(
            required_tests::parse('testAdd'),
            [
                self::result('testAdd', 'SKIPPED', 'SuiteA'),
                self::result('testAdd', 'PASSED', 'SuiteB'),
            ]
        );

        $this->assertSame(1, $tally['passed']);
    }

    public function test_evaluate_accepts_array_rows(): void {
        $tally = required_tests::evaluate(
            required_tests::parse('testAdd'),
            [['testname' => 'testAdd', 'status' => 'PASSED', 'testsuite' => null]]
        );

        $this->assertSame(1, $tally['passed']);
    }

    public function test_evaluate_with_no_entries_is_empty(): void {
        $tally = required_tests::evaluate([], [self::result('testAdd', 'PASSED')]);

        $this->assertSame(['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'missing' => 0], $tally);
    }

    public function test_is_required_matches_evaluate(): void {
        $index = required_tests::index_entries(required_tests::parse("testAdd\nCalcTest#testMul"));

        $this->assertTrue(required_tests::is_required($index, null, 'testAdd'));
        $this->assertTrue(required_tests::is_required($index, 'AnySuite', 'testAdd'));
        $this->assertTrue(required_tests::is_required($index, 'calctest', ' TESTMUL '));
        $this->assertFalse(required_tests::is_required($index, 'StringTest', 'testMul'));
        $this->assertFalse(required_tests::is_required($index, null, 'testMul'));
        $this->assertFalse(required_tests::is_required($index, null, 'testUnknown'));
    }

    public function test_is_required_with_no_entries_matches_nothing(): void {
        $index = required_tests::index_entries([]);

        $this->assertFalse(required_tests::is_required($index, 'CalcTest', 'testAdd'));
    }
}
