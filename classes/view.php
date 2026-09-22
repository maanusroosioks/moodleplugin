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

use completion_info;
use context_module;
use core\exception\required_capability_exception;
use core\output\html_writer;
use core\output\notification;
use core\output\single_select;
use mod_idetestfeedback\event\course_module_viewed;
use mod_idetestfeedback\local\feedback_notifier;
use mod_idetestfeedback\local\feedback_saver;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\source_history;
use mod_idetestfeedback\local\status;
use mod_idetestfeedback\output\renderer;
use mod_idetestfeedback\output\run_detail;
use mod_idetestfeedback\output\run_list;
use moodle_url;
use stdClass;

/**
 * The controller behind /mod/idetestfeedback/view.php.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view {

    protected const PER_PAGE = 50;

    protected stdClass $cm;
    protected stdClass $course;
    protected stdClass $instance;
    protected context_module $context;
    protected bool $canviewall;
    protected bool $cancomment;
    protected repository $repository;
    protected renderer $renderer;
    protected stdClass $user;
    protected ?stdClass $run = null;
    protected ?status $filterstatus = null;

    /**
     * Sets the page up and settles every access question, before any output.
     *
     * @param int $cmid the course module id
     * @param int $runid the run to show in detail, or 0 for the run list
     * @param int $page zero based page number within the run list
     * @param int $filteruserid show only this student's runs, or 0 for all students
     * @param string $filterstatus show only runs with this status, or '' for all statuses
     */
    public function __construct(
        protected int $cmid,
        protected int $runid = 0,
        protected int $page = 0,
        protected int $filteruserid = 0,
        string $filterstatus = ''
    ) {
        global $DB, $PAGE, $USER;

        $this->repository = new repository($DB);
        $this->user = $USER;
        $this->filterstatus = status::tryFrom($filterstatus);

        $this->cm = get_coursemodule_from_id('idetestfeedback', $cmid, 0, false, MUST_EXIST);
        $this->course = $this->repository->get_course($this->cm->course);
        $this->instance = $this->repository->get_instance($this->cm->instance);

        require_course_login($this->course, true, $this->cm);

        $this->context = context_module::instance($this->cm->id);

        $this->canviewall = has_capability('mod/idetestfeedback:viewall', $this->context);
        if (!$this->canviewall) {
            require_capability('mod/idetestfeedback:view', $this->context);
        }
        $this->cancomment = has_capability('mod/idetestfeedback:comment', $this->context);

        $PAGE->set_url($this->url($this->runid > 0 ? ['runid' => $this->runid] : []));
        $PAGE->set_title(format_string($this->instance->name));
        $PAGE->set_heading(format_string($this->course->fullname));
        $PAGE->set_context($this->context);

        $this->renderer = $PAGE->get_renderer('mod_idetestfeedback');

        if ($this->runid > 0) {
            $this->run = $this->load_run();
        }
    }

    /**
     * Fetches the requested run and checks the viewer is entitled to it.
     *
     * Called from the constructor so the check cannot fire once output has started.
     *
     * @return stdClass|null the run, or null when this activity has no such run
     */
    protected function load_run(): ?stdClass {
        $run = $this->repository->get_run($this->runid, $this->instance->id);

        if (!$run) {
            return null;
        }

        if (!$this->canviewall && (int) $run->userid !== (int) $this->user->id) {
            throw new required_capability_exception(
                $this->context,
                'mod/idetestfeedback:viewall',
                'nopermissions',
                ''
            );
        }

        return $run;
    }

    /**
     * Saves feedback posted from the run detail page and redirects back to it.
     *
     * The feedback fields are plain markup rather than form elements, so the
     * session key posted by mod_idetestfeedback/run_feedback_form is confirmed here.
     */
    public function handle_post(): void {
        if (!optional_param('savefeedback', 0, PARAM_BOOL)) {
            return;
        }

        require_sesskey();

        if (!$this->run) {
            redirect($this->url());
        }

        require_capability('mod/idetestfeedback:comment', $this->context);

        $changed = (new feedback_saver($this->repository))
            ->save($this->runid, $this->submitted_feedback(), (int) $this->user->id);

        $notified = false;
        if ($changed && optional_param('notify', 0, PARAM_BOOL)) {
            $notifier = new feedback_notifier($this->course, $this->instance, $this->cmid);
            $notified = $notifier->notify($this->run, $changed, $this->user);
        }

        redirect(
            $this->url(['runid' => $this->runid]),
            get_string($notified ? 'feedbacksavednotified' : 'feedbacksaved', 'mod_idetestfeedback'),
            null,
            notification::NOTIFY_SUCCESS
        );
    }

    /**
     * The feedback text posted for each result, keyed by result id.
     *
     * Only fields that were actually posted, so an absent textarea keeps its
     * stored feedback. Raw because PARAM_TEXT would strip List<String> and the
     * like; output is escaped by format_text() and the template.
     *
     * @return array<int, string> result id => submitted feedback
     */
    protected function submitted_feedback(): array {
        $submission = data_submitted();

        if (!$submission) {
            return [];
        }

        $feedback = [];
        foreach ((array) $submission as $field => $value) {
            if (preg_match('/^feedback_(\d+)$/', $field, $matches)) {
                $feedback[(int) $matches[1]] = (string) $value;
            }
        }

        return $feedback;
    }

    /**
     * Writes the page.
     */
    public function render(): void {
        $this->mark_viewed();
        $this->log_viewed();

        echo $this->renderer->header();
        echo $this->renderer->heading(format_string($this->instance->name));
        echo $this->assignment_key();
        echo $this->submission_window_notice();

        if ($this->runid > 0) {
            echo $this->run_detail();
        } else if ($this->canviewall) {
            echo $this->teacher_view();
        } else {
            echo $this->student_view();
        }

        echo $this->renderer->footer();
    }

    /**
     * Records the view against the activity's completion tracking.
     */
    protected function mark_viewed(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $completion = new completion_info($this->course);
        $completion->set_module_viewed($this->cm);
    }

    /**
     * Logs the view.
     */
    protected function log_viewed(): void {
        $event = course_module_viewed::create([
            'objectid' => $this->instance->id,
            'context' => $this->context,
        ]);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('idetestfeedback', $this->instance);
        $event->trigger();
    }

    /**
     * The key students paste into their IDE plugin.
     *
     * Shown to everyone who can see the activity: it routes submissions to this
     * instance but grants nothing on its own, and a student cannot submit
     * without it.
     *
     * @return string
     */
    protected function assignment_key(): string {
        return html_writer::div(
            html_writer::tag('span', get_string('assignmentkey', 'mod_idetestfeedback') . ': ') .
                html_writer::tag('code', s($this->instance->assignmentkey)),
            'idetestfeedback-key mb-3'
        );
    }

    /**
     * A notice when the activity is outside its submission window.
     *
     * @return string rendered notification, or '' while submissions are open
     */
    protected function submission_window_notice(): string {
        $now = time();

        if ($this->instance->timeopen > 0 && $now < $this->instance->timeopen) {
            return $this->renderer->notification(
                get_string('submissionnotopen', 'mod_idetestfeedback', userdate($this->instance->timeopen)),
                notification::NOTIFY_WARNING
            );
        }

        if ($this->instance->timeclose > 0 && $now > $this->instance->timeclose) {
            return $this->renderer->notification(
                get_string('submissionclosed', 'mod_idetestfeedback', userdate($this->instance->timeclose)),
                notification::NOTIFY_WARNING
            );
        }

        return '';
    }

    /**
     * One run in full, with the feedback form when the viewer may comment.
     *
     * @return string
     */
    protected function run_detail(): string {
        if (!$this->run) {
            return $this->renderer->notification(
                get_string('runnotfound', 'mod_idetestfeedback'),
                notification::NOTIFY_ERROR
            );
        }

        $priorids = $this->repository->get_prior_run_ids(
            (int) $this->instance->id,
            (int) $this->run->userid,
            $this->runid,
            source_history::LOOKBACK_RUNS
        );

        return $this->renderer->render(new run_detail(
            $this->instance,
            $this->run,
            $this->repository->get_results($this->runid),
            $this->repository->get_files($this->runid),
            $this->canviewall ? $this->student_name($this->run) : null,
            $this->context,
            $this->cmid,
            $this->cancomment,
            new source_history(
                $this->repository->get_source_history($priorids),
                $this->repository->get_file_history($priorids),
                (int) $this->run->timecreated
            )
        ));
    }

    /**
     * The display name of the student a run belongs to.
     *
     * @param stdClass $run the run
     * @return string
     */
    protected function student_name(stdClass $run): string {
        $user = $this->repository->get_user_brief((int) $run->userid);

        return $user ? fullname($user) : get_string('unknownuser');
    }

    /**
     * Every student's runs, filtered and paged.
     *
     * @return string
     */
    protected function teacher_view(): string {
        $out = $this->renderer->heading(get_string('viewresults', 'mod_idetestfeedback'), 2);

        $grandtotal = $this->repository->count_runs_for_instance($this->instance->id);
        if ($grandtotal === 0) {
            return $out . $this->renderer->notification(
                get_string('noresults', 'mod_idetestfeedback'),
                notification::NOTIFY_INFO
            );
        }

        $filters = $this->run_filters();
        $menus = $this->run_filter_menus();

        $total = $filters
            ? $this->repository->count_runs_for_instance($this->instance->id, $filters)
            : $grandtotal;

        if ($total === 0) {
            return $out . $menus . $this->renderer->notification(
                get_string('nomatchingruns', 'mod_idetestfeedback'),
                notification::NOTIFY_INFO
            );
        }

        $page = $this->clamp_page($total);

        return $out . $this->renderer->render(new run_list(
            runs: $this->repository->get_runs_for_instance(
                $this->instance->id,
                $filters,
                $page * self::PER_PAGE,
                self::PER_PAGE
            ),
            cmid: $this->cmid,
            showstudent: true,
            colourrows: false,
            totaltext: get_string('totalruns', 'mod_idetestfeedback', $total),
            filters: $menus,
            pagingbar: $this->renderer->paging_bar(
                $total,
                $page,
                self::PER_PAGE,
                $this->url($this->filter_params())
            )
        ));
    }

    /**
     * The viewer's own runs, paged.
     *
     * @return string
     */
    protected function student_view(): string {
        $out = $this->renderer->heading(get_string('myresults', 'mod_idetestfeedback'), 2);

        [$total, $passedruns] = $this->repository->get_pass_stats($this->instance->id, $this->user->id);

        if ($total === 0) {
            return $out . $this->renderer->notification(
                get_string('noresults', 'mod_idetestfeedback'),
                notification::NOTIFY_INFO
            );
        }

        $page = $this->clamp_page($total);

        return $out . $this->renderer->render(new run_list(
            runs: $this->repository->get_runs_for_user(
                $this->instance->id,
                $this->user->id,
                $page * self::PER_PAGE,
                self::PER_PAGE
            ),
            cmid: $this->cmid,
            showstudent: false,
            colourrows: true,
            summary: get_string('summarytext', 'mod_idetestfeedback', [
                'total' => $total,
                'rate' => (int) round($passedruns / $total * 100),
            ]),
            pagingbar: $this->renderer->paging_bar(
                $total,
                $page,
                self::PER_PAGE,
                $this->url()
            )
        ));
    }

    /**
     * The student and status filter menus.
     *
     * Each carries the other's current value, and neither carries the page number.
     *
     * @return string
     */
    protected function run_filter_menus(): string {
        $studentoptions = [0 => get_string('allstudents', 'mod_idetestfeedback')];
        foreach ($this->repository->get_students_with_runs($this->instance->id) as $student) {
            $studentoptions[$student->id] = fullname($student);
        }

        $params = $this->filter_params();

        $studentselect = new single_select(
            $this->url(array_diff_key($params, ['filteruserid' => null])),
            'filteruserid',
            $studentoptions,
            $this->filteruserid,
            null
        );
        $studentselect->label = get_string('student', 'mod_idetestfeedback');

        $statusselect = new single_select(
            $this->url(array_diff_key($params, ['filterstatus' => null])),
            'filterstatus',
            status::filter_options(),
            $this->filterstatus?->value ?? status::ANY,
            null
        );
        $statusselect->label = get_string('status', 'mod_idetestfeedback');

        return html_writer::div(
            $this->renderer->render($studentselect) . $this->renderer->render($statusselect),
            'idetestfeedback-filters mb-3'
        );
    }

    /**
     * The active filters, in the shape the repository expects.
     *
     * @return array
     */
    protected function run_filters(): array {
        $filters = [];

        if ($this->filteruserid) {
            $filters['userid'] = $this->filteruserid;
        }
        if ($this->filterstatus !== null) {
            $filters['status'] = $this->filterstatus->value;
        }

        return $filters;
    }

    /**
     * The active filters, as query parameters for links back into the list.
     *
     * @return array
     */
    protected function filter_params(): array {
        $params = [];

        if ($this->filteruserid) {
            $params['filteruserid'] = $this->filteruserid;
        }
        if ($this->filterstatus !== null) {
            $params['filterstatus'] = $this->filterstatus->value;
        }

        return $params;
    }

    /**
     * The requested page, held within the range the run count allows.
     *
     * @param int $total the number of runs being paged through
     * @return int a zero based page number
     */
    protected function clamp_page(int $total): int {
        $maxpage = (int) floor(max(0, $total - 1) / self::PER_PAGE);

        return max(0, min($this->page, $maxpage));
    }

    /**
     * A URL back into this activity.
     *
     * @param array $params query parameters to carry alongside the course module id
     * @return moodle_url
     */
    protected function url(array $params = []): moodle_url {
        return new moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->cmid] + $params);
    }
}
