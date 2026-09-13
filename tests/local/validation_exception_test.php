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
 * Tests for the exception raised when a submitted test run is rejected.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\local\validation_exception
 */
final class validation_exception_test extends \advanced_testcase {

    public function test_it_resolves_the_named_language_string(): void {
        $exception = new validation_exception('validation_noresults');

        $this->assertSame(get_string('validation_noresults', 'mod_idetestfeedback'), $exception->getMessage());
    }

    public function test_it_passes_through_the_placeholder_value(): void {
        $exception = new validation_exception('validation_toomanyresults', 2000);

        $this->assertSame(
            get_string('validation_toomanyresults', 'mod_idetestfeedback', 2000),
            $exception->getMessage()
        );
    }

    public function test_it_is_a_moodle_exception(): void {
        $this->assertInstanceOf(\moodle_exception::class, new validation_exception('validation_noide'));
    }
}
