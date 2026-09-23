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
 * How a test's status moved since a teacher last commented on it.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum feedback_outcome: string {
    // Failing when commented on, passing now.
    case FIXED = 'FIXED';

    // Failing when commented on and still failing.
    case STILLFAILING = 'STILLFAILING';

    // Passing when commented on, failing now.
    case REGRESSED = 'REGRESSED';

    /**
     * The outcome between two statuses.
     *
     * @param status $then the status the teacher commented on
     * @param status $now the status on the run being shown
     * @return self|null null when neither side failed, or either was skipped
     */
    public static function between(status $then, status $now): ?self {
        $failing = [status::FAILED, status::ERROR];
        $wasfailing = in_array($then, $failing, true);
        $isfailing = in_array($now, $failing, true);

        return match (true) {
            $wasfailing && $now === status::PASSED => self::FIXED,
            $wasfailing && $isfailing => self::STILLFAILING,
            $then === status::PASSED && $isfailing => self::REGRESSED,
            default => null,
        };
    }
}
