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

use mod_idetestfeedback\form\feedback_form;
use plugin_renderer_base;

/**
 * Renderer for the activity's pages.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    public function render_run_list(run_list $list): string {
        return $this->render_from_template(
            'mod_idetestfeedback/run_list',
            $list->export_for_template($this)
        );
    }

    public function render_run_detail(run_detail $detail): string {
        $data = $detail->export_for_template($this);

        $out = $this->render_from_template('mod_idetestfeedback/run_detail', $data);

        if (!$data['hasresults']) {
            return $out . $this->notification(get_string('noresults', 'mod_idetestfeedback'), 'info');
        }

        $results = $this->render_from_template('mod_idetestfeedback/run_results', $data);

        if (!$detail->can_comment()) {
            return $out . $results;
        }

        $form = new feedback_form($detail->get_form_url(), [
            'resultstable' => $results,
            'cmid' => $detail->get_cmid(),
            'runid' => $detail->get_run()->id,
        ]);

        return $out . $form->render();
    }
}
