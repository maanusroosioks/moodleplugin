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
 * Lists every IDE Test Feedback activity in a course.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
