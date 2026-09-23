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
 * One test run as the IDE submitted it, after parameter validation.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final readonly class submission {

    /**
     * @param string $email the student the middleware authenticated
     * @param string $assignmentkey the key shown on the activity
     * @param string $ide the IDE the run came from
     * @param string|null $projectname the project the tests ran in
     * @param string|null $commithash the commit the tests ran against
     * @param int|null $startedat when the run started, in epoch milliseconds
     * @param int|null $finishedat when the run finished, in epoch milliseconds
     * @param array[] $results the test case results, statuses uppercased
     * @param array[] $testfiles the captured test files
     * @param bool $capturedisabled whether the student turned source capture off
     * @param bool $warningacknowledged whether the student submitted past the empty-test warning
     */
    public function __construct(
        public string $email,
        public string $assignmentkey,
        public string $ide,
        public ?string $projectname,
        public ?string $commithash,
        public ?int $startedat,
        public ?int $finishedat,
        public array $results,
        public array $testfiles,
        public bool $capturedisabled,
        public bool $warningacknowledged,
    ) {
    }
}
