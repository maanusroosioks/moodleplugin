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
 * Backup steps for the activity.
 *
 * @package    mod_idetestfeedback
 * @subpackage backup-moodle2
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the complete idetestfeedback structure for backup.
 */
class backup_idetestfeedback_activity_structure_step extends backup_activity_structure_step {

    /**
     * @return backup_nested_element the activity structure, wrapped for backup
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $idetestfeedback = new backup_nested_element('idetestfeedback', ['id'], [
            'name', 'intro', 'introformat', 'assignmentkey', 'timeopen', 'timeclose',
            'timecreated', 'timemodified', 'completionpassrun', 'requiredtests',
        ]);

        $runs = new backup_nested_element('runs');
        $run = new backup_nested_element('run', ['id'], [
            'userid', 'ide', 'projectname', 'commithash', 'startedat', 'finishedat',
            'status', 'passedcount', 'failedcount', 'skippedcount', 'errorcount', 'timecreated',
        ]);

        $results = new backup_nested_element('results');
        $result = new backup_nested_element('result', ['id'], [
            'testsuite', 'testname', 'status', 'durationms', 'message', 'stacktracehash',
            'timecreated', 'feedback', 'feedbackformat', 'feedbackby', 'feedbackmodified',
        ]);

        $idetestfeedback->add_child($runs);
        $runs->add_child($run);
        $run->add_child($results);
        $results->add_child($result);

        $idetestfeedback->set_source_table('idetestfeedback', ['id' => backup::VAR_ACTIVITYID]);

        // A run and its results are the students' data, so they only travel with user info.
        if ($userinfo) {
            $run->set_source_table('idetestfeedback_run', ['idetestfeedbackid' => backup::VAR_PARENTID], 'id ASC');
            $result->set_source_table('idetestfeedback_result', ['runid' => backup::VAR_PARENTID], 'id ASC');
        }

        $run->annotate_ids('user', 'userid');
        $result->annotate_ids('user', 'feedbackby');

        $idetestfeedback->annotate_files('mod_idetestfeedback', 'intro', null);

        return $this->prepare_activity_structure($idetestfeedback);
    }
}
