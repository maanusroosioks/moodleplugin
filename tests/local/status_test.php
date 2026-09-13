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
 * @covers     \mod_idetestfeedback\local\status
 */
final class status_test extends \basic_testcase {

    public function test_try_from_resolves_known_values(): void {
        $this->assertSame(status::PASSED, status::tryFrom('PASSED'));
        $this->assertSame(status::FAILED, status::tryFrom('FAILED'));
        $this->assertSame(status::ERROR, status::tryFrom('ERROR'));
        $this->assertSame(status::SKIPPED, status::tryFrom('SKIPPED'));
    }

    public function test_try_from_rejects_unknown_or_lowercase_values(): void {
        $this->assertNull(status::tryFrom('passed'));
        $this->assertNull(status::tryFrom('BOGUS'));
        $this->assertNull(status::tryFrom(''));
    }

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
}
