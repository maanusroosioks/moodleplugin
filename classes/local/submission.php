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
final class submission {
    /**
     * Holds a validated submission.
     *
     * @param string $email the student the middleware authenticated
     * @param string $assignmentkey the key shown on the activity
     * @param string $ide the IDE the run came from
     * @param string|null $projectname the project the tests ran in
     * @param string|null $commithash the commit the tests ran against
     * @param string|null $repourl the git remote the tests ran in, without credentials
     * @param int|null $startedat when the run started, in epoch milliseconds
     * @param int|null $finishedat when the run finished, in epoch milliseconds
     * @param array[] $results the test case results, statuses uppercased
     * @param array[] $testfiles the captured test files
     * @param bool $capturedisabled whether the student turned source capture off
     */
    public function __construct(
        /** @var string The student the middleware authenticated */
        public readonly string $email,
        /** @var string The key shown on the activity */
        public readonly string $assignmentkey,
        /** @var string The IDE the run came from */
        public readonly string $ide,
        /** @var string|null The project the tests ran in */
        public readonly ?string $projectname,
        /** @var string|null The commit the tests ran against */
        public readonly ?string $commithash,
        /** @var string|null The git remote the tests ran in, without credentials */
        public readonly ?string $repourl,
        /** @var int|null When the run started, in epoch milliseconds */
        public readonly ?int $startedat,
        /** @var int|null When the run finished, in epoch milliseconds */
        public readonly ?int $finishedat,
        /** @var array[] The test case results, statuses uppercased */
        public readonly array $results,
        /** @var array[] The captured test files */
        public readonly array $testfiles,
        /** @var bool Whether the student turned source capture off */
        public readonly bool $capturedisabled,
    ) {
    }
}
