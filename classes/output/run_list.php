<?php
namespace mod_idetestfeedback\output;

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

class run_list implements renderable, templatable {

    public function __construct(
        protected readonly array $runs,
        protected readonly int $cmid,
        protected readonly bool $showstudent,
        protected readonly bool $colourrows,
        protected readonly string $summary = '',
        protected readonly string $totaltext = '',
        protected readonly string $filters = '',
        protected readonly string $pagingbar = ''
    ) {
    }

    /**
     * Exports the run list for mod_idetestfeedback/run_list.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'showstudent' => $this->showstudent,
            'summary' => $this->summary,
            'totaltext' => $this->totaltext,
            'filters' => $this->filters,
            'pagingbar' => $this->pagingbar,
            'rows' => $this->rows($output),
        ];
    }

    /**
     * Builds one template row per run.
     *
     * @param renderer_base $output
     * @return array[]
     */
    protected function rows(renderer_base $output): array {
        $rows = [];

        foreach ($this->runs as $run) {
            $failed = (int) $run->failedcount + (int) $run->errorcount;
            $detailurl = new moodle_url('/mod/idetestfeedback/view.php', [
                'id' => $this->cmid,
                'runid' => $run->id,
            ]);

            $rows[] = [
                'rowclass' => $this->colourrows ? $this->row_class($run->status) : '',
                'student' => $this->showstudent ? fullname($run) : '',
                'ide' => $run->ide,
                'projectname' => (string) ($run->projectname ?? ''),
                'badge' => (new status_badge($run->status))->export_for_template($output),
                'passed' => (int) $run->passedcount,
                'failed' => $failed,
                'hasfailed' => $failed > 0,
                'date' => userdate((int) $run->timecreated),
                'detailurl' => $detailurl->out(false),
            ];
        }

        return $rows;
    }

    /**
     * The Bootstrap table row class for a run status.
     *
     * @param string $status the run status
     * @return string
     */
    protected function row_class(string $status): string {
        return match ($status) {
            'PASSED' => 'table-success',
            'FAILED', 'ERROR' => 'table-danger',
            'SKIPPED' => 'table-warning',
            default => '',
        };
    }
}
