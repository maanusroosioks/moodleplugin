<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback;

use mod_idetestfeedback\local\repository;

defined('MOODLE_INTERNAL') || die();

class view {
    protected int $id;
    protected int $runid;
    protected $cm;
    protected $course;
    protected $instance;
    protected $context;
    protected bool $canviewall;
    protected bool $canview;
    protected repository $repository;
    protected $output;
    protected $page;
    protected $user;

    public function __construct(int $id, int $runid) {
        global $DB, $OUTPUT, $PAGE, $USER;

        $this->id = $id;
        $this->runid = $runid;
        $this->repository = new repository($DB);
        $this->output = $OUTPUT;
        $this->page = $PAGE;
        $this->user = $USER;

        $this->cm = get_coursemodule_from_id('idetestfeedback', $id, 0, false, MUST_EXIST);
        $this->course = $this->repository->get_course($this->cm->course);
        $this->instance = $this->repository->get_instance($this->cm->instance);

        require_course_login($this->course, true, $this->cm);

        $this->context = \context_module::instance($this->cm->id);

        $this->page->set_url('/mod/idetestfeedback/view.php', ['id' => $id]);
        $this->page->set_title(format_string($this->instance->name));
        $this->page->set_heading(format_string($this->course->fullname));
        $this->page->set_context($this->context);

        $this->canviewall = has_capability('mod/idetestfeedback:viewall', $this->context);
        $this->canview = has_capability('mod/idetestfeedback:view', $this->context);

        if (!$this->canview && !$this->canviewall) {
            throw new \required_capability_exception($this->context, 'mod/idetestfeedback:view', 'nopermissions', '');
        }
    }

    public function render(): void {
        echo $this->output->header();
        echo $this->output->heading(format_string($this->instance->name));

        $this->render_submission_window_notice();

        if ($this->runid > 0) {
            $this->render_run_detail();
        } elseif ($this->canviewall) {
            $this->render_teacher_view();
        } else {
            $this->render_student_view();
        }

        echo $this->output->footer();
    }

    protected function render_submission_window_notice(): void {
        $now = time();
        if ($this->instance->timeopen > 0 && $now < $this->instance->timeopen) {
            echo $this->output->notification(
                get_string('submissionnotopen', 'mod_idetestfeedback', userdate($this->instance->timeopen)),
                'warning'
            );
        } elseif ($this->instance->timeclose > 0 && $now > $this->instance->timeclose) {
            echo $this->output->notification(
                get_string('submissionclosed', 'mod_idetestfeedback', userdate($this->instance->timeclose)),
                'warning'
            );
        }
    }

