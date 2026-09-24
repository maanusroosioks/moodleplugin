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
 * How much of a file a test case result points at.
 *
 * Derived from the result rather than stored: what a result names is what it found.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum source_kind {
    // The test was not found in the project's source at all.
    case NONE;

    // The file was found but the test could not be picked out of it.
    case FILE;

    // The declaration was located; the line range covers just it.
    case TEST;

    /**
     * What a result's source columns describe.
     *
     * @param \stdClass $result a test case result carrying the source columns
     * @return self
     */
    public static function of(\stdClass $result): self {
        if (trim((string) ($result->sourcefilepath ?? '')) === '') {
            return self::NONE;
        }

        return ($result->sourcestartline ?? null) === null || ($result->sourceendline ?? null) === null
            ? self::FILE
            : self::TEST;
    }
}
