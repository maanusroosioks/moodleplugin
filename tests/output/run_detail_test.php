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

/**
 * Exposes the protected language mapping so it can be tested on its own.
 */
class testable_run_detail extends run_detail {

    /**
     * @param string $path the file the code came from
     * @return string the Prism language name
     */
    public static function language(string $path): string {
        return self::language_of($path);
    }
}

/**
 * Tests for the run detail renderable.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\output\run_detail
 */
final class run_detail_test extends \basic_testcase {

    /**
     * @return array[] [file path, expected Prism language]
     */
    public static function language_provider(): array {
        return [
            'python' => ['tests/test_calculator.py', 'python'],
            'java' => ['src/test/java/CalculatorTest.java', 'java'],
            'javascript' => ['spec/calculator.spec.js', 'javascript'],
            'c header shares the c grammar' => ['src/calc.h', 'c'],
            'c++ variant' => ['src/calc.cxx', 'cpp'],
            'html maps to the markup grammar' => ['page.html', 'markup'],
            'extension case is ignored' => ['Tests/CalculatorTest.PY', 'python'],
            'a path with dots keeps the last extension' => ['tests/test.calc.rb', 'ruby'],
            'a language with no bundled grammar' => ['tests/calculator_test.go', ''],
            'no extension at all' => ['Makefile', ''],
            'empty path' => ['', ''],
        ];
    }

    /**
     * @dataProvider language_provider
     * @param string $path the file the code came from
     * @param string $expected the Prism language it should be tagged with
     */
    public function test_language_is_taken_from_the_file_extension(string $path, string $expected): void {
        $this->assertSame($expected, testable_run_detail::language($path));
    }
}
