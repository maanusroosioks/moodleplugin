<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_idetestfeedback activity was viewed.
 */
class course_module_viewed extends \core\event\course_module_viewed {

    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'idetestfeedback';
    }

    public static function get_objectid_mapping() {
        return ['db' => 'idetestfeedback', 'restore' => 'idetestfeedback'];
    }
}
