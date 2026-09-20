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

namespace mod_idetestfeedback\local;

/**
 * The discriminator of the source block the IDE sends with a test case result.
 *
 * It decides which of the other source fields are present and what the
 * normalised hash covers, so it is stored alongside the hash: a FILE hash and a
 * TEST hash describe different things and must never be compared with each other.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum source_kind: string {

    /** The test was not found in the project's source at all. */
    case NONE = 'NONE';

    /** The file was found but the test could not be picked out of it; the hash covers the whole file. */
    case FILE = 'FILE';

    /** The declaration was located; the line range and the hash cover just it. */
    case TEST = 'TEST';

    /**
     * @param string|null $kind a kind as the IDE reported it, in any case
     * @return self|null null when the IDE sent nothing, throwing is the caller's business
     */
    public static function resolve(?string $kind): ?self {
        if ($kind === null) {
            return null;
        }

        return self::tryFrom(\core_text::strtoupper(trim($kind)));
    }
}
