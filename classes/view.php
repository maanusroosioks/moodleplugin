<?php
namespace mod_idetestfeedback;

use context_module;
use core\output\notification;
use mod_idetestfeedback\form\feedback_form;
use mod_idetestfeedback\local\feedback_notifier;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\output\run_detail;
use mod_idetestfeedback\output\run_list;
use moodle_url;
use required_capability_exception;
use single_select;
use stdClass;

class view {

    protected const PER_PAGE = 50;

    protected stdClass $cm;
    protected stdClass $course;
    protected stdClass $instance;
    protected context_module $context;
    protected bool $canviewall;
    protected repository $repository;
    protected output\renderer $renderer;
    protected stdClass $user;

    /**
     * Sets up the page and checks the viewer may be here.
     *
     * @param int $id the course module id
     * @param int $runid the run to show in detail, or 0 for the run list
     */
    public function __construct(
        protected int $id,
        protected int $runid
    ) {
        global $DB, $PAGE, $USER;

        $this->repository = new repository($DB);
        $this->user = $USER;

        $this->cm = get_coursemodule_from_id('idetestfeedback', $id, 0, false, MUST_EXIST);
        $this->course = $this->repository->get_course($this->cm->course);
        $this->instance = $this->repository->get_instance($this->cm->instance);

        require_course_login($this->course, true, $this->cm);

        $this->context = context_module::instance($this->cm->id);

        $PAGE->set_url('/mod/idetestfeedback/view.php', ['id' => $id]);
        $PAGE->set_title(format_string($this->instance->name));
        $PAGE->set_heading(format_string($this->course->fullname));
        $PAGE->set_context($this->context);

        $this->canviewall = has_capability('mod/idetestfeedback:viewall', $this->context);
        if (!$this->canviewall) {
            require_capability('mod/idetestfeedback:view', $this->context);
        }

        $this->renderer = $PAGE->get_renderer('mod_idetestfeedback');
    }

