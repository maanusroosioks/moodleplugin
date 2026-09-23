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
 * Derived from the result rather than stored: what a result names is what it
 * found. A FILE hash and a TEST hash describe different things, so the two are
 * never compared with each other.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum source_kind: string {
    // The test was not found in the project's source at all.
    case NONE = 'NONE';

    // The file was found but the test could not be picked out of it; the hash covers the whole file.
    case FILE = 'FILE';

    // The declaration was located; the line range and the hash cover just it.
    case TEST = 'TEST';

    /**
     * What a result's source columns describe.
     *
     * @param \stdClass $result a test case result carrying the source columns
     * @return self
     */
    public static function of(\stdClass $result): self {
        return self::from_parts(
            $result->sourcefilepath ?? null,
            $result->sourcestartline ?? null,
            $result->sourceendline ?? null
        );
    }

    /**
     * What a set of source columns describes.
     *
     * @param string|null $filepath the file the test was found in, if any
     * @param int|null $startline the declaration's first line, if located
     * @param int|null $endline the declaration's last line, if located
     * @return self
     */
    public static function from_parts(?string $filepath, ?int $startline, ?int $endline): self {
        if (trim((string) $filepath) === '') {
            return self::NONE;
        }

        return $startline === null || $endline === null ? self::FILE : self::TEST;
    }
}
