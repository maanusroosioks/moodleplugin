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
 * Shows the test runs submitted to one IDE Test Feedback activity.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$view = new \mod_idetestfeedback\view(
    cmid: required_param('id', PARAM_INT),
    runid: optional_param('runid', 0, PARAM_INT),
    page: optional_param('page', 0, PARAM_INT),
    filteruserid: optional_param('filteruserid', 0, PARAM_INT),
    filterstatus: optional_param('filterstatus', '', PARAM_ALPHA),
    listpage: optional_param('listpage', 0, PARAM_INT),
);

$view->handle_post();
$view->render();
