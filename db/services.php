<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_idetestfeedback_submit_test_run' => [
        'classname'   => 'mod_idetestfeedback\external\submit_test_run',
        'methodname'  => 'execute',
        'description' => 'Submit IDE test run results.',
        'type'        => 'write',
        'capabilities' => 'mod/idetestfeedback:submit',
        'ajax'        => false,
    ],
];

$services = [
    'IDE Test Results Service' => [
        'functions'       => ['mod_idetestfeedback_submit_test_run'],
        'restrictedusers' => 1,
        'enabled'         => 0,
        'shortname'       => 'ide_test_results',
    ],
];
