<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\event;

defined('MOODLE_INTERNAL') || die();


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
