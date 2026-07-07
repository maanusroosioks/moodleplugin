<?php
// This file is part of Moodle - http://moodle.org/

require('../../config.php');
require_once(__DIR__ . '/classes/view.php');

$id    = required_param('id',    PARAM_INT);
$runid = optional_param('runid', 0, PARAM_INT);

$view = new \mod_idetestfeedback\view($id, $runid);
$view->render();
