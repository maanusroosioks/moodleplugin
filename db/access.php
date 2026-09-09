<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'mod/idetestfeedback:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'mod/idetestfeedback:viewall' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'mod/idetestfeedback:comment' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'mod/idetestfeedback:submit' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    'mod/idetestfeedback:addinstance' => [
        'riskbitmask'           => RISK_SPAM | RISK_XSS,
        'captype'               => 'write',
        'contextlevel'          => CONTEXT_COURSE,
        'archetypes'            => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom'  => 'moodle/course:manageactivities',
    ],
];
