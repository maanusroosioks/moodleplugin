<?php
namespace mod_idetestfeedback\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class feedback_form extends moodleform {

    /**
     * Defines the form.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('html', $this->_customdata['resultstable']);

        $mform->addElement(
            'advcheckbox',
            'notify',
            '',
            get_string('notifystudent', 'mod_idetestfeedback')
        );
        $mform->setType('notify', PARAM_BOOL);

        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'runid', $this->_customdata['runid']);
        $mform->setType('runid', PARAM_INT);

        $mform->addElement('hidden', 'savefeedback', 1);
        $mform->setType('savefeedback', PARAM_INT);

        $this->add_action_buttons(false, get_string('savefeedback', 'mod_idetestfeedback'));
    }
}
