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

use cm_info;
use core\context;
use core\exception\moodle_exception;
use core\exception\required_capability_exception;
use core\output\html_writer;
use core\output\notification;
use core\output\single_select;
use core\url;
use mod_idetestfeedback\local\feedback_notifier;
use mod_idetestfeedback\local\feedback_saver;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\source_history;
use mod_idetestfeedback\local\status;
use mod_idetestfeedback\output\renderer;
use mod_idetestfeedback\output\run_detail;
use mod_idetestfeedback\output\run_list;
use stdClass;

/**
 * The controller behind /mod/idetestfeedback/view.php.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view {

    /** @var int Runs per page of the run list. */
    protected const RUNS_PER_PAGE = 50;

    /** @var int Test case results per page of the run detail. */
    protected const RESULTS_PER_PAGE = 50;

    protected cm_info $cm;
    protected stdClass $course;
    protected stdClass $instance;
    protected context\module $context;
    protected bool $canviewall;
    protected bool $cancomment;
    protected bool $viewfullnames;
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
     * @param int $page zero based page number within the run list, or within the run's results
     * @param int $filteruserid show only this student's runs, or 0 for all students
     * @param string $filterstatus show only runs with this status, or '' for all statuses
     * @param int $listpage the run list page a run was opened from, for the way back
     */
    public function __construct(
        protected int $cmid,
        protected int $runid = 0,
        protected int $page = 0,
        protected int $filteruserid = 0,
        string $filterstatus = '',
        protected int $listpage = 0
    ) {
        global $DB, $PAGE, $USER;

        $this->repository = new repository($DB);
        $this->user = $USER;
        $this->filterstatus = status::tryFrom($filterstatus);

        [$this->course, $this->cm] = get_course_and_cm_from_cmid($cmid, 'idetestfeedback');
        $this->instance = $this->repository->get_instance($this->cm->instance);

        $PAGE->set_url($this->runid > 0 ? $this->run_url($this->page) : $this->list_url($this->page));

        require_course_login($this->course, true, $this->cm);

        $this->context = $this->cm->context;

        $this->canviewall = has_capability('mod/idetestfeedback:viewall', $this->context);
        if (!$this->canviewall) {
            require_capability('mod/idetestfeedback:view', $this->context);
        }
        $this->cancomment = has_capability('mod/idetestfeedback:comment', $this->context);
        $this->viewfullnames = has_capability('moodle/site:viewfullnames', $this->context);

        $PAGE->set_title(format_string($this->instance->name));
        $PAGE->set_heading(format_string($this->course->fullname));

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

        if (!$run || (int) $run->userid === (int) $this->user->id) {
            return $run;
        }

        if (!$this->canviewall) {
            throw new required_capability_exception(
                $this->context,
                'mod/idetestfeedback:viewall',
                'nopermissions',
                ''
            );
        }

        if (!groups_user_groups_visible($this->course, (int) $run->userid, $this->cm)) {
            throw new moodle_exception('notingroup');
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
            redirect($this->list_url($this->listpage));
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
            $this->run_url($this->page),
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
        return optional_param_array('feedback', [], PARAM_RAW);
    }

    /**
     * Writes the page.
     */
    public function render(): void {
        idetestfeedback_view($this->instance, $this->course, $this->cm, $this->context);

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

        $results = $this->repository->get_results($this->runid);
        $page = $this->clamp_page(count($results), self::RESULTS_PER_PAGE);

        return $this->renderer->render(new run_detail(
            instance: $this->instance,
            run: $this->run,
            results: $results,
            files: $this->repository->get_files($this->runid),
            studentname: $this->canviewall ? $this->student_name($this->run) : null,
            context: $this->context,
            cancomment: $this->cancomment,
            history: new source_history(
                $this->repository->get_source_history($priorids),
                $this->repository->get_file_history($priorids),
                (int) $this->run->timecreated
            ),
            backurl: $this->list_url($this->listpage),
            formurl: $this->run_url($page),
            page: $page,
            perpage: self::RESULTS_PER_PAGE,
            pagingbar: $this->renderer->paging_bar(
                count($results),
                $page,
                self::RESULTS_PER_PAGE,
                $this->run_url()
            ),
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

        return $user ? fullname($user, $this->viewfullnames) : get_string('unknownuser');
    }

    /**
     * Every student's runs in the active group, filtered and paged.
     *
     * @return string
     */
    protected function teacher_view(): string {
        $out = $this->renderer->heading(get_string('viewresults', 'mod_idetestfeedback'), 2);
        $out .= groups_print_activity_menu(
            $this->cm,
            $this->url(array_diff_key($this->filter_params(), ['filteruserid' => 0])),
            true
        );

        $groupid = $this->active_group();
        if ($groupid === null) {
            return $out . $this->renderer->notification(get_string('notingroup'), notification::NOTIFY_INFO);
        }

        $scope = array_filter(['groupid' => $groupid]);
        $grandtotal = $this->repository->count_runs_for_instance($this->instance->id, $scope);
        if ($grandtotal === 0) {
            return $out . $this->renderer->notification(
                get_string('noresults', 'mod_idetestfeedback'),
                notification::NOTIFY_INFO
            );
        }

        $filters = $this->run_filters();
        $menus = $this->run_filter_menus($groupid);

        $total = $filters
            ? $this->repository->count_runs_for_instance($this->instance->id, $scope + $filters)
            : $grandtotal;

        if ($total === 0) {
            return $out . $menus . $this->renderer->notification(
                get_string('nomatchingruns', 'mod_idetestfeedback'),
                notification::NOTIFY_INFO
            );
        }

        $page = $this->clamp_page($total, self::RUNS_PER_PAGE);

        return $out . $this->renderer->render(new run_list(
            runs: $this->repository->get_runs_for_instance(
                $this->instance->id,
                $scope + $filters,
                $page * self::RUNS_PER_PAGE,
                self::RUNS_PER_PAGE
            ),
            detailurl: $this->url($this->list_state($page)),
            showstudent: true,
            colourrows: false,
            viewfullnames: $this->viewfullnames,
            totaltext: get_string('totalruns', 'mod_idetestfeedback', $total),
            filters: $menus,
            pagingbar: $this->renderer->paging_bar($total, $page, self::RUNS_PER_PAGE, $this->list_url())
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

        $page = $this->clamp_page($total, self::RUNS_PER_PAGE);

        return $out . $this->renderer->render(new run_list(
            runs: $this->repository->get_runs_for_user(
                $this->instance->id,
                $this->user->id,
                $page * self::RUNS_PER_PAGE,
                self::RUNS_PER_PAGE
            ),
            detailurl: $this->url($this->list_state($page)),
            showstudent: false,
            colourrows: true,
            summary: get_string('summarytext', 'mod_idetestfeedback', [
                'total' => $total,
                'rate' => (int) round($passedruns / $total * 100),
            ]),
            pagingbar: $this->renderer->paging_bar($total, $page, self::RUNS_PER_PAGE, $this->list_url())
        ));
    }

    /**
     * The group the run list is limited to.
     *
     * @return int|null the group id, 0 for every student, or null when the viewer may see no group at all
     */
    protected function active_group(): ?int {
        $groupid = (int) groups_get_activity_group($this->cm, true);

        if ($groupid === 0 && groups_get_activity_groupmode($this->cm) == SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $this->context)) {
            return null;
        }

        return $groupid;
    }

    /**
     * The student and status filter menus.
     *
     * Each carries the other's current value, and neither carries the page number.
     *
     * @param int $groupid list only students in this group, or 0 for every student
     * @return string
     */
    protected function run_filter_menus(int $groupid): string {
        $studentoptions = [0 => get_string('allstudents', 'mod_idetestfeedback')];
        foreach ($this->repository->get_students_with_runs($this->instance->id, $groupid) as $student) {
            $studentoptions[$student->id] = fullname($student, $this->viewfullnames);
        }

        $params = $this->filter_params();

        $studentselect = new single_select(
            $this->url(array_diff_key($params, ['filteruserid' => 0])),
            'filteruserid',
            $studentoptions,
            $this->filteruserid,
            null
        );
        $studentselect->label = get_string('student', 'mod_idetestfeedback');

        $statusselect = new single_select(
            $this->url(array_diff_key($params, ['filterstatus' => 0])),
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
        return array_filter(['userid' => $this->filteruserid, 'status' => $this->filterstatus?->value]);
    }

    /**
     * The active filters, as query parameters for links back into the list.
     *
     * @return array
     */
    protected function filter_params(): array {
        return array_filter(['filteruserid' => $this->filteruserid, 'filterstatus' => $this->filterstatus?->value]);
    }

    /**
     * Where the run list stood, as query parameters a run carries so its back link returns there.
     *
     * @param int $listpage the run list page
     * @return array
     */
    protected function list_state(int $listpage): array {
        return $this->filter_params() + array_filter(['listpage' => $listpage]);
    }

    /**
     * The requested page, held within the range the item count allows.
     *
     * @param int $total the number of items being paged through
     * @param int $perpage items per page
     * @return int a zero based page number
     */
    protected function clamp_page(int $total, int $perpage): int {
        $maxpage = (int) floor(max(0, $total - 1) / $perpage);

        return max(0, min($this->page, $maxpage));
    }

    /**
     * The run list, with the active filters.
     *
     * @param int $page zero based page number
     * @return url
     */
    protected function list_url(int $page = 0): url {
        return $this->url($this->filter_params() + array_filter(['page' => $page]));
    }

    /**
     * The requested run, keeping the run list state it was opened from.
     *
     * @param int $page zero based page number within the run's results
     * @return url
     */
    protected function run_url(int $page = 0): url {
        return $this->url(['runid' => $this->runid] + $this->list_state($this->listpage) + array_filter(['page' => $page]));
    }

    /**
     * A URL back into this activity.
     *
     * @param array $params query parameters to carry alongside the course module id
     * @return url
     */
    protected function url(array $params = []): url {
        return new url('/mod/idetestfeedback/view.php', ['id' => $this->cmid] + $params);
    }
}