    public function handle_post(): void {
        if (!optional_param('savefeedback', 0, PARAM_BOOL)) {
            return;
        }

        $listurl = new moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->id]);

        $run = $this->repository->get_run($this->runid, $this->instance->id);
        if (!$run) {
            redirect($listurl);
        }

        require_capability('mod/idetestfeedback:comment', $this->context);

        $form = new feedback_form(
            new moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->id, 'runid' => $this->runid]),
            ['resultstable' => '', 'cmid' => $this->id, 'runid' => $this->runid]
        );

        $data = $form->get_data();
        if (!$data) {
            return;
        }

        $changed = $this->save_feedback();

        $notified = false;
        if ($changed && !empty($data->notify)) {
            $notifier = new feedback_notifier($this->course, $this->instance, $this->id);
            $notified = $notifier->notify($run, $changed, $this->user);
        }

        redirect(
            new moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->id, 'runid' => $this->runid]),
            get_string($notified ? 'feedbacksavednotified' : 'feedbacksaved', 'mod_idetestfeedback'),
            null,
            notification::NOTIFY_SUCCESS
        );
    }

    protected function save_feedback(): array {
        $changed = [];

        foreach ($this->repository->get_results($this->runid) as $result) {
            $submitted = trim(optional_param('feedback_' . $result->id, '', PARAM_TEXT));
            $current = trim((string) ($result->feedback ?? ''));

            if ($submitted === $current) {
                continue;
            }

            $this->repository->update_result_feedback(
                (int) $result->id,
                $submitted,
                FORMAT_PLAIN,
                (int) $this->user->id
            );

            if ($submitted !== '') {
                $result->feedback = $submitted;
                $changed[] = $result;
            }
        }

        return $changed;
    }

    public function render(): void {
        $this->mark_viewed();
        $this->log_viewed();

        echo $this->renderer->header();
        echo $this->renderer->heading(format_string($this->instance->name));
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

    protected function mark_viewed(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $completion = new \completion_info($this->course);
        $completion->set_module_viewed($this->cm);
    }

    protected function log_viewed(): void {
        $event = event\course_module_viewed::create([
            'objectid' => $this->instance->id,
            'context' => $this->context,
        ]);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot('idetestfeedback', $this->instance);
        $event->trigger();
    }

    protected function submission_window_notice(): string {
        $now = time();

        if ($this->instance->timeopen > 0 && $now < $this->instance->timeopen) {
            return $this->renderer->notification(
                get_string('submissionnotopen', 'mod_idetestfeedback', userdate($this->instance->timeopen)),
                'warning'
            );
        }

        if ($this->instance->timeclose > 0 && $now > $this->instance->timeclose) {
            return $this->renderer->notification(
                get_string('submissionclosed', 'mod_idetestfeedback', userdate($this->instance->timeclose)),
                'warning'
            );
        }

        return '';
    }

    protected function run_detail(): string {
        $run = $this->repository->get_run($this->runid, $this->instance->id);

        if (!$run) {
            return $this->renderer->notification(get_string('runnotfound', 'mod_idetestfeedback'), 'error');
        }

        if (!$this->canviewall && (int) $run->userid !== (int) $this->user->id) {
            throw new required_capability_exception(
                $this->context,
                'mod/idetestfeedback:viewall',
                'nopermissions',
                ''
            );
        }

        return $this->renderer->render(new run_detail(
            $this->instance,
            $run,
            $this->repository->get_results($this->runid),
            $this->canviewall ? $this->student_name($run) : null,
            $this->context,
            $this->id,
            has_capability('mod/idetestfeedback:comment', $this->context)
        ));
    }

    protected function student_name(stdClass $run): string {
        $user = $this->repository->get_user_brief((int) $run->userid);

        return $user ? fullname($user) : (string) $run->userid;
    }

    protected function teacher_view(): string {
        $page = optional_param('page', 0, PARAM_INT);
        $filteruserid = optional_param('filteruserid', 0, PARAM_INT);
        $filterstatus = optional_param('filterstatus', '', PARAM_ALPHA);

        if ($filterstatus === 'ALL') {
            $filterstatus = '';
        }

        $out = $this->renderer->heading(get_string('viewresults', 'mod_idetestfeedback'), 2);

        $grandtotal = $this->repository->count_runs_for_instance($this->instance->id);
        if ($grandtotal === 0) {
            return $out . $this->renderer->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
        }

        $filters = [];
        if ($filteruserid) {
            $filters['userid'] = $filteruserid;
        }
        if ($filterstatus) {
            $filters['status'] = $filterstatus;
        }

        $filtershtml = $this->run_filters($filteruserid, $filterstatus);

        $total = $filters
            ? $this->repository->count_runs_for_instance($this->instance->id, $filters)
            : $grandtotal;

        if ($total === 0) {
            return $out . $filtershtml
                . $this->renderer->notification(get_string('nomatchingruns', 'mod_idetestfeedback'), 'info');
        }

        $page = $this->clamp_page($page, $total);

        return $out . $this->renderer->render(new run_list(
            runs: $this->repository->get_runs_for_instance(
                $this->instance->id,
                $filters,
                $page * self::PER_PAGE,
                self::PER_PAGE
            ),
            cmid: $this->id,
            showstudent: true,
            colourrows: false,
            totaltext: get_string('totalruns', 'mod_idetestfeedback', $total),
            filters: $filtershtml,
            pagingbar: $this->renderer->paging_bar(
                $total,
                $page,
                self::PER_PAGE,
                $this->list_url($filters)
            )
        ));
    }

    protected function run_filters(int $filteruserid, string $filterstatus): string {
        $studentoptions = [0 => get_string('allstudents', 'mod_idetestfeedback')];
        foreach ($this->repository->get_students_with_runs($this->instance->id) as $student) {
            $studentoptions[$student->id] = fullname($student);
        }

        $studentselect = new single_select(
            new moodle_url(
                '/mod/idetestfeedback/view.php',
                ['id' => $this->id] + ($filterstatus ? ['filterstatus' => $filterstatus] : [])
            ),
            'filteruserid',
            $studentoptions,
            $filteruserid,
            null
        );
        $studentselect->label = get_string('student', 'mod_idetestfeedback');

        $statusselect = new single_select(
            new moodle_url(
                '/mod/idetestfeedback/view.php',
                ['id' => $this->id] + ($filteruserid ? ['filteruserid' => $filteruserid] : [])
            ),
            'filterstatus',
            [
                'ALL' => get_string('allstatuses', 'mod_idetestfeedback'),
                'PASSED' => 'PASSED',
                'FAILED' => 'FAILED',
                'ERROR' => 'ERROR',
                'SKIPPED' => 'SKIPPED',
            ],
            $filterstatus ?: 'ALL',
            null
        );
        $statusselect->label = get_string('status', 'mod_idetestfeedback');

        return \html_writer::div(
            $this->renderer->render($studentselect) . $this->renderer->render($statusselect),
            'idetestfeedback-filters mb-3'
        );
    }

    protected function student_view(): string {
        $page = optional_param('page', 0, PARAM_INT);

        $out = $this->renderer->heading(get_string('myresults', 'mod_idetestfeedback'), 2);

        [$total, $passedruns] = $this->repository->get_pass_stats($this->instance->id, $this->user->id);

        if ($total === 0) {
            return $out . $this->renderer->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
        }

        $page = $this->clamp_page($page, $total);

        return $out . $this->renderer->render(new run_list(
            runs: $this->repository->get_runs_for_user(
                $this->instance->id,
                $this->user->id,
                $page * self::PER_PAGE,
                self::PER_PAGE
            ),
            cmid: $this->id,
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
                new moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->id])
            )
        ));
    }

    protected function clamp_page(int $page, int $total): int {
        $maxpage = $total > 0 ? (int) floor(($total - 1) / self::PER_PAGE) : 0;

        return max(0, min($page, $maxpage));
    }

    protected function list_url(array $filters): moodle_url {
        $params = ['id' => $this->id];

        if (!empty($filters['userid'])) {
            $params['filteruserid'] = $filters['userid'];
        }
        if (!empty($filters['status'])) {
            $params['filterstatus'] = $filters['status'];
        }

        return new moodle_url('/mod/idetestfeedback/view.php', $params);
    }
}
