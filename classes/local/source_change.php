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
 * How a hash compares against the same hash on an earlier run.
 *
 * UNCHANGED is the strong verdict and CHANGED the weak one: a match is byte for
 * byte over the region the hash covers, while a difference can be a reformat.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum source_change: string {
    // Nothing to compare: no earlier occurrence, a missing hash, or mismatched kinds.
    case UNKNOWN = 'UNKNOWN';

    // Both hashes are present and equal.
    case UNCHANGED = 'UNCHANGED';

    // Both hashes are present and differ.
    case CHANGED = 'CHANGED';
}
