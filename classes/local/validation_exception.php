<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\local;

defined('MOODLE_INTERNAL') || die();

class validation_exception extends \moodle_exception {
    public function __construct(string $errorcode, $a = null) {
        parent::__construct($errorcode, 'mod_idetestfeedback', '', $a);
    }
}
