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
 * The core callbacks Moodle expects from an activity module.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\required_tests;

/**
 * Creates an activity instance, generating the key the IDE submits under.
 *
 * @param stdClass $data the submitted mod_form data
 * @param mod_idetestfeedback_mod_form|null $mform the form the data came from
 * @return int the new instance id
 */
function idetestfeedback_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->assignmentkey = bin2hex(random_bytes(16));
    $data->timeopen      = $data->timeopen  ?? 0;
    $data->timeclose     = $data->timeclose ?? 0;
    $data->requiredtests = required_tests::normalize($data->requiredtests ?? null);
    $data->timecreated   = time();
    $data->timemodified  = time();

    return $DB->insert_record('idetestfeedback', $data);
}

/**
 * Updates an activity instance. The assignment key is never reissued, so the
 * keys students already hold keep working.
 *
 * @param stdClass $data the submitted mod_form data
 * @param mod_idetestfeedback_mod_form|null $mform the form the data came from
 * @return bool
 */
function idetestfeedback_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->id            = $data->instance;
    $data->timeopen      = $data->timeopen  ?? 0;
    $data->timeclose     = $data->timeclose ?? 0;
    $data->requiredtests = required_tests::normalize($data->requiredtests ?? null);
    $data->timemodified  = time();

    return $DB->update_record('idetestfeedback', $data);
}

/**
 * Deletes an activity instance, with every run and result it holds.
 *
 * @param int $id the instance id
 * @return bool
 */
function idetestfeedback_delete_instance(int $id): bool {
    global $DB;

    (new repository($DB))->delete_instance($id);

    return true;
}

/**
 * Reports which optional module features this activity supports.
 *
 * @param string $feature a FEATURE_* constant
 * @return string|bool|null true or the feature's value when supported, null when not
 */
function idetestfeedback_supports(string $feature): string|bool|null {
    return match ($feature) {
        FEATURE_MOD_INTRO                => true,
        FEATURE_SHOW_DESCRIPTION         => true,
        FEATURE_BACKUP_MOODLE2           => true,
        FEATURE_COMPLETION_TRACKS_VIEWS  => true,
        FEATURE_COMPLETION_HAS_RULES     => true,
        FEATURE_MOD_PURPOSE              => MOD_PURPOSE_ASSESSMENT,
        FEATURE_GROUPS                   => true,
        FEATURE_GROUPINGS                => true,
        default                          => null,
    };
}

/**
 * Logs a view of the activity and marks it viewed for completion.
 *
 * @param stdClass $instance the activity instance
 * @param stdClass $course the course the activity is in
 * @param cm_info|stdClass $cm the course module
 * @param \core\context\module $context the activity context
 */
function idetestfeedback_view(stdClass $instance, stdClass $course, cm_info|stdClass $cm,
                              \core\context\module $context): void {
    global $CFG;
    require_once($CFG->libdir . '/completionlib.php');

    $event = \mod_idetestfeedback\event\course_module_viewed::create([
        'objectid' => $instance->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('idetestfeedback', $instance);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Surfaces the custom completion rule to the course page and completion reports.
 *
 * @param stdClass $coursemodule the course module being displayed
 * @return cached_cm_info|bool the cached info, or false when the instance is gone
 */
function idetestfeedback_get_coursemodule_info($coursemodule) {
    global $DB;

    $instance = $DB->get_record(
        'idetestfeedback',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, completionpassrun'
    );
    if (!$instance) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('idetestfeedback', $instance, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionpassrun'] = $instance->completionpassrun;
    }

    return $info;
}

/**
 * Adds this activity's options to the course reset form.
 *
 * @param MoodleQuickForm $mform the reset form
 */
function idetestfeedback_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'idetestfeedbackheader', get_string('modulenameplural', 'mod_idetestfeedback'));
    $mform->addElement('advcheckbox', 'reset_idetestfeedback', get_string('resetruns', 'mod_idetestfeedback'));
}

/**
 * @param stdClass $course the course being reset
 * @return array the default state of this activity's reset options
 */
function idetestfeedback_reset_course_form_defaults($course): array {
    return ['reset_idetestfeedback' => 1];
}

/**
 * Removes the submitted runs of every instance in the course, and shifts the
 * submission windows when the reset asks for a date shift.
 *
 * @param stdClass $data the submitted course reset form data
 * @return array[] one status row per action taken
 */
function idetestfeedback_reset_userdata($data): array {
    global $DB;

    $componentstr = get_string('modulenameplural', 'mod_idetestfeedback');
    $status = [];

    if (!empty($data->reset_idetestfeedback)) {
        $repository = new repository($DB);

        foreach ($repository->get_instance_ids_in_course((int) $data->courseid) as $instanceid) {
            $repository->delete_runs($instanceid);
        }

        $status[] = [
            'component' => $componentstr,
            'item' => get_string('resetruns', 'mod_idetestfeedback'),
            'error' => false,
        ];
    }

    // Any changes to the list of dates rolled here must match the restore step. See MDL-9367.
    if (!empty($data->timeshift)) {
        shift_course_mod_dates(
            'idetestfeedback',
            ['timeopen', 'timeclose'],
            $data->timeshift,
            $data->courseid
        );

        $status[] = [
            'component' => $componentstr,
            'item' => get_string('date'),
            'error' => false,
        ];
    }

    return $status;
}
