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
 * The settings form shown when adding or editing the activity.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_idetestfeedback_mod_form extends moodleform_mod {

    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('activityname', 'mod_idetestfeedback'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Show the auto-generated key when editing an existing instance.
        if (!empty($this->current->instance)) {
            $mform->addElement(
                'static',
                'assignmentkey_display',
                get_string('assignmentkey', 'mod_idetestfeedback'),
                html_writer::tag('code', s($this->current->assignmentkey)) .
                html_writer::tag('p', get_string('assignmentkey_help', 'mod_idetestfeedback'), ['class' => 'text-muted small mt-1'])
            );
        } else {
            $mform->addElement(
                'static',
                'assignmentkey_display',
                get_string('assignmentkey', 'mod_idetestfeedback'),
                get_string('assignmentkey_generated', 'mod_idetestfeedback')
            );
        }

        $this->standard_intro_elements();

        $mform->addElement('header', 'definedtestshdr', get_string('requiredtestsheading', 'mod_idetestfeedback'));

        $mform->addElement(
            'textarea',
            'requiredtests',
            get_string('requiredtests', 'mod_idetestfeedback'),
            ['rows' => 8, 'cols' => 60, 'class' => 'idetestfeedback-monospace']
        );
        $mform->setType('requiredtests', PARAM_RAW);
        $mform->addHelpButton('requiredtests', 'requiredtests', 'mod_idetestfeedback');

        $mform->addElement('header', 'submissionwindow', get_string('submissionwindow', 'mod_idetestfeedback'));

        $mform->addElement(
            'date_time_selector',
            'timeopen',
            get_string('timeopen', 'mod_idetestfeedback'),
            ['optional' => true]
        );
        $mform->setDefault('timeopen', 0);

        $mform->addElement(
            'date_time_selector',
            'timeclose',
            get_string('timeclose', 'mod_idetestfeedback'),
            ['optional' => true]
        );
        $mform->setDefault('timeclose', 0);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['timeopen']) && !empty($data['timeclose'])) {
            if ($data['timeclose'] <= $data['timeopen']) {
                $errors['timeclose'] = get_string('error_closebeforeopen', 'mod_idetestfeedback');
            }
        }

        return $errors;
    }

    public function add_completion_rules(): array {
        $mform = $this->_form;

        $mform->addElement(
            'checkbox',
            'completionpassrun',
            '',
            get_string('completionpassrun', 'mod_idetestfeedback')
        );

        return ['completionpassrun'];
    }

    public function completion_rule_enabled($data): bool {
        return !empty($data['completionpassrun']);
    }
}
