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

use context;
use mod_idetestfeedback\local\required_tests;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * One test run in full: its summary and every test case result.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_detail implements renderable, templatable {

    public function __construct(
        protected readonly stdClass $instance,
        protected readonly stdClass $run,
        protected readonly array $results,
        protected readonly ?string $studentname,
        protected readonly context $context,
        protected readonly int $cmid,
        protected readonly bool $cancomment
    ) {
    }

    /**
     * Whether the viewer may edit feedback on this run.
     *
     * @return bool
     */
    public function can_comment(): bool {
        return $this->cancomment;
    }

    /**
     * Where the feedback form posts back to.
     *
     * @return moodle_url
     */
    public function get_form_url(): moodle_url {
        return new moodle_url('/mod/idetestfeedback/view.php', [
            'id' => $this->cmid,
            'runid' => $this->run->id,
        ]);
    }

    /**
     * The run this detail view describes.
     *
     * @return stdClass
     */
    public function get_run(): stdClass {
        return $this->run;
    }

    /**
     * The course module this run belongs to.
     *
     * @return int
     */
    public function get_cmid(): int {
        return $this->cmid;
    }

    /**
     * Exports the run summary and results for the run detail templates.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $entries = required_tests::parse($this->instance->requiredtests ?? null);
        $showrequired = $entries !== [];

        return [
            'backurl' => (new moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->cmid]))->out(false),
            'meta' => $this->meta_rows($output, $entries, $showrequired),
            'hasresults' => $this->results !== [],
            'showrequired' => $showrequired,
            'showfeedback' => $this->cancomment || $this->has_feedback(),
            'cancomment' => $this->cancomment,
            'rows' => $this->result_rows($output, $entries, $showrequired),
        ];
    }

    /**
     * Whether any result on this run already carries feedback.
     *
     * @return bool
     */
    protected function has_feedback(): bool {
        foreach ($this->results as $result) {
            if (trim((string) ($result->feedback ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the run summary rows.
     *
     * Each row carries a label plus exactly one of text, code, strong or
     * badge, so the template decides the markup and nothing is exported
     * pre-escaped.
     *
     * @param renderer_base $output
     * @param string[] $entries the activity's defined test cases
     * @param bool $showrequired whether the activity defines test cases at all
     * @return array[]
     */
    protected function meta_rows(renderer_base $output, array $entries, bool $showrequired): array {
        $rows = [];

        if ($this->studentname !== null) {
            $rows[] = [
                'label' => get_string('student', 'mod_idetestfeedback'),
                'text' => $this->studentname,
            ];
        }

        $rows[] = ['label' => get_string('ide', 'mod_idetestfeedback'), 'text' => $this->run->ide];

        if ($this->run->projectname) {
            $rows[] = [
                'label' => get_string('projectname', 'mod_idetestfeedback'),
                'text' => $this->run->projectname,
            ];
        }
        if ($this->run->commithash) {
            $rows[] = [
                'label' => get_string('commithash', 'mod_idetestfeedback'),
                'code' => $this->run->commithash,
            ];
        }

        $rows[] = [
            'label' => get_string('status', 'mod_idetestfeedback'),
            'badge' => (new status_badge($this->run->status))->export_for_template($output),
        ];

        $total = (int) $this->run->passedcount + (int) $this->run->failedcount
            + (int) $this->run->skippedcount + (int) $this->run->errorcount;
        $rows[] = [
            'label' => get_string('passed', 'mod_idetestfeedback'),
            'strong' => (int) $this->run->passedcount . ' / ' . $total,
        ];

        if ((int) $this->run->skippedcount > 0) {
            $rows[] = [
                'label' => get_string('skipped', 'mod_idetestfeedback'),
                'text' => (string) (int) $this->run->skippedcount,
            ];
        }

        if ($showrequired) {
            $rows = array_merge($rows, $this->required_rows($entries));
        }

        $rows[] = [
            'label' => get_string('timecreated', 'mod_idetestfeedback'),
            'text' => userdate((int) $this->run->timecreated),
        ];

        return $rows;
    }

    /**
     * Builds the summary rows scoring this run against the defined test cases.
     *
     * @param string[] $entries the activity's defined test cases
     * @return array[]
     */
    protected function required_rows(array $entries): array {
        $required = required_tests::evaluate($entries, $this->results);
        if ($required['total'] === 0) {
            return [];
        }

        $rows = [[
            'label' => get_string('requiredprogress', 'mod_idetestfeedback'),
            'strong' => $required['passed'] . ' / ' . $required['total'],
        ]];

        $outstanding = [];
        if ($required['failed'] > 0) {
            $outstanding[] = get_string('requiredfailedn', 'mod_idetestfeedback', $required['failed']);
        }
        if ($required['skipped'] > 0) {
            $outstanding[] = get_string('requiredskippedn', 'mod_idetestfeedback', $required['skipped']);
        }
        if ($required['missing'] > 0) {
            $outstanding[] = get_string('requiredmissingn', 'mod_idetestfeedback', $required['missing']);
        }

        if ($outstanding) {
            $rows[] = [
                'label' => get_string('requiredoutstanding', 'mod_idetestfeedback'),
                'text' => implode('; ', $outstanding),
            ];
        }

        return $rows;
    }

    /**
     * Builds one template row per test case result.
     *
     * @param renderer_base $output
     * @param string[] $entries the activity's defined test cases
     * @param bool $showrequired whether the activity defines test cases at all
     * @return array[]
     */
    protected function result_rows(renderer_base $output, array $entries, bool $showrequired): array {
        $rows = [];

        foreach ($this->results as $result) {
            $feedback = trim((string) ($result->feedback ?? ''));

            $rows[] = [
                'rowclass' => status_badge::row_class($result->status),
                'testsuite' => (string) ($result->testsuite ?? ''),
                'testname' => $result->testname,
                'required' => $showrequired && required_tests::is_required(
                    $entries,
                    $result->testsuite ?? null,
                    $result->testname
                ),
                'badge' => (new status_badge($result->status))->export_for_template($output),
                'duration' => $result->durationms !== null
                    ? get_string('durationunit', 'mod_idetestfeedback', (int) $result->durationms)
                    : '',
                'message' => (string) ($result->message ?? ''),
                'feedbackname' => 'feedback_' . $result->id,
                'feedbacklabel' => get_string('feedbackfor', 'mod_idetestfeedback', $result->testname),
                'feedback' => $feedback,
                // Stored feedback is plain text, but format_text() still owns the
                // sanitising, so this is the one field the template prints unescaped.
                'feedbackhtml' => $feedback === '' ? '' : format_text(
                    $result->feedback,
                    (int) $result->feedbackformat,
                    ['context' => $this->context]
                ),
            ];
        }

        return $rows;
    }
}
