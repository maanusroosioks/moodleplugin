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

use core\output\notification;
use core\output\plugin_renderer_base;
use core\output\single_select;
use core\url;
use stdClass;

/**
 * Renderer for the activity's pages.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * The run summary, its results (inside the feedback form when the viewer may
     * comment) and its files.
     *
     * @param run_detail $detail one run in full
     * @return string
     */
    public function render_run_detail(run_detail $detail): string {
        $data = $detail->export_for_template($this);

        $out = $this->render_from_template('mod_idetestfeedback/run_detail', $data);

        if (!$data['hasresults']) {
            $out .= $this->notification(get_string('noresults', 'mod_idetestfeedback'), notification::NOTIFY_INFO);
        } else if (!$data['cancomment']) {
            $out .= $this->render_from_template('mod_idetestfeedback/run_results', $data);
        } else {
            $data['sesskey'] = sesskey();
            $out .= $this->render_from_template('mod_idetestfeedback/run_feedback_form', $data);
        }

        if ($data['hasfiles']) {
            $out .= $this->render_from_template('mod_idetestfeedback/run_files', $data);
        }

        if ($data['hascode']) {
            $this->require_highlighter();
        }

        return $out;
    }

    /**
     * The key students paste into their IDE plugin.
     *
     * Safe to show everyone who can see the activity: it routes submissions to
     * the instance but grants nothing on its own.
     *
     * @param string $assignmentkey the activity's key
     * @return string
     */
    public function assignment_key(string $assignmentkey): string {
        return $this->render_from_template('mod_idetestfeedback/assignment_key', ['assignmentkey' => $assignmentkey]);
    }

    /**
     * The menus that narrow the run list, side by side.
     *
     * @param single_select[] $selects the menus
     * @return string
     */
    public function run_filters(array $selects): string {
        return $this->render_from_template('mod_idetestfeedback/run_filters', [
            'selects' => array_map(fn(single_select $select) => $this->render($select), $selects),
        ]);
    }

    /**
     * One of a run's files on its own page, expanded.
     *
     * @param stdClass $file the file, with the body it points at
     * @param url $backurl the run the file was opened from
     * @return string
     */
    public function run_file(stdClass $file, url $backurl): string {
        $row = ['inline' => true, 'open' => true] + run_detail::file_row($file);

        if ($row['hascontent'] && $row['language'] !== '') {
            $this->require_highlighter();
        }

        return $this->render_from_template('mod_idetestfeedback/run_file', [
            'backurl' => $backurl->out(false),
            'files' => [$row],
        ]);
    }

    /**
     * Colours language-tagged code blocks with filter_codehighlighter's Prism build,
     * which works whether or not the filter is enabled.
     */
    protected function require_highlighter(): void {
        if (\core_component::get_component_directory('filter_codehighlighter') === null) {
            return;
        }

        $this->page->requires->js_call_amd('filter_codehighlighter/prism-init');
    }
}
