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

namespace mod_idetestfeedback\local;

use cm_info;
use core\message\message;
use core\output\html_writer;
use core\url;
use core_user;
use stdClass;

/**
 * Tells a student that new feedback is waiting on one of their runs.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_notifier {
    /**
     * Sets up a notifier for one activity.
     *
     * @param cm_info $cm the activity's course module
     */
    public function __construct(
        /** @var cm_info The activity's course module */
        protected readonly cm_info $cm
    ) {
    }

    /**
     * Tells the run's owner that feedback is waiting.
     *
     * @param stdClass $run the run that was commented on
     * @param stdClass[] $results the results that now carry new feedback
     * @param stdClass $author the teacher who wrote it
     * @return bool whether the message was accepted for delivery
     */
    public function notify(stdClass $run, array $results, stdClass $author): bool {
        $recipient = core_user::get_user((int) $run->userid, '*', MUST_EXIST);

        $rundetailurl = new url('/mod/idetestfeedback/view.php', [
            'id' => $this->cm->id,
            'runid' => $run->id,
        ]);

        $textlines = [get_string('feedbackmsgintro', 'mod_idetestfeedback'), ''];
        $htmlitems = '';

        foreach ($results as $result) {
            $label = $result->testsuite
                ? $result->testsuite . '#' . $result->testname
                : $result->testname;

            $textlines[] = $label;
            $textlines[] = $result->feedback;
            $textlines[] = '';

            $htmlitems .= html_writer::tag(
                'li',
                html_writer::tag('strong', s($label)) . html_writer::empty_tag('br') .
                    nl2br(s($result->feedback))
            );
        }

        $textlines[] = $rundetailurl->out(false);

        $message = new message();
        $message->component = 'mod_idetestfeedback';
        $message->name = 'feedback';
        $message->courseid = $this->cm->course;
        $message->userfrom = $author;
        $message->userto = $recipient;
        $message->subject = get_string(
            'feedbackmsgsubject',
            'mod_idetestfeedback',
            $this->cm->get_formatted_name()
        );
        $message->fullmessage = implode("\n", $textlines);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml =
            html_writer::tag('p', get_string('feedbackmsgintro', 'mod_idetestfeedback')) .
            html_writer::tag('ul', $htmlitems) .
            html_writer::tag('p', html_writer::link(
                $rundetailurl,
                get_string('rundetail', 'mod_idetestfeedback')
            ));
        $message->smallmessage = get_string('feedbackmsgsmall', 'mod_idetestfeedback');
        $message->notification = 1;
        $message->contexturl = $rundetailurl->out(false);
        $message->contexturlname = get_string('rundetail', 'mod_idetestfeedback');

        return (bool) message_send($message);
    }
}
