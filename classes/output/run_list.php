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

namespace mod_idetestfeedback\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core\url;
use mod_idetestfeedback\local\git_remote;
use stdClass;

/**
 * A page of test runs, as a table.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_list implements renderable, templatable {
    /**
     * Creates the run list.
     *
     * @param stdClass[] $runs the runs on this page
     * @param url $detailurl the run detail page, carrying the list state; each row adds its run id
     * @param bool $showstudent whether to show the student column
     * @param bool $colourrows whether to tint each row by its status
     * @param bool $viewfullnames whether the viewer may see full names
     * @param string $summary a pass rate summary shown above the table
     * @param string $totaltext how many runs matched, shown above the table
     * @param string $filters the rendered filter menus
     * @param string $pagingbar the rendered paging bar
     */
    public function __construct(
        /** @var stdClass[] The runs on this page */
        protected readonly array $runs,
        /** @var url The run detail page, carrying the list state; each row adds its run id */
        protected readonly url $detailurl,
        /** @var bool Whether to show the student column */
        protected readonly bool $showstudent,
        /** @var bool Whether to tint each row by its status */
        protected readonly bool $colourrows,
        /** @var bool Whether the viewer may see full names */
        protected readonly bool $viewfullnames = false,
        /** @var string A pass rate summary shown above the table */
        protected readonly string $summary = '',
        /** @var string How many runs matched, shown above the table */
        protected readonly string $totaltext = '',
        /** @var string The rendered filter menus */
        protected readonly string $filters = '',
        /** @var string The rendered paging bar */
        protected readonly string $pagingbar = ''
    ) {
    }

    /**
     * Exports the run list for mod_idetestfeedback/run_list.
     *
     * @param renderer_base $output
     * @return array
     */
    #[\Override]
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
            $detailurl = new url($this->detailurl, ['runid' => $run->id]);
            $commithash = (string) ($run->commithash ?? '');

            $rows[] = [
                'rowclass' => $this->colourrows ? status_badge::row_class($run->status) : '',
                'student' => $this->showstudent ? fullname($run, $this->viewfullnames) : '',
                'ide' => $run->ide,
                'projectname' => (string) ($run->projectname ?? ''),
                'commithash' => $commithash,
                'shorthash' => $commithash === '' ? '' : git_remote::short_hash($commithash),
                'commiturl' => (string) git_remote::commit_url($run->repourl ?? null, $commithash),
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
}
