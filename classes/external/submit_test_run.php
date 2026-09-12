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

namespace mod_idetestfeedback\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;
use context_course;
use context_module;
use mod_idetestfeedback\event\test_run_submitted;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\status;
use mod_idetestfeedback\local\validation_exception;

/**
 * The web service the IDE plugin posts a finished test run to.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_test_run extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email'         => new external_value(PARAM_EMAIL,    'Email of the user, from the OAuth identity asserted by the Java middleware'),
            'assignmentkey' => new external_value(PARAM_ALPHANUMEXT, 'Assignment key shown in the activity'),
            'ide'           => new external_value(PARAM_ALPHAEXT, 'IDE identifier, e.g. VSCODE'),
            'projectname'   => new external_value(PARAM_RAW,      'Project name',    VALUE_DEFAULT, null),
            'commithash'    => new external_value(PARAM_TEXT,     'Git commit hash',  VALUE_DEFAULT, null),
            'startedat'     => new external_value(PARAM_INT,      'Run start (unix)', VALUE_DEFAULT, null),
            'finishedat'    => new external_value(PARAM_INT,      'Run end (unix)',   VALUE_DEFAULT, null),
            'results'       => new external_multiple_structure(
                new external_single_structure([
                    'testname'       => new external_value(PARAM_RAW,         'Test name'),
                    'status'         => new external_value(PARAM_ALPHA,       'PASSED | FAILED | SKIPPED | ERROR'),
                    'testsuite'      => new external_value(PARAM_RAW,         'Test suite',       VALUE_DEFAULT, null),
                    'durationms'     => new external_value(PARAM_INT,         'Duration ms',      VALUE_DEFAULT, null),
                    'message'        => new external_value(PARAM_RAW,         'Failure message',  VALUE_DEFAULT, null),
                    'stacktracehash' => new external_value(PARAM_ALPHANUMEXT, 'Stack trace hash', VALUE_DEFAULT, null),
                ])
            ),
        ]);
    }

    public static function execute(
        string $email,
        string $assignmentkey,
        string $ide,
        ?string $projectname,
        ?string $commithash,
        ?int $startedat,
        ?int $finishedat,
        array $results
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'email'         => $email,
            'assignmentkey' => $assignmentkey,
            'ide'           => $ide,
            'projectname'   => $projectname,
            'commithash'    => $commithash,
            'startedat'     => $startedat,
            'finishedat'    => $finishedat,
            'results'       => $results,
        ]);

        self::validate_context(context_system::instance());
        require_capability('mod/idetestfeedback:submit', context_system::instance());

        $repository = new repository($DB);

        $userid = self::validate_submission($repository, $params);

        $instance = $repository->get_instance_by_assignmentkey($params['assignmentkey']);
        $cm = get_coursemodule_from_instance('idetestfeedback', $instance->id, $instance->course, false, MUST_EXIST);

        $run = self::store_run($repository, $instance, $userid, $params);

        self::update_completion($instance, $cm, $userid);
        self::log_submission($cm, $run, $userid);

        return ['runid' => $run->id];
    }

    private static function update_completion(\stdClass $instance, \stdClass $cm, int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/completionlib.php');

        if (empty($instance->completionpassrun)) {
            return;
        }

        $completion = new \completion_info(get_course($instance->course));
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }

    private static function log_submission(\stdClass $cm, \stdClass $run, int $userid): void {
        test_run_submitted::create([
            'objectid'      => $run->id,
            'context'       => context_module::instance($cm->id),
            'relateduserid' => $userid,
            'other'         => ['status' => $run->status],
        ])->trigger();
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Created run ID'),
        ]);
    }

    private static function validate_submission(repository $repository, array $params): int {
        $user = $repository->get_active_user_by_email($params['email']);
        if (!$user) {
            throw new validation_exception('idetestfeedback_validation_usernotfound', $params['email']);
        }

        $instance = $repository->get_instance_by_assignmentkey($params['assignmentkey']);
        if (!$instance) {
            throw new validation_exception('idetestfeedback_validation_assignmentnotfound', $params['assignmentkey']);
        }

        $coursecontext = context_course::instance($instance->course);
        if (!is_enrolled($coursecontext, $user->id)) {
            throw new validation_exception('idetestfeedback_validation_notenrolled');
        }

        $now = time();
        if ($instance->timeopen > 0 && $now < $instance->timeopen) {
            throw new validation_exception('idetestfeedback_validation_windownotopen', userdate($instance->timeopen));
        }
        if ($instance->timeclose > 0 && $now > $instance->timeclose) {
            throw new validation_exception('idetestfeedback_validation_windowclosed', userdate($instance->timeclose));
        }

        foreach ($params['results'] as $result) {
            if (status::tryFrom($result['status']) === null) {
                throw new validation_exception('idetestfeedback_validation_invalidstatus', $result['status']);
            }
        }

        if (empty($params['results'])) {
            throw new validation_exception('idetestfeedback_validation_noresults');
        }

        return $user->id;
    }

    private static function store_run(repository $repository, \stdClass $instance, int $userid, array $params): \stdClass {
        $now = time();

        $run                 = new \stdClass();
        $run->idetestfeedbackid = $instance->id;
        $run->userid         = $userid;
        $run->ide            = $params['ide'];
        $run->projectname    = $params['projectname'] ?? null;
        $run->commithash     = $params['commithash']  ?? null;
        $run->startedat      = $params['startedat']   ?? null;
        $run->finishedat     = $params['finishedat']  ?? null;
        $run->status         = self::resolve_run_status($params['results'])->value;
        $run->passedcount    = self::count_status($params['results'], status::PASSED);
        $run->failedcount    = self::count_status($params['results'], status::FAILED);
        $run->skippedcount   = self::count_status($params['results'], status::SKIPPED);
        $run->errorcount     = self::count_status($params['results'], status::ERROR);
        $run->timecreated    = $now;

        $run->id = $repository->insert_run($run);

        foreach ($params['results'] as $r) {
            $result                 = new \stdClass();
            $result->runid          = $run->id;
            $result->testsuite      = $r['testsuite']      ?? null;
            $result->testname       = $r['testname'];
            $result->status         = $r['status'];
            $result->durationms     = $r['durationms']     ?? null;
            $result->message        = $r['message']        ?? null;
            $result->stacktracehash = $r['stacktracehash'] ?? null;
            $result->timecreated    = $now;
            $repository->insert_result($result);
        }

        return $run;
    }

    private static function resolve_run_status(array $results): status {
        if (self::count_status($results, status::ERROR) > 0) {
            return status::ERROR;
        }
        if (self::count_status($results, status::FAILED) > 0) {
            return status::FAILED;
        }
        if (self::count_status($results, status::PASSED) > 0) {
            return status::PASSED;
        }

        return status::SKIPPED;
    }

    private static function count_status(array $results, status $status): int {
        return count(array_filter($results, fn($r) => ($r['status'] ?? '') === $status->value));
    }
}