    protected function render_run_detail(): void {
        $run = $this->repository->get_run($this->runid, $this->instance->id);

        if (!$run) {
            echo $this->output->notification(get_string('runnotfound', 'mod_idetestfeedback'), 'error');
            return;
        }

        if (!$this->canviewall && (int) $run->userid !== (int) $this->user->id) {
            throw new \required_capability_exception($this->context, 'mod/idetestfeedback:viewall', 'nopermissions', '');
        }

        $backurl = new \moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->id]);
        echo \html_writer::div(
            \html_writer::link($backurl, '&#8592; ' . get_string('back', 'mod_idetestfeedback'), ['class' => 'btn btn-secondary mb-3']),
            'mb-3'
        );

        echo $this->output->heading(get_string('rundetail', 'mod_idetestfeedback'), 2);

        $meta = new \html_table();
        $meta->attributes['class'] = 'table table-bordered w-auto mb-4';

        if ($this->canviewall) {
            $user = $this->repository->get_user_brief($run->userid);
            $meta->data[] = [get_string('student', 'mod_idetestfeedback'), $user ? fullname($user) : (string) $run->userid];
        }

        $meta->data[] = [get_string('ide', 'mod_idetestfeedback'), s($run->ide)];

        if ($run->projectname) {
            $meta->data[] = [get_string('projectname', 'mod_idetestfeedback'), s($run->projectname)];
        }
        if ($run->commithash) {
            $meta->data[] = [get_string('commithash', 'mod_idetestfeedback'), \html_writer::tag('code', s($run->commithash))];
        }

        $meta->data[] = [get_string('status', 'mod_idetestfeedback'), $this->status_badge($run->status)];

        $total = (int) $run->passedcount + (int) $run->failedcount + (int) $run->skippedcount + (int) $run->errorcount;
        $meta->data[] = [
            get_string('passed', 'mod_idetestfeedback'),
            \html_writer::tag('span', (int) $run->passedcount . ' / ' . $total, ['style' => 'font-weight:bold;']),
        ];

        if ((int) $run->skippedcount > 0) {
            $meta->data[] = [get_string('skipped', 'mod_idetestfeedback'), (int) $run->skippedcount];
        }

        $meta->data[] = [get_string('timecreated', 'mod_idetestfeedback'), userdate((int) $run->timecreated)];

        echo \html_writer::table($meta);

        echo $this->output->heading(get_string('testresults', 'mod_idetestfeedback'), 3);

        $results = $this->repository->get_results($this->runid);

        if (empty($results)) {
            echo $this->output->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
            return;
        }

        $table = new \html_table();
        $table->attributes['class'] = 'table table-sm table-bordered';
        $table->head = [
            get_string('testsuite', 'mod_idetestfeedback'),
            get_string('testname', 'mod_idetestfeedback'),
            get_string('status', 'mod_idetestfeedback'),
            get_string('duration', 'mod_idetestfeedback'),
            get_string('message', 'mod_idetestfeedback'),
        ];

        foreach ($results as $result) {
            $row = new \html_table_row();
            $row->cells[] = s($result->testsuite ?? '');
            $row->cells[] = s($result->testname);
            $row->cells[] = $this->status_badge($result->status);
            $row->cells[] = $result->durationms !== null ? (int) $result->durationms . ' ms' : '';

            $msgcell = new \html_table_cell();
            if ($result->message) {
                $msgcell->text = \html_writer::tag('pre', s($result->message), [
                    'style' => 'max-height:120px;overflow-y:auto;font-size:0.78em;margin:0;white-space:pre-wrap;',
                ]);
            }
            $row->cells[] = $msgcell;

            $row->attributes['class'] = match (true) {
                in_array($result->status, ['FAILED', 'ERROR'], true) => 'table-danger',
                $result->status === 'PASSED' => 'table-success',
                $result->status === 'SKIPPED' => 'table-warning',
                default => '',
            };

            $table->data[] = $row;
        }

        echo \html_writer::table($table);
    }

    protected function render_teacher_view(): void {
        echo $this->output->heading(get_string('viewresults', 'mod_idetestfeedback'), 2);

        $runs = $this->repository->get_runs_for_instance($this->instance->id);

        if (empty($runs)) {
            echo $this->output->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
            return;
        }

        echo \html_writer::tag('p', get_string('totalruns', 'mod_idetestfeedback', count($runs)), ['class' => 'text-muted']);

        $table = new \html_table();
        $table->attributes['class'] = 'table table-striped table-bordered table-sm';
        $table->head = [
            get_string('student', 'mod_idetestfeedback'),
            get_string('ide', 'mod_idetestfeedback'),
            get_string('projectname', 'mod_idetestfeedback'),
            get_string('status', 'mod_idetestfeedback'),
            get_string('passed', 'mod_idetestfeedback'),
            get_string('failed', 'mod_idetestfeedback'),
            get_string('timecreated', 'mod_idetestfeedback'),
            '',
        ];

        foreach ($runs as $run) {
            $detailurl = new \moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->id, 'runid' => $run->id]);
            $failed = (int) $run->failedcount + (int) $run->errorcount;

            $row = new \html_table_row();
            $row->cells[] = fullname($run);
            $row->cells[] = s($run->ide);
            $row->cells[] = s($run->projectname ?? '');
            $row->cells[] = $this->status_badge($run->status);
            $row->cells[] = \html_writer::tag('span', (string) (int) $run->passedcount, ['style' => 'color:#28a745;font-weight:bold;']);
            $row->cells[] = \html_writer::tag('span', (string) $failed, $failed > 0 ? ['style' => 'color:#dc3545;font-weight:bold;'] : []);
            $row->cells[] = userdate((int) $run->timecreated);
            $row->cells[] = \html_writer::link($detailurl, get_string('viewdetail', 'mod_idetestfeedback'));

            $table->data[] = $row;
        }

        echo \html_writer::table($table);
    }

    protected function render_student_view(): void {
        echo $this->output->heading(get_string('myresults', 'mod_idetestfeedback'), 2);

        $runs = $this->repository->get_runs_for_user($this->instance->id, $this->user->id);

        if (empty($runs)) {
            echo $this->output->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
            return;
        }

        $total = count($runs);
        $passedrunscount = count(array_filter($runs, fn($r) => $r->status === 'PASSED'));
        $rate = $total > 0 ? round($passedrunscount / $total * 100) : 0;

        echo \html_writer::div(
            get_string('summarytext', 'mod_idetestfeedback', ['total' => $total, 'rate' => $rate]),
            'alert alert-info'
        );

        $table = new \html_table();
        $table->attributes['class'] = 'table table-striped table-bordered';
        $table->head = [
            get_string('ide', 'mod_idetestfeedback'),
            get_string('projectname', 'mod_idetestfeedback'),
            get_string('status', 'mod_idetestfeedback'),
            get_string('passed', 'mod_idetestfeedback'),
            get_string('failed', 'mod_idetestfeedback'),
            get_string('timecreated', 'mod_idetestfeedback'),
            '',
        ];

        foreach ($runs as $run) {
            $detailurl = new \moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->id, 'runid' => $run->id]);
            $failed = (int) $run->failedcount + (int) $run->errorcount;

            $row = new \html_table_row();
            $row->cells[] = s($run->ide);
            $row->cells[] = s($run->projectname ?? '');
            $row->cells[] = $this->status_badge($run->status);
            $row->cells[] = \html_writer::tag('span', (string) (int) $run->passedcount, ['style' => 'color:#28a745;font-weight:bold;']);
            $row->cells[] = \html_writer::tag('span', (string) $failed, $failed > 0 ? ['style' => 'color:#dc3545;font-weight:bold;'] : []);
            $row->cells[] = userdate((int) $run->timecreated);
            $row->cells[] = \html_writer::link($detailurl, get_string('viewdetail', 'mod_idetestfeedback'));

            $row->attributes['class'] = match ($run->status) {
                'PASSED' => 'table-success',
                'FAILED', 'ERROR' => 'table-danger',
                'SKIPPED' => 'table-warning',
                default => '',
            };

            $table->data[] = $row;
        }

        echo \html_writer::table($table);
    }

    protected function status_badge(string $status): string {
        $colors = [
            'PASSED' => '#28a745',
            'FAILED' => '#dc3545',
            'ERROR' => '#6c1a1a',
            'SKIPPED' => '#6c757d',
        ];
        $color = $colors[$status] ?? '#6c757d';

        return \html_writer::tag('span', s($status), [
            'style' => "display:inline-block;background:{$color};color:#fff;padding:2px 10px;" .
                'border-radius:3px;font-size:0.82em;font-weight:bold;letter-spacing:0.02em;',
        ]);
    }
}
