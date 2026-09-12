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

namespace mod_idetestfeedback\event;

/**
 * A test run was submitted from a student's IDE.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_run_submitted extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'idetestfeedback_run';
    }

    public static function get_name() {
        return get_string('event_test_run_submitted', 'mod_idetestfeedback');
    }

    public function get_description() {
        return "The web service account with id '{$this->userid}' submitted test run with id " .
            "'{$this->objectid}' (status {$this->other['status']}) for the user with id " .
            "'{$this->relateduserid}' in the idetestfeedback activity with course module id " .
            "'{$this->contextinstanceid}'.";
    }

    public function get_url() {
        return new \moodle_url('/mod/idetestfeedback/view.php', [
            'id'    => $this->contextinstanceid,
            'runid' => $this->objectid,
        ]);
    }

    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['status'])) {
            throw new \coding_exception('The \'status\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return ['db' => 'idetestfeedback_run', 'restore' => 'idetestfeedback_run'];
    }

    public static function get_other_mapping() {
        return false;
    }
}
