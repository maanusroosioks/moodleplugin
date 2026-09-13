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
 * Backup task for the activity.
 *
 * @package    mod_idetestfeedback
 * @subpackage backup-moodle2
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/idetestfeedback/backup/moodle2/backup_idetestfeedback_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the activity instance.
 */
class backup_idetestfeedback_activity_task extends backup_activity_task {

    /**
     * No settings of its own.
     */
    protected function define_my_settings() {
    }

    /**
     * Stores the instance data in idetestfeedback.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_idetestfeedback_activity_structure_step(
            'idetestfeedback_structure',
            'idetestfeedback.xml'
        ));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts.
     *
     * @param string $content HTML that may contain links to this activity
     * @return string the content with those links encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $content = preg_replace(
            "/({$base}\/mod\/idetestfeedback\/index.php\?id\=)([0-9]+)/",
            '$@IDETESTFEEDBACKINDEX*$2@$',
            $content
        );

        $content = preg_replace(
            "/({$base}\/mod\/idetestfeedback\/view.php\?id\=)([0-9]+)/",
            '$@IDETESTFEEDBACKVIEWBYID*$2@$',
            $content
        );

        return $content;
    }
}
