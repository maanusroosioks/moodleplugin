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
 * Where an activity stands against its optional timeopen / timeclose window.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum submission_window {
    case NOT_YET_OPEN;
    case OPEN;
    case CLOSED;

    /**
     * Where an activity stands at a given moment.
     *
     * @param \stdClass $instance the activity instance
     * @param int $now the moment to check
     * @return self
     */
    public static function of(\stdClass $instance, int $now): self {
        if ($instance->timeopen > 0 && $now < $instance->timeopen) {
            return self::NOT_YET_OPEN;
        }
        if ($instance->timeclose > 0 && $now > $instance->timeclose) {
            return self::CLOSED;
        }

        return self::OPEN;
    }
}
