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
 * Restore task for the activity.
 *
 * @package    mod_idetestfeedback
 * @subpackage backup-moodle2
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/idetestfeedback/backup/moodle2/restore_idetestfeedback_stepslib.php');

/**
 * Provides the settings and steps to perform one complete restore of the activity.
 */
class restore_idetestfeedback_activity_task extends restore_activity_task {
    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * The activity has a single structure step.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_idetestfeedback_activity_structure_step(
            'idetestfeedback_structure',
            'idetestfeedback.xml'
        ));
    }

    /**
     * Defines the contents in the activity that must be decoded.
     *
     * @return restore_decode_content[] the fields the link decoder must process
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('idetestfeedback', ['intro'], 'idetestfeedback'),
        ];
    }

    /**
     * Defines the decoding rules for links belonging to the activity.
     *
     * @return restore_decode_rule[] the decoding rules for links into this activity
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule(
                'IDETESTFEEDBACKVIEWBYID',
                '/mod/idetestfeedback/view.php?id=$1',
                'course_module'
            ),
            new restore_decode_rule(
                'IDETESTFEEDBACKINDEX',
                '/mod/idetestfeedback/index.php?id=$1',
                'course'
            ),
        ];
    }

    /**
     * No legacy log rules: the activity only ever logs through events.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
