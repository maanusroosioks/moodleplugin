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
 * Tests for the result/run status enum.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\status::class)]
final class status_test extends \basic_testcase {
    public function test_any_sentinel_is_rejected_by_try_from(): void {
        $this->assertNull(status::tryFrom(status::ANY));
    }

    public function test_filter_options_lists_any_then_every_case_keyed_by_value(): void {
        $options = status::filter_options();

        $this->assertSame(
            [status::ANY, 'PASSED', 'FAILED', 'ERROR', 'SKIPPED'],
            array_keys($options)
        );
        $this->assertSame('PASSED', $options['PASSED']);
        $this->assertSame('FAILED', $options['FAILED']);
        $this->assertSame('ERROR', $options['ERROR']);
        $this->assertSame('SKIPPED', $options['SKIPPED']);
    }

    public function test_tally_counts_every_status_including_absent_ones(): void {
        $this->assertSame(
            ['PASSED' => 2, 'FAILED' => 1, 'ERROR' => 0, 'SKIPPED' => 0],
            status::tally(['PASSED', 'FAILED', 'PASSED'])
        );
    }

    public function test_worst_prefers_error_then_failed_then_passed(): void {
        $this->assertSame(status::ERROR, status::worst(status::tally(['PASSED', 'ERROR', 'FAILED'])));
        $this->assertSame(status::FAILED, status::worst(status::tally(['PASSED', 'SKIPPED', 'FAILED'])));
        $this->assertSame(status::PASSED, status::worst(status::tally(['SKIPPED', 'PASSED'])));
    }

    public function test_worst_is_skipped_when_nothing_ran(): void {
        $this->assertSame(status::SKIPPED, status::worst(status::tally(['SKIPPED'])));
        $this->assertSame(status::SKIPPED, status::worst(status::tally([])));
    }
}
