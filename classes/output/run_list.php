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

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * A page of test runs, as a table.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
                'rowclass' => $this->colourrows ? status_badge::row_class($run->status) : '',
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
}
