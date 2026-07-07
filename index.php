<?php
// This file is part of Moodle - http://moodle.org/
// Lists all idetestfeedback instances in a course.

require('../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/idetestfeedback/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_idetestfeedback'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_idetestfeedback'));

$instances = get_all_instances_in_course('idetestfeedback', $course);

if (empty($instances)) {
    echo $OUTPUT->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'table table-bordered table-sm';
$table->head = [
    get_string('activityname', 'mod_idetestfeedback'),
    get_string('assignmentkey', 'mod_idetestfeedback'),
    get_string('timeopen',     'mod_idetestfeedback'),
    get_string('timeclose',    'mod_idetestfeedback'),
];

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/idetestfeedback/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name)
    );

    $table->data[] = [
        $link,
        html_writer::tag('code', s($instance->assignmentkey)),
        $instance->timeopen  ? userdate($instance->timeopen)  : '—',
        $instance->timeclose ? userdate($instance->timeclose) : '—',
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
