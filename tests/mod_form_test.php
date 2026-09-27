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

namespace mod_idetestfeedback;

use mod_idetestfeedback_mod_form;

/**
 * Tests for the activity settings form.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback_mod_form::class)]
final class mod_form_test extends \advanced_testcase {
    /** @var mod_idetestfeedback_mod_form */
    private mod_idetestfeedback_mod_form $form;

    #[\Override]
    protected function setUp(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/idetestfeedback/mod_form.php');

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $PAGE->set_course($course);
        [, , $cw, $cm, $data] = prepare_new_moduleinfo_data($course, 'idetestfeedback', 0);
        $this->form = new mod_idetestfeedback_mod_form($data, $cw->section, $cm, $course);
    }

    /**
     * Validates the form with the given submission window.
     *
     * @param int $timeopen the opening date, or 0 for none
     * @param int $timeclose the closing date, or 0 for none
     * @return array the validation errors
     */
    private function errors(int $timeopen, int $timeclose): array {
        return $this->form->validation([
            'name' => 'A test activity',
            'modulename' => 'idetestfeedback',
            'instance' => 0,
            'coursemodule' => 0,
            'cmidnumber' => '',
            'availabilityconditionsjson' => '',
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
        ], []);
    }

    public function test_a_window_closing_before_it_opens_is_rejected(): void {
        $this->assertSame(
            get_string('closebeforeopen', 'mod_idetestfeedback'),
            $this->errors(2000, 1000)['timeclose'] ?? null
        );
    }

    public function test_a_window_closing_as_it_opens_is_rejected(): void {
        $this->assertArrayHasKey('timeclose', $this->errors(1000, 1000));
    }

    public function test_a_window_closing_after_it_opens_is_accepted(): void {
        $this->assertArrayNotHasKey('timeclose', $this->errors(1000, 2000));
    }

    public function test_a_window_open_at_one_end_is_accepted(): void {
        $this->assertArrayNotHasKey('timeclose', $this->errors(2000, 0));
        $this->assertArrayNotHasKey('timeclose', $this->errors(0, 1000));
    }

    public function test_unticking_the_passing_run_rule_switches_it_off(): void {
        $data = (object) ['completionunlocked' => 1, 'completion' => COMPLETION_TRACKING_MANUAL];

        $this->form->data_postprocessing($data);

        $this->assertSame(0, $data->completionpassrun);
    }

    public function test_a_ticked_passing_run_rule_is_kept(): void {
        $data = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpassrun' => 1,
        ];

        $this->form->data_postprocessing($data);

        $this->assertSame(1, $data->completionpassrun);
    }
}
