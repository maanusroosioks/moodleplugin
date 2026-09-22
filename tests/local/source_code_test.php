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

namespace mod_idetestfeedback\local;

/**
 * Tests for reading the source code an IDE captured with a run.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\local\source_code
 */
final class source_code_test extends \basic_testcase {

    /** @var string A file of five numbered lines. */
    private const FILE = "one\ntwo\nthree\nfour\nfive\n";

    public function test_it_normalises_line_endings(): void {
        $this->assertSame("a\nb\nc", source_code::normalise_endings("a\r\nb\rc"));
    }

    public function test_it_drops_the_marker_an_ide_appends_to_a_string_it_cut(): void {
        $cut = "one\ntwo\n\u{2026} [truncated by moodle-test-submit: 1234 more characters]";

        $this->assertSame("one\ntwo", source_code::strip_marker($cut));
    }

    public function test_it_leaves_source_that_merely_mentions_truncation_alone(): void {
        $code = "assert log == '\u{2026} [truncated]'\n";

        $this->assertSame($code, source_code::strip_marker($code));
    }

    public function test_it_cuts_an_inclusive_one_based_range_out_of_a_file(): void {
        $this->assertSame(["two\nthree", false], source_code::excerpt(self::FILE, 2, 3));
    }

    public function test_a_single_line_range_is_one_line(): void {
        $this->assertSame(['one', false], source_code::excerpt(self::FILE, 1, 1));
    }

    public function test_a_range_running_past_the_end_of_a_cut_file_is_marked_truncated(): void {
        [$code, $truncated] = source_code::excerpt("one\ntwo\n\u{2026} [truncated by x: 9 more characters]", 2, 8);

        $this->assertSame('two', $code);
        $this->assertTrue($truncated);
    }

    public function test_a_test_below_a_cut_leaves_nothing_to_show(): void {
        $this->assertSame(['', true], source_code::excerpt("one\ntwo", 40, 50));
    }

    public function test_the_range_counts_lines_after_line_endings_are_normalised(): void {
        $this->assertSame(["two\nthree", false], source_code::excerpt("one\r\ntwo\r\nthree\r\nfour", 2, 3));
    }

    public function test_the_same_code_under_three_line_endings_hashes_alike(): void {
        $hash = source_code::hash("one\ntwo\nthree");

        $this->assertSame($hash, source_code::hash("one\r\ntwo\r\nthree"));
        $this->assertSame($hash, source_code::hash("one\rtwo\rthree"));
    }

    public function test_trailing_whitespace_does_not_change_the_hash(): void {
        $this->assertSame(source_code::hash("one\ntwo"), source_code::hash("one   \ntwo\t"));
    }

    public function test_a_final_newline_does_not_change_the_hash(): void {
        $this->assertSame(source_code::hash("one\ntwo"), source_code::hash("one\ntwo\n\n"));
    }

    public function test_a_cut_string_hashes_as_what_the_marker_left(): void {
        $cut = "one\ntwo\n\u{2026} [truncated by moodle-test-submit: 1234 more characters]";

        $this->assertSame(source_code::hash("one\ntwo"), source_code::hash($cut));
    }

    public function test_a_blank_line_inside_the_code_is_kept(): void {
        $this->assertNotSame(source_code::hash("one\ntwo"), source_code::hash("one\n\ntwo"));
    }

    public function test_an_edited_comment_is_a_different_hash(): void {
        $this->assertNotSame(
            source_code::hash("// adds two numbers\nassertEquals(3, add(1, 2));"),
            source_code::hash("// sums two numbers\nassertEquals(3, add(1, 2));")
        );
    }

    public function test_a_renamed_variable_is_a_different_hash(): void {
        $this->assertNotSame(
            source_code::hash('int sum = add(1, 2);'),
            source_code::hash('int total = add(1, 2);')
        );
    }

    public function test_reindented_code_is_a_different_hash(): void {
        $this->assertNotSame(
            source_code::hash("if (x) {\n    return 1;\n}"),
            source_code::hash("if (x) {\n\t\treturn 1;\n}")
        );
    }
}
