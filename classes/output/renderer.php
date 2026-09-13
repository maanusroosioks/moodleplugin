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

use plugin_renderer_base;

/**
 * Renderer for the activity's pages.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * @param run_list $list a page of runs
     * @return string
     */
    public function render_run_list(run_list $list): string {
        return $this->render_from_template(
            'mod_idetestfeedback/run_list',
            $list->export_for_template($this)
        );
    }

    /**
     * The run summary, then its results, wrapped in the feedback form when the
     * viewer may comment.
     *
     * @param run_detail $detail one run in full
     * @return string
     */
    public function render_run_detail(run_detail $detail): string {
        $data = $detail->export_for_template($this);

        $out = $this->render_from_template('mod_idetestfeedback/run_detail', $data);

        if (!$data['hasresults']) {
            return $out . $this->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
        }

        if (!$data['cancomment']) {
            return $out . $this->render_from_template('mod_idetestfeedback/run_results', $data);
        }

        $data['sesskey'] = sesskey();

        return $out . $this->render_from_template('mod_idetestfeedback/run_feedback_form', $data);
    }
}
