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

use core\context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_idetestfeedback\event\test_run_submitted;
use mod_idetestfeedback\local\git_remote;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\run_recorder;
use mod_idetestfeedback\local\status;
use mod_idetestfeedback\local\submission;
use mod_idetestfeedback\local\submission_window;
use mod_idetestfeedback\local\validation_exception;

/**
 * The web service the IDE plugin posts a finished test run to.
 *
 * The run is attributed to the 'email' parameter, so the Java middleware
 * holding the service token is the trust boundary.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_test_run extends external_api {
    /** @var int Most test case results accepted in one submission. */
    private const MAX_RESULTS = 5000;

    /** @var int Most test files accepted in one submission. */
    private const MAX_FILES = 200;

    /**
     * Describes the web service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email'         => new external_value(PARAM_EMAIL, 'Email of the user, from the identity the middleware asserted'),
            'assignmentkey' => new external_value(PARAM_ALPHANUMEXT, 'Assignment key shown in the activity'),
            'ide'           => new external_value(PARAM_ALPHANUMEXT, 'IDE identifier, e.g. VSCODE'),
            'projectname'   => new external_value(PARAM_TEXT, 'Project name', VALUE_DEFAULT, null),
            'commithash'    => new external_value(PARAM_ALPHANUM, 'Git commit hash', VALUE_DEFAULT, null),
            'repourl'       => new external_value(
                PARAM_TEXT,
                'Git remote URL; any credentials in it are dropped',
                VALUE_DEFAULT,
                null
            ),
            'startedatms'   => new external_value(PARAM_INT, 'Run start (epoch milliseconds)', VALUE_DEFAULT, null),
            'finishedatms'  => new external_value(PARAM_INT, 'Run end (epoch milliseconds)', VALUE_DEFAULT, null),
            'payload'       => new external_value(
                PARAM_RAW,
                'JSON object holding the run\'s "results" and "testfiles"; see payload_parameters()'
            ),
            'capturedisabled' => new external_value(
                PARAM_BOOL,
                'The student disabled code capture',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * The shape of the 'payload' parameter, sent as one JSON string so a large
     * run does not overrun max_input_vars.
     *
     * @return external_function_parameters
     */
    private static function payload_parameters(): external_function_parameters {
        return new external_function_parameters([
            'results'       => new external_multiple_structure(
                new external_single_structure([
                    'testname'   => new external_value(PARAM_RAW, 'Test name'),
                    'status'     => new external_value(PARAM_ALPHA, 'PASSED, FAILED, SKIPPED or ERROR; case insensitive'),
                    'testsuite'  => new external_value(PARAM_RAW, 'Test suite', VALUE_DEFAULT, null),
                    'durationms' => new external_value(PARAM_INT, 'Duration ms', VALUE_DEFAULT, null),
                    'message'    => new external_value(PARAM_RAW, 'Failure message', VALUE_DEFAULT, null),
                    'source'     => new external_single_structure([
                        'path'      => new external_value(PARAM_RAW, 'File the test lives in', VALUE_DEFAULT, null),
                        'startline' => new external_value(
                            PARAM_INT,
                            'First line, 1-based inclusive',
                            VALUE_DEFAULT,
                            null
                        ),
                        'endline'   => new external_value(
                            PARAM_INT,
                            'Last line, 1-based inclusive',
                            VALUE_DEFAULT,
                            null
                        ),
                    ], 'Where in the run\'s files the test case came from', VALUE_OPTIONAL),
                ]),
                'The test case results of the run, at least one'
            ),
            'testfiles'     => new external_multiple_structure(
                new external_single_structure([
                    'path'      => new external_value(PARAM_RAW, 'Repo-relative path'),
                    'content'   => new external_value(PARAM_RAW, 'File contents', VALUE_DEFAULT, null),
                    'truncated' => new external_value(PARAM_BOOL, 'The content was cut short', VALUE_DEFAULT, false),
                ]),
                'Test files captured with the run',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Stores one finished test run against the activity the key names.
     *
     * @param string $email the student the middleware authenticated
     * @param string $assignmentkey the key shown on the activity
     * @param string $ide the IDE the run came from
     * @param string|null $projectname the project the tests ran in
     * @param string|null $commithash the commit the tests ran against
     * @param string|null $repourl the git remote the tests ran in
     * @param int|null $startedatms when the run started, in epoch milliseconds
     * @param int|null $finishedatms when the run finished, in epoch milliseconds
     * @param string $payload JSON holding the run's results and captured test files
     * @param bool $capturedisabled whether the student turned source capture off
     * @return array the new run id
     */
    public static function execute(
        string $email,
        string $assignmentkey,
        string $ide,
        ?string $projectname,
        ?string $commithash,
        ?string $repourl,
        ?int $startedatms,
        ?int $finishedatms,
        string $payload,
        bool $capturedisabled
    ): array {
        global $DB;

        self::validate_context(context\system::instance());
        require_capability('mod/idetestfeedback:submit', context\system::instance());

        $params = self::validate_parameters(self::execute_parameters(), [
            'email'           => $email,
            'assignmentkey'   => $assignmentkey,
            'ide'             => $ide,
            'projectname'     => $projectname,
            'commithash'      => $commithash,
            'repourl'         => $repourl,
            'startedatms'     => $startedatms,
            'finishedatms'    => $finishedatms,
            'payload'         => $payload,
            'capturedisabled' => $capturedisabled,
        ]);
        $submission = self::decode_submission($params);
        self::validate_submission($submission);

        $repository = new repository($DB);

        [$userid, $instance, $cm] = self::resolve_target($repository, $submission);

        $run = (new run_recorder($repository))->record($submission, (int) $instance->id, $userid);

        self::update_completion($instance, $cm, $userid);
        self::log_submission($cm, $run, $userid);

        return ['runid' => $run->id];
    }

    /**
     * Describes the web service return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Created run ID'),
        ]);
    }

    /**
     * Unpacks the payload into a submission, with statuses uppercased and
     * captured code dropped when capture was disabled.
     *
     * @param array $params the validated call parameters
     * @return submission
     */
    private static function decode_submission(array $params): submission {
        $decoded = json_decode($params['payload'], true);
        if (!is_array($decoded)) {
            throw new validation_exception('validation_invalidpayload');
        }

        self::check_list_size($decoded, 'results', self::MAX_RESULTS, 'validation_toomanyresults');
        self::check_list_size($decoded, 'testfiles', self::MAX_FILES, 'validation_toomanyfiles');

        $payload = self::validate_parameters(self::payload_parameters(), $decoded);

        $results = array_map(
            fn(array $result) => ['status' => \core_text::strtoupper($result['status'])] + $result,
            $payload['results']
        );

        $testfiles = $payload['testfiles'];
        if ($params['capturedisabled']) {
            $testfiles = array_map(fn(array $file) => ['content' => null, 'truncated' => false] + $file, $testfiles);
        }

        return new submission(
            email: $params['email'],
            assignmentkey: $params['assignmentkey'],
            ide: $params['ide'],
            projectname: $params['projectname'],
            commithash: $params['commithash'],
            repourl: git_remote::strip_credentials($params['repourl']),
            startedatms: $params['startedatms'],
            finishedatms: $params['finishedatms'],
            results: $results,
            testfiles: $testfiles,
            capturedisabled: $params['capturedisabled'],
        );
    }

    /**
     * Rejects an oversized list before validate_parameters() walks it.
     *
     * @param array $decoded the decoded payload
     * @param string $key the list to count
     * @param int $max the most entries accepted
     * @param string $errorcode the language string to reject with
     */
    private static function check_list_size(array $decoded, string $key, int $max, string $errorcode): void {
        if (is_array($decoded[$key] ?? null) && count($decoded[$key]) > $max) {
            throw new validation_exception($errorcode, $max);
        }
    }

    /**
     * Rejects values that are unusable on their own, before anything is looked up.
     *
     * @param submission $submission
     */
    private static function validate_submission(submission $submission): void {
        if (trim($submission->email) === '') {
            throw new validation_exception('validation_noemail');
        }
        if (!$submission->results) {
            throw new validation_exception('validation_noresults');
        }
        if (trim($submission->ide) === '') {
            throw new validation_exception('validation_noide');
        }

        foreach ($submission->results as $result) {
            if (trim($result['testname']) === '') {
                throw new validation_exception('validation_notestname');
            }
            if (status::tryFrom($result['status']) === null) {
                throw new validation_exception('validation_invalidstatus', $result['status']);
            }
            if ($result['durationms'] !== null && $result['durationms'] < 0) {
                throw new validation_exception('validation_invalidtiming');
            }
            self::validate_source($result['source'] ?? null);
        }

        foreach ($submission->testfiles as $file) {
            if (trim($file['path']) === '') {
                throw new validation_exception('validation_nofilepath');
            }
        }

        foreach ([$submission->startedatms, $submission->finishedatms] as $time) {
            if ($time !== null && $time < 0) {
                throw new validation_exception('validation_invalidtiming');
            }
        }
        if (
            $submission->startedatms !== null && $submission->finishedatms !== null
                && $submission->finishedatms < $submission->startedatms
        ) {
            throw new validation_exception('validation_invalidtiming');
        }
    }

    /**
     * Rejects line numbers without a file, and incomplete or impossible line ranges.
     *
     * @param array|null $source the source block of one result, absent when the IDE sent none
     */
    private static function validate_source(?array $source): void {
        if ($source === null || ($source['startline'] === null && $source['endline'] === null)) {
            return;
        }

        if (trim((string) $source['path']) === '') {
            throw new validation_exception('validation_nosourcefilepath');
        }

        if (
            $source['startline'] === null || $source['endline'] === null
                || $source['startline'] < 1 || $source['endline'] < $source['startline']
        ) {
            throw new validation_exception('validation_invalidsourcelines');
        }
    }

    /**
     * Resolves the student and activity a submission names, and checks it is welcome.
     *
     * @param repository $repository the activity's database access
     * @param submission $submission
     * @return array{0:int,1:\stdClass,2:\cm_info} [user id, activity instance, course module]
     */
    private static function resolve_target(repository $repository, submission $submission): array {
        $users = $repository->get_active_users_by_email($submission->email);
        if (!$users) {
            throw new validation_exception('validation_usernotfound', $submission->email);
        }
        if (count($users) > 1) {
            throw new validation_exception('validation_ambiguousemail', $submission->email);
        }
        $userid = (int) reset($users)->id;

        $instance = $repository->get_instance_by_assignmentkey($submission->assignmentkey);
        if (!$instance) {
            throw new validation_exception('validation_assignmentnotfound', $submission->assignmentkey);
        }

        if (!is_enrolled(context\course::instance($instance->course), $userid, '', true)) {
            throw new validation_exception('validation_notenrolled');
        }

        $cm = get_fast_modinfo($instance->course, $userid)->get_instances_of('idetestfeedback')[$instance->id] ?? null;
        if (
            !$cm || $cm->deletioninprogress || !$cm->uservisible
                || !has_capability('mod/idetestfeedback:view', $cm->context, $userid)
        ) {
            throw new validation_exception('validation_activityunavailable');
        }
        if (!has_capability('mod/idetestfeedback:recordruns', $cm->context, $userid)) {
            throw new validation_exception('validation_cannotrecordruns');
        }

        match (submission_window::of($instance, time())) {
            submission_window::NOT_YET_OPEN => throw new validation_exception(
                'validation_windownotopen',
                userdate($instance->timeopen)
            ),
            submission_window::CLOSED => throw new validation_exception(
                'validation_windowclosed',
                userdate($instance->timeclose)
            ),
            submission_window::OPEN => null,
        };

        return [$userid, $instance, $cm];
    }

    /**
     * Re-evaluates the custom completion rule for the student who submitted.
     *
     * @param \stdClass $instance the activity the run belongs to
     * @param \cm_info $cm the activity's course module
     * @param int $userid the student the run is attributed to
     */
    private static function update_completion(\stdClass $instance, \cm_info $cm, int $userid): void {
        global $CFG;

        if (empty($instance->completionpassrun)) {
            return;
        }

        require_once($CFG->libdir . '/completionlib.php');

        $completion = new \completion_info($cm->get_course());
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }

    /**
     * Triggers the test run submitted event.
     *
     * @param \cm_info $cm the activity's course module
     * @param \stdClass $run the stored run
     * @param int $userid the student the run is attributed to
     */
    private static function log_submission(\cm_info $cm, \stdClass $run, int $userid): void {
        test_run_submitted::create([
            'objectid'      => $run->id,
            'context'       => context\module::instance($cm->id),
            'userid'        => $userid,
            'relateduserid' => $userid,
            'other'         => ['status' => $run->status],
        ])->trigger();
    }
}
