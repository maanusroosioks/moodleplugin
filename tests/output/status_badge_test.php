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
 * Tests for the status/run coloured badge.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\output\status_badge
 */
final class status_badge_test extends \basic_testcase {

    /**
     * @return array[] [status, expected row class]
     */
    public static function row_class_provider(): array {
        return [
            'passed' => ['PASSED', 'table-success'],
            'failed' => ['FAILED', 'table-danger'],
            'error' => ['ERROR', 'table-danger'],
            'skipped' => ['SKIPPED', 'table-warning'],
            'unknown' => ['BOGUS', ''],
        ];
    }

    /**
     * @dataProvider row_class_provider
     * @param string $status a status as stored on the run or result row
     * @param string $expected the expected row class
     */
    public function test_row_class(string $status, string $expected): void {
        $this->assertSame($expected, status_badge::row_class($status));
    }

    /**
     * @return \renderer_base a renderer double; export_for_template() never touches it
     */
    private function renderer(): \renderer_base {
        return $this->createMock(\renderer_base::class);
    }

    public function test_export_for_template_labels_the_badge_with_the_raw_status(): void {
        $exported = (new status_badge('PASSED'))->export_for_template($this->renderer());

        $this->assertSame('PASSED', $exported['label']);
    }

    /**
     * @return array[] [status, expected classes]
     */
    public static function classes_provider(): array {
        return [
            'passed' => ['PASSED', 'bg-success text-white'],
            'failed' => ['FAILED', 'bg-danger text-white'],
            'skipped' => ['SKIPPED', 'bg-secondary text-white'],
            'unknown' => ['BOGUS', 'bg-secondary text-white'],
        ];
    }

    /**
     * @dataProvider classes_provider
     * @param string $status a status as stored on the run or result row
     * @param string $expected the expected CSS classes
     */
    public function test_export_for_template_classes(string $status, string $expected): void {
        $exported = (new status_badge($status))->export_for_template($this->renderer());

        $this->assertSame($expected, $exported['classes']);
    }

    public function test_export_for_template_marks_error_apart_from_failed(): void {
        $error = (new status_badge('ERROR'))->export_for_template($this->renderer());
        $failed = (new status_badge('FAILED'))->export_for_template($this->renderer());

        $this->assertSame('bg-danger text-white idetestfeedback-badge-error', $error['classes']);
        $this->assertSame('bg-danger text-white', $failed['classes']);
    }
}
