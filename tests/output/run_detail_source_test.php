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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/testable_run_detail_source.php');

/**
 * Tests for how the run detail finds the code a test case ran.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\output\run_detail::class)]
final class run_detail_source_test extends \advanced_testcase {
    /** @var string A file whose test declaration sits on lines 3 and 4. */
    private const FILE = "import pytest\n\ndef test_add():\n    assert add(2, 3) == 5\n";

    /**
     * Builds a result row.
     *
     * @param array $source the stored source columns of one result
     * @return \stdClass a result row
     */
    private function testresult(array $source = []): \stdClass {
        return (object) array_merge([
            'id' => 1,
            'testname' => 'test_add',
            'sourcefilepath' => 'tests/test_calculator.py',
            'sourcestartline' => 3,
            'sourceendline' => 4,
        ], $source);
    }

    /**
     * Builds the run detail under test.
     *
     * @param array $files the stored file rows of the run
     * @return testable_run_detail_source
     */
    private function detail(array $files): testable_run_detail_source {
        return new testable_run_detail_source(
            run: (object) [],
            results: [],
            files: $files,
            studentname: null,
            context: \context_system::instance(),
            cancomment: false,
            history: new \mod_idetestfeedback\local\feedback_history([]),
            backurl: new \core\url('/'),
            formurl: new \core\url('/')
        );
    }

    /**
     * Builds a file row.
     *
     * @param array $file the stored columns of one file row
     * @return \stdClass a file row
     */
    private function file(array $file = []): \stdClass {
        return (object) array_merge([
            'id' => 1,
            'path' => 'tests/test_calculator.py',
            'content' => self::FILE,
            'truncated' => 0,
        ], $file);
    }

    public function test_a_test_body_is_cut_out_of_the_run_file_it_lives_in(): void {
        $block = $this->detail([$this->file()])->block($this->testresult());

        $this->assertSame("def test_add():\n    assert add(2, 3) == 5", $block['code']);
        $this->assertSame("3\n4", $block['linenumbers']);
        $this->assertFalse($block['truncated']);
        $this->assertTrue($block['expandable']);
    }

    public function test_files_past_the_inline_budget_link_to_their_own_page(): void {
        $large = str_repeat("x\n", run_detail::INLINE_FILE_BYTES / 2);

        $rows = $this->detail([
            $this->file(['id' => 4, 'path' => 'a.py', 'content' => $large]),
            $this->file(['id' => 5, 'path' => 'b.py']),
        ])->files();

        $this->assertTrue($rows[0]['inline']);
        $this->assertSame($large, $rows[0]['content']);

        $this->assertFalse($rows[1]['inline']);
        $this->assertTrue($rows[1]['hascontent']);
        $this->assertSame('', $rows[1]['content']);
        $this->assertSame('', $rows[1]['linenumbers']);
        $this->assertStringContainsString('fileid=5', $rows[1]['fileurl']);
    }

    public function test_a_short_run_is_timed_in_milliseconds_and_a_long_one_in_minutes(): void {
        $this->assertSame('250 ms', testable_run_detail_source::duration(250));
        $this->assertSame(format_time(125), testable_run_detail_source::duration(125000));
    }

    public function test_a_body_the_run_did_not_capture_shows_nothing(): void {
        $block = $this->detail([$this->file(['content' => null])])->block($this->testresult());

        $this->assertSame('', $block['code']);
        $this->assertFalse($block['hascode']);
    }

    public function test_a_body_below_the_cut_in_a_truncated_file_shows_nothing(): void {
        $block = $this->detail([$this->file(['content' => "import pytest\n", 'truncated' => 1])])
            ->block($this->testresult());

        $this->assertSame('', $block['code']);
        $this->assertFalse($block['hascode']);
    }

    public function test_a_run_with_capture_off_still_says_where_the_test_lives(): void {
        $block = $this->detail([])->block($this->testresult());

        $this->assertSame('tests/test_calculator.py:3-4', $block['summary']);
        $this->assertFalse($block['expandable']);
        $this->assertFalse($block['hascode']);
    }

    public function test_a_test_that_was_never_found_has_nothing_to_open(): void {
        $block = $this->detail([$this->file()])->block($this->testresult([
            'sourcefilepath' => null,
            'sourcestartline' => null,
            'sourceendline' => null,
        ]));

        $this->assertFalse($block['expandable']);
        $this->assertSame(get_string('sourcenotfound', 'mod_idetestfeedback'), $block['summary']);
    }

    public function test_a_file_the_test_could_not_be_picked_out_of_is_not_excerpted(): void {
        $block = $this->detail([$this->file()])->block($this->testresult([
            'sourcestartline' => null,
            'sourceendline' => null,
        ]));

        $this->assertSame('', $block['code']);
        $this->assertTrue($block['wholefile']);
        $this->assertTrue($block['expandable']);
    }

    public function test_a_file_kind_with_capture_off_does_not_promise_a_file_below(): void {
        $block = $this->detail([$this->file(['content' => null])])->block($this->testresult([
            'sourcestartline' => null,
            'sourceendline' => null,
        ]));

        $this->assertFalse($block['wholefile']);
        $this->assertFalse($block['expandable']);
    }

    public function test_a_result_the_ide_said_nothing_about_has_no_block(): void {
        $this->assertNull($this->detail([])->block($this->testresult([
            'sourcefilepath' => null,
            'sourcestartline' => null,
            'sourceendline' => null,
        ])));
    }
}
