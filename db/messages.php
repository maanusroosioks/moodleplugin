<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Message providers for mod_idetestfeedback.
 *
 * @package mod_idetestfeedback
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // A teacher left feedback on a student's individual test results.
    'feedback' => [
        'capability' => 'mod/idetestfeedback:view',
        'defaults'   => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
