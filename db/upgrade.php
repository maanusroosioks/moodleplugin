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

/**
 * Database upgrade steps for the activity.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Runs the upgrade steps for this activity.
 *
 * @param int $oldversion the currently installed version
 * @return bool
 */
function xmldb_idetestfeedback_upgrade($oldversion): bool {
    // Pre-release builds had a different schema and no path forward from it.
    if ($oldversion < 2026092300) {
        throw new \core\exception\moodle_exception('upgradefromprerelease', 'mod_idetestfeedback');
    }

    return true;
}
