<?php
namespace mod_idetestfeedback\output;

use mod_idetestfeedback\form\feedback_form;
use plugin_renderer_base;

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
