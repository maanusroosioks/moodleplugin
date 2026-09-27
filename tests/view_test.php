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

use core\exception\moodle_exception;
use core\exception\required_capability_exception;
use mod_idetestfeedback\local\repository;

/**
 * Tests for the controller behind view.php: who may see which runs, and who may leave feedback.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\view::class)]
final class view_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $instance;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $teacher;

    /** @var repository */
    private repository $repository;

    #[\Override]
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->repository = new repository($DB);
    }

    #[\Override]
    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    /**
     * Stores a one-test run with one captured file.
     *
     * @param \stdClass $user the student the run belongs to
     * @param string $status the test's status
     * @param \stdClass|null $instance the activity, or null for the default one
     * @return \stdClass the stored run
     */
    private function run_for(\stdClass $user, string $status = 'PASSED', ?\stdClass $instance = null): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => ($instance ?? $this->instance)->id,
            'userid' => $user->id,
            'results' => [['testname' => 'testAdd', 'status' => $status]],
            'files' => [['path' => 'tests/test_calculator.py', 'content' => "def testAdd():\n    pass\n"]],
        ]);
    }

    /**
     * Switches the default activity for one in separate groups mode, with a student in each of two groups.
     *
     * @return array{0:\stdClass,1:\stdClass} [the non-editing teacher in group A, the student in group B]
     */
    private function use_separate_groups(): array {
        $generator = $this->getDataGenerator();
        $this->instance = $generator->create_module('idetestfeedback', [
            'course' => $this->course->id,
            'groupmode' => SEPARATEGROUPS,
        ]);

        $groupa = $generator->create_group(['courseid' => $this->course->id]);
        $groupb = $generator->create_group(['courseid' => $this->course->id]);
        $teacher = $generator->create_and_enrol($this->course, 'teacher');
        $other = $generator->create_and_enrol($this->course, 'student');

        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $this->student->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $other->id]);

        return [$teacher, $other];
    }

    /**
     * Builds the controller for the default activity.
     *
     * @param array $params named constructor arguments other than the course module id
     * @return view
     */
    private function view(array $params = []): view {
        return new view($this->instance->cmid, ...$params);
    }

    /**
     * Renders the page.
     *
     * @param view $view
     * @return string the page HTML
     */
    private function render(view $view): string {
        ob_start();
        try {
            $view->render();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * Posts the feedback form to the controller.
     *
     * @param view $view
     * @param array $post form fields to add to or override the defaults with
     * @return bool whether the controller redirected
     */
    private function post(view $view, array $post): bool {
        $_POST = $post + ['savefeedback' => 1, 'sesskey' => sesskey()];

        try {
            $view->handle_post();
        } catch (moodle_exception $e) {
            if ($e->errorcode === 'redirecterrordetected') {
                return true;
            }
            throw $e;
        }

        return false;
    }

    /**
     * The feedback stored on a run's only result.
     *
     * @param \stdClass $run the run
     * @return \stdClass the result, carrying its feedback columns
     */
    private function result_of(\stdClass $run): \stdClass {
        $results = $this->repository->get_results($run->id);

        return reset($results);
    }

    /**
     * Whether the page links to a run's detail.
     *
     * @param string $html the page
     * @param \stdClass $run the run
     * @return bool
     */
    private function links_to(string $html, \stdClass $run): bool {
        return (bool) preg_match('/runid=' . $run->id . '(?!\d)/', $html);
    }

    /**
     * Opens a run's page as a user.
     *
     * @param \stdClass $user the viewer
     * @param \stdClass $run the run to open
     * @param array $params other named constructor arguments, e.g. 'fileid'
     * @return view
     */
    private function open_as(\stdClass $user, \stdClass $run, array $params = []): view {
        $this->setUser($user);

        return $this->view(['runid' => $run->id] + $params);
    }

    /**
     * Renders the run list as a user.
     *
     * @param \stdClass $user the viewer
     * @param array $params named constructor arguments other than the course module id
     * @return string the page HTML
     */
    private function page_as(\stdClass $user, array $params = []): string {
        $this->setUser($user);

        return $this->render($this->view($params));
    }

    /**
     * Posts feedback on a run's only result.
     *
     * @param view $view the controller, opened on the run
     * @param \stdClass $run the run
     * @param string $feedback the feedback to post
     * @param array $post other form fields to add or override
     * @return bool whether the controller redirected
     */
    private function post_feedback(view $view, \stdClass $run, string $feedback, array $post = []): bool {
        return $this->post($view, $post + ['feedback' => [$this->result_of($run)->id => $feedback]]);
    }

    /**
     * Asserts an action is refused with the given error.
     *
     * @param string $errorcode the expected moodle_exception error code
     * @param callable $action the action to attempt
     */
    private function assert_refused(string $errorcode, callable $action): void {
        try {
            $action();
        } catch (moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
            return;
        }

        $this->fail("The action was not refused with {$errorcode}");
    }

    /**
     * Takes a capability away from a role in the activity.
     *
     * @param string $capability the capability
     * @param string $role the role's short name
     */
    private function prohibit(string $capability, string $role): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => $role], MUST_EXIST);
        assign_capability($capability, CAP_PROHIBIT, $roleid, \context_module::instance($this->instance->cmid));
    }

    public function test_a_student_can_open_their_own_run(): void {
        $html = $this->render($this->open_as($this->student, $this->run_for($this->student)));

        $this->assertStringContainsString('testAdd', $html);
    }

    public function test_a_student_cannot_open_another_students_run(): void {
        $run = $this->run_for($this->getDataGenerator()->create_and_enrol($this->course, 'student'));

        $this->expectException(required_capability_exception::class);

        $this->open_as($this->student, $run);
    }

    public function test_a_student_cannot_open_a_file_of_another_students_run(): void {
        $run = $this->run_for($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
        $file = current($this->repository->get_files($run->id));

        $this->expectException(required_capability_exception::class);

        $this->open_as($this->student, $run, ['fileid' => $file->id]);
    }

    public function test_a_user_without_the_view_capability_is_sent_back_to_the_course(): void {
        $this->prohibit('mod/idetestfeedback:view', 'student');
        $this->setUser($this->student);

        $this->assert_refused('redirecterrordetected', fn() => $this->view());
    }

    public function test_a_teacher_can_open_any_students_run(): void {
        $html = $this->render($this->open_as($this->teacher, $this->run_for($this->student)));

        $this->assertStringContainsString('testAdd', $html);
        $this->assertStringContainsString(fullname($this->student), $html);
    }

    public function test_a_teacher_cannot_open_a_run_from_another_group(): void {
        [$teacher, $other] = $this->use_separate_groups();
        $run = $this->run_for($other);

        $this->assert_refused('notingroup', fn() => $this->open_as($teacher, $run));
    }

    public function test_a_teacher_can_open_a_run_from_their_own_group(): void {
        [$teacher] = $this->use_separate_groups();

        $html = $this->render($this->open_as($teacher, $this->run_for($this->student)));

        $this->assertStringContainsString('testAdd', $html);
    }

    public function test_a_run_of_another_activity_is_not_found(): void {
        $otherinstance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $run = $this->run_for($this->student, 'PASSED', $otherinstance);

        $html = $this->render($this->open_as($this->teacher, $run));

        $this->assertStringContainsString(get_string('runnotfound', 'mod_idetestfeedback'), $html);
        $this->assertStringNotContainsString('testAdd', $html);
    }

    public function test_a_file_of_another_run_is_not_found(): void {
        $run = $this->run_for($this->student);
        $file = current($this->repository->get_files($this->run_for($this->student)->id));

        $html = $this->render($this->open_as($this->student, $run, ['fileid' => $file->id]));

        $this->assertStringContainsString(get_string('filenotfound', 'mod_idetestfeedback'), $html);
    }

    public function test_a_page_without_the_feedback_form_posted_saves_nothing(): void {
        $run = $this->run_for($this->student);

        $redirected = $this->post_feedback($this->open_as($this->teacher, $run), $run, 'Well done', ['savefeedback' => 0]);

        $this->assertFalse($redirected);
        $this->assertNull($this->result_of($run)->feedback);
    }

    public function test_feedback_is_refused_without_a_valid_sesskey(): void {
        $run = $this->run_for($this->student);
        $view = $this->open_as($this->teacher, $run);

        $this->assert_refused('invalidsesskey', fn() => $this->post_feedback($view, $run, 'Well done', ['sesskey' => 'forged']));
        $this->assertNull($this->result_of($run)->feedback);
    }

    public function test_a_student_cannot_leave_feedback_on_their_own_run(): void {
        $run = $this->run_for($this->student);
        $view = $this->open_as($this->student, $run);

        $this->assert_refused('nopermissions', fn() => $this->post_feedback($view, $run, 'Looks fine to me'));
        $this->assertNull($this->result_of($run)->feedback);
    }

    public function test_a_teacher_without_the_comment_capability_cannot_leave_feedback(): void {
        $this->prohibit('mod/idetestfeedback:comment', 'editingteacher');
        $run = $this->run_for($this->student);
        $view = $this->open_as($this->teacher, $run);

        $this->assert_refused('nopermissions', fn() => $this->post_feedback($view, $run, 'Well done'));
        $this->assertNull($this->result_of($run)->feedback);
    }

    public function test_a_teacher_saves_feedback_and_is_sent_back_to_the_run(): void {
        $run = $this->run_for($this->student, 'FAILED');

        $redirected = $this->post_feedback($this->open_as($this->teacher, $run), $run, 'Check the edge cases');

        $this->assertTrue($redirected);
        $result = $this->result_of($run);
        $this->assertSame('Check the edge cases', $result->feedback);
        $this->assertSame((int) $this->teacher->id, (int) $result->feedbackby);
    }

    public function test_feedback_for_a_run_of_another_activity_is_not_saved(): void {
        $otherinstance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $run = $this->run_for($this->student, 'FAILED', $otherinstance);

        $redirected = $this->post_feedback($this->open_as($this->teacher, $run), $run, 'Check the edge cases');

        $this->assertTrue($redirected);
        $this->assertNull($this->result_of($run)->feedback);
    }

    public function test_the_student_is_notified_when_the_teacher_asks(): void {
        $sink = $this->redirectMessages();
        $run = $this->run_for($this->student, 'FAILED');

        $this->post_feedback($this->open_as($this->teacher, $run), $run, 'Check the edge cases', ['notify' => 1]);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame((int) $this->student->id, (int) reset($messages)->useridto);
    }

    public function test_the_student_is_not_notified_unless_the_teacher_asks(): void {
        $sink = $this->redirectMessages();
        $run = $this->run_for($this->student, 'FAILED');

        $this->post_feedback($this->open_as($this->teacher, $run), $run, 'Check the edge cases');

        $this->assertCount(0, $sink->get_messages());
    }

    public function test_the_student_is_not_notified_when_no_feedback_changed(): void {
        $sink = $this->redirectMessages();
        $run = $this->run_for($this->student, 'FAILED');

        $this->post_feedback($this->open_as($this->teacher, $run), $run, '', ['notify' => 1]);

        $this->assertCount(0, $sink->get_messages());
    }

    public function test_a_student_sees_only_their_own_runs_with_their_pass_rate(): void {
        $passed = $this->run_for($this->student, 'PASSED');
        $failed = $this->run_for($this->student, 'FAILED');
        $othersrun = $this->run_for($this->getDataGenerator()->create_and_enrol($this->course, 'student'), 'PASSED');

        $html = $this->page_as($this->student);

        $this->assertStringContainsString(get_string('myresults', 'mod_idetestfeedback'), $html);
        $this->assertStringContainsString(
            get_string('summarytext', 'mod_idetestfeedback', ['total' => 2, 'rate' => 50]),
            $html
        );
        $this->assertTrue($this->links_to($html, $passed));
        $this->assertTrue($this->links_to($html, $failed));
        $this->assertFalse($this->links_to($html, $othersrun));
    }

    public function test_a_student_without_runs_is_told_there_are_none(): void {
        $html = $this->page_as($this->student);

        $this->assertStringContainsString(get_string('noresults', 'mod_idetestfeedback'), $html);
    }

    public function test_a_page_past_the_end_of_the_list_shows_the_last_page(): void {
        $run = $this->run_for($this->student);

        $this->assertTrue($this->links_to($this->page_as($this->student, ['page' => 99]), $run));
    }

    public function test_a_teacher_sees_every_students_runs(): void {
        $run = $this->run_for($this->student);
        $othersrun = $this->run_for($this->getDataGenerator()->create_and_enrol($this->course, 'student'));

        $html = $this->page_as($this->teacher);

        $this->assertStringContainsString(get_string('viewresults', 'mod_idetestfeedback'), $html);
        $this->assertStringContainsString(get_string('totalruns', 'mod_idetestfeedback', 2), $html);
        $this->assertTrue($this->links_to($html, $run));
        $this->assertTrue($this->links_to($html, $othersrun));
    }

    public function test_a_teacher_is_told_when_no_run_matches_the_filters(): void {
        $this->run_for($this->student, 'PASSED');

        $html = $this->page_as($this->teacher, ['filterstatus' => 'FAILED']);

        $this->assertStringContainsString(get_string('nomatchingruns', 'mod_idetestfeedback'), $html);
    }

    public function test_a_teacher_filtering_by_status_sees_only_matching_runs(): void {
        $passed = $this->run_for($this->student, 'PASSED');
        $failed = $this->run_for($this->student, 'FAILED');

        $html = $this->page_as($this->teacher, ['filterstatus' => 'FAILED']);

        $this->assertTrue($this->links_to($html, $failed));
        $this->assertFalse($this->links_to($html, $passed));
    }

    public function test_a_teacher_sees_only_their_groups_runs_in_separate_groups(): void {
        [$teacher, $other] = $this->use_separate_groups();
        $run = $this->run_for($this->student);
        $othersrun = $this->run_for($other);

        $html = $this->page_as($teacher);

        $this->assertStringContainsString(get_string('totalruns', 'mod_idetestfeedback', 1), $html);
        $this->assertTrue($this->links_to($html, $run));
        $this->assertFalse($this->links_to($html, $othersrun));
        $this->assertStringNotContainsString(fullname($other), $html);
    }

    public function test_a_teacher_in_no_group_sees_no_runs_in_separate_groups(): void {
        [, $other] = $this->use_separate_groups();
        $othersrun = $this->run_for($other);

        $html = $this->page_as($this->getDataGenerator()->create_and_enrol($this->course, 'teacher'));

        $this->assertStringContainsString(get_string('notingroup'), $html);
        $this->assertFalse($this->links_to($html, $othersrun));
    }
}
