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

namespace mod_idetestfeedback\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The form a teacher saves per test case feedback with.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
