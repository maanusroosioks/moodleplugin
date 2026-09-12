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
 * The web services this activity defines.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
