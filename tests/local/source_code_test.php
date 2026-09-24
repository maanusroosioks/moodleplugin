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
 * Tests for reading the source code an IDE captured with a run.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\source_code::class)]
final class source_code_test extends \basic_testcase {
    /** @var string A file of five numbered lines. */
    private const FILE = "one\ntwo\nthree\nfour\nfive\n";

    public function test_it_leaves_source_that_merely_mentions_truncation_alone(): void {
        $code = "assert log == '\u{2026} [truncated]'";

        $this->assertSame($code, source_code::canonicalise($code . "\n"));
    }

    public function test_it_cuts_an_inclusive_one_based_range_out_of_a_file(): void {
        $this->assertSame(["two\nthree", false], source_code::excerpt(self::FILE, 2, 3));
    }

    public function test_a_single_line_range_is_one_line(): void {
        $this->assertSame(['one', false], source_code::excerpt(self::FILE, 1, 1));
    }

    public function test_a_range_running_past_the_end_of_a_cut_file_is_marked_truncated(): void {
        $cut = source_code::canonicalise("one\ntwo\n\u{2026} [truncated by x: 9 more characters]");
        [$code, $truncated] = source_code::excerpt($cut, 2, 8);

        $this->assertSame('two', $code);
        $this->assertTrue($truncated);
    }

    public function test_a_test_below_a_cut_leaves_nothing_to_show(): void {
        $this->assertSame(['', true], source_code::excerpt("one\ntwo", 40, 50));
    }

    public function test_the_range_counts_lines_after_line_endings_are_normalised(): void {
        $content = source_code::canonicalise("one\r\ntwo\r\nthree\r\nfour");

        $this->assertSame(["two\nthree", false], source_code::excerpt($content, 2, 3));
    }

    public function test_the_same_code_under_three_line_endings_is_canonically_alike(): void {
        $canonical = source_code::canonicalise("one\ntwo\nthree");

        $this->assertSame($canonical, source_code::canonicalise("one\r\ntwo\r\nthree"));
        $this->assertSame($canonical, source_code::canonicalise("one\rtwo\rthree"));
    }

    public function test_trailing_whitespace_is_not_canonical(): void {
        $this->assertSame("one\ntwo", source_code::canonicalise("one   \ntwo\t"));
    }

    public function test_a_final_newline_is_not_canonical(): void {
        $this->assertSame("one\ntwo", source_code::canonicalise("one\ntwo\n\n"));
    }

    public function test_a_byte_order_mark_is_not_canonical(): void {
        $this->assertSame("one\ntwo", source_code::canonicalise("\u{FEFF}one\ntwo"));
    }

    public function test_a_cut_string_canonicalises_to_what_the_marker_left(): void {
        $cut = "one\ntwo\n\u{2026} [truncated by moodle-test-submit: 1234 more characters]";

        $this->assertSame("one\ntwo", source_code::canonicalise($cut));
    }

    public function test_hash_canonical_hashes_its_input_as_is(): void {
        $this->assertSame(hash('sha256', "one  \r\n"), source_code::hash_canonical("one  \r\n"));
    }

    public function test_invalid_utf8_is_kept_rather_than_emptied(): void {
        $this->assertSame("caf\xE9\nbar", source_code::canonicalise("caf\xE9\r\nbar  \n"));
    }

    public function test_a_blank_line_inside_the_code_is_kept(): void {
        $this->assertSame("one\n\ntwo", source_code::canonicalise("one\n\ntwo"));
    }

    public function test_comments_names_and_indentation_are_kept(): void {
        $samples = [
            "// adds two numbers\nassertEquals(3, add(1, 2));",
            'int sum = add(1, 2);',
            "if (x) {\n\t\treturn 1;\n}",
        ];

        foreach ($samples as $code) {
            $this->assertSame($code, source_code::canonicalise($code));
        }
    }
}
