<?php

require('../../config.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moodleplugin/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_moodleplugin'));
$PAGE->set_heading(get_string('pluginname', 'local_moodleplugin'));

echo $OUTPUT->header();

echo '<h1>Hello Moodle</h1>';

echo $OUTPUT->footer();