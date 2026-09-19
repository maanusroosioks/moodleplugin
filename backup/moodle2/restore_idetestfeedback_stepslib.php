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
 * Restore steps for the activity.
 *
 * @package    mod_idetestfeedback
 * @subpackage backup-moodle2
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one idetestfeedback activity.
 */
class restore_idetestfeedback_activity_structure_step extends restore_activity_structure_step {

    /**
     * @return array the paths to restore, wrapped for restore
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('idetestfeedback', '/activity/idetestfeedback');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'idetestfeedback_run',
                '/activity/idetestfeedback/runs/run'
            );
            $paths[] = new restore_path_element(
                'idetestfeedback_result',
                '/activity/idetestfeedback/runs/run/results/result'
            );
            $paths[] = new restore_path_element(
                'idetestfeedback_file',
                '/activity/idetestfeedback/runs/run/files/file'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity instance.
     *
     * @param array $data the backed up instance
     */
    protected function process_idetestfeedback($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates rolled here must match idetestfeedback_reset_userdata().
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        // The key is unique site-wide, so a duplicate of an activity that still
        // exists has to be issued its own. A restore onto a site that has never
        // seen this key keeps it, so the keys students already hold keep working.
        if (empty($data->assignmentkey)
                || $DB->record_exists('idetestfeedback', ['assignmentkey' => $data->assignmentkey])) {
            $data->assignmentkey = bin2hex(random_bytes(16));
        }

        $newitemid = $DB->insert_record('idetestfeedback', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores one submitted run.
     *
     * @param array $data the backed up run
     */
    protected function process_idetestfeedback_run($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->idetestfeedbackid = $this->get_new_parentid('idetestfeedback');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('idetestfeedback_run', $data);
        $this->set_mapping('idetestfeedback_run', $oldid, $newitemid);
    }

    /**
     * Restores one test case result.
     *
     * @param array $data the backed up result
     */
    protected function process_idetestfeedback_result($data) {
        global $DB;

        $data = (object) $data;

        $data->runid = $this->get_new_parentid('idetestfeedback_run');

        // Feedback outlives its author being anonymised or missing from the backup.
        $data->feedbackby = empty($data->feedbackby)
            ? null
            : ($this->get_mappingid('user', $data->feedbackby) ?: null);

        $DB->insert_record('idetestfeedback_result', $data);
    }

    /**
     * Restores one captured test file.
     *
     * @param array $data the backed up test file
     */
    protected function process_idetestfeedback_file($data) {
        global $DB;

        $data = (object) $data;

        $data->runid = $this->get_new_parentid('idetestfeedback_run');

        $DB->insert_record('idetestfeedback_file', $data);
    }

    /**
     * Reattaches the intro files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_idetestfeedback', 'intro', null);
    }
}
