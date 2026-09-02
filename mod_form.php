<?php
// This file is part of Moodle - http://moodle.org/

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
