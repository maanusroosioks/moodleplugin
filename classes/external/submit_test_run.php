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
 * The run is attributed to the 'email' parameter, so the Java middleware
 * holding the service token is the trust boundary.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_test_run extends external_api {

    /** @var int Most test case results accepted in one submission. */
    private const MAX_RESULTS = 2000;

    /** @var int Most test files accepted in one submission. */
    private const MAX_FILES = 200;

    /** @var int Most bytes of captured source kept per test case result. */
    private const MAX_SOURCE_BYTES = 65536;

    /** @var int Most bytes of content kept per test file. */
    private const MAX_FILE_BYTES = 524288;

    /** @var array<string, int> Column widths from db/install.xml. */
    private const MAX_LENGTHS = [
        'ide'            => 50,
        'projectname'    => 255,
        'commithash'     => 100,
        'testsuite'      => 255,
        'testname'       => 255,
        'sourcekind'     => 20,
        'sourcefilepath' => 1024,
        'sourcecodehash' => 64,
        'filepath'       => 1024,
        'filesha256'     => 64,
    ];

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email'         => new external_value(PARAM_EMAIL, 'Email of the user, from the identity the middleware asserted'),
            'assignmentkey' => new external_value(PARAM_ALPHANUMEXT, 'Assignment key shown in the activity'),
            'ide'           => new external_value(PARAM_TEXT, 'IDE identifier, e.g. VSCODE'),
            'projectname'   => new external_value(PARAM_RAW, 'Project name', VALUE_DEFAULT, null),
            'commithash'    => new external_value(PARAM_TEXT, 'Git commit hash', VALUE_DEFAULT, null),
            'startedat'     => new external_value(PARAM_INT, 'Run start (epoch milliseconds)', VALUE_DEFAULT, null),
            'finishedat'    => new external_value(PARAM_INT, 'Run end (epoch milliseconds)', VALUE_DEFAULT, null),
            'results'       => new external_multiple_structure(
                new external_single_structure([
                    'testname'   => new external_value(PARAM_RAW, 'Test name'),
                    'status'     => new external_value(PARAM_ALPHA, 'PASSED, FAILED, SKIPPED or ERROR; case insensitive'),
                    'testsuite'  => new external_value(PARAM_RAW, 'Test suite', VALUE_DEFAULT, null),
                    'durationms' => new external_value(PARAM_INT, 'Duration ms', VALUE_DEFAULT, null),
                    'message'    => new external_value(PARAM_RAW, 'Failure message', VALUE_DEFAULT, null),
                    'source'     => new external_single_structure([
                        'kind'      => new external_value(PARAM_ALPHANUMEXT, 'What the code is, e.g. TEST',
                            VALUE_DEFAULT, null),
                        'filepath'  => new external_value(PARAM_RAW, 'File the test lives in', VALUE_DEFAULT, null),
                        'startline' => new external_value(PARAM_INT, 'First line', VALUE_DEFAULT, null),
                        'endline'   => new external_value(PARAM_INT, 'Last line', VALUE_DEFAULT, null),
                        'code'      => new external_value(PARAM_RAW, 'The captured source', VALUE_DEFAULT, null),
                        'truncated' => new external_value(PARAM_BOOL, 'The code was cut short', VALUE_DEFAULT, 0),
                        'normalizedcodehash' => new external_value(PARAM_ALPHANUMEXT,
                            'Hash of the normalised source', VALUE_DEFAULT, null),
                    ], 'Where the test case came from', VALUE_OPTIONAL),
                ]),
                'The test case results of the run, at least one'
            ),
            'testfiles'     => new external_multiple_structure(
                new external_single_structure([
                    'path'      => new external_value(PARAM_RAW, 'Repo-relative path'),
                    'sha256'    => new external_value(PARAM_ALPHANUMEXT, 'SHA-256 of the file', VALUE_DEFAULT, null),
                    'content'   => new external_value(PARAM_RAW, 'File contents', VALUE_DEFAULT, null),
                    'truncated' => new external_value(PARAM_BOOL, 'The content was cut short', VALUE_DEFAULT, 0),
                ]),
                'Test files captured with the run',
                VALUE_DEFAULT,
                []
            ),
            'capturedisabled' => new external_value(PARAM_BOOL, 'The student disabled code capture',
                VALUE_DEFAULT, 0),
            'warningacknowledged' => new external_value(PARAM_BOOL,
                'The student acknowledged the empty-test warning', VALUE_DEFAULT, 0),
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
     * @param int|null $startedat when the run started, in epoch milliseconds
     * @param int|null $finishedat when the run finished, in epoch milliseconds
     * @param array $results the run's test case results
     * @param array $testfiles the test files captured with the run
     * @param bool $capturedisabled whether the student turned source capture off
     * @param bool $warningacknowledged whether the student submitted past the empty-test warning
     * @return array the new run id
     */
    public static function execute(
        string $email,
        string $assignmentkey,
        string $ide,
        ?string $projectname,
        ?string $commithash,
        ?int $startedat,
        ?int $finishedat,
        array $results,
        array $testfiles = [],
        bool $capturedisabled = false,
        bool $warningacknowledged = false
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'email'               => $email,
            'assignmentkey'       => $assignmentkey,
            'ide'                 => $ide,
            'projectname'         => $projectname,
            'commithash'          => $commithash,
            'startedat'           => $startedat,
            'finishedat'          => $finishedat,
            'results'             => $results,
            'testfiles'           => $testfiles,
            'capturedisabled'     => $capturedisabled,
            'warningacknowledged' => $warningacknowledged,
        ]);

        self::validate_context(context_system::instance());
        require_capability('mod/idetestfeedback:submit', context_system::instance());

        $repository = new repository($DB);

        [$userid, $instance] = self::validate_submission($repository, $params);
        $cm = get_coursemodule_from_instance('idetestfeedback', $instance->id, $instance->course, false, MUST_EXIST);

        $run = self::store_run($repository, $instance, $userid, $params);

        self::update_completion($repository, $instance, $cm, $userid);
        self::log_submission($cm, $run, $userid);

        return ['runid' => $run->id];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Created run ID'),
        ]);
    }

    /**
     * Resolves the student and activity a submission names, and checks it is welcome.
     *
     * @param repository $repository the activity's database access
     * @param array $params the validated call parameters
     * @return array{0:int,1:\stdClass} [user id, activity instance]
     */
    private static function validate_submission(repository $repository, array $params): array {
        self::validate_payload($params);

        $users = $repository->get_active_users_by_email($params['email']);
        if (!$users) {
            throw new validation_exception('validation_usernotfound', $params['email']);
        }
        if (count($users) > 1) {
            throw new validation_exception('validation_ambiguousemail', $params['email']);
        }
        $user = reset($users);

        $instance = $repository->get_instance_by_assignmentkey($params['assignmentkey']);
        if (!$instance) {
            throw new validation_exception('validation_assignmentnotfound', $params['assignmentkey']);
        }

        $coursecontext = context_course::instance($instance->course);
        if (!is_enrolled($coursecontext, $user->id, '', true)) {
            throw new validation_exception('validation_notenrolled');
        }

        $now = time();
        if ($instance->timeopen > 0 && $now < $instance->timeopen) {
            throw new validation_exception('validation_windownotopen', userdate($instance->timeopen));
        }
        if ($instance->timeclose > 0 && $now > $instance->timeclose) {
            throw new validation_exception('validation_windowclosed', userdate($instance->timeclose));
        }

        return [$user->id, $instance];
    }

    /**
     * Rejects values that are unusable on their own, before anything is looked up.
     *
     * @param array $params the validated call parameters
     */
    private static function validate_payload(array $params): void {
        if (!$params['results']) {
            throw new validation_exception('validation_noresults');
        }
        if (count($params['results']) > self::MAX_RESULTS) {
            throw new validation_exception('validation_toomanyresults', self::MAX_RESULTS);
        }
        if (trim($params['ide']) === '') {
            throw new validation_exception('validation_noide');
        }

        if (count($params['testfiles']) > self::MAX_FILES) {
            throw new validation_exception('validation_toomanyfiles', self::MAX_FILES);
        }

        foreach ($params['results'] as $result) {
            if (status::tryFrom(self::normalise_status($result['status'])) === null) {
                throw new validation_exception('validation_invalidstatus', $result['status']);
            }
            if ($result['durationms'] !== null && $result['durationms'] < 0) {
                throw new validation_exception('validation_invalidtiming');
            }
            self::validate_source($result['source'] ?? null);
        }

        foreach ($params['testfiles'] as $file) {
            if (trim($file['path']) === '') {
                throw new validation_exception('validation_nofilepath');
            }
        }

        foreach (['startedat', 'finishedat'] as $field) {
            if ($params[$field] !== null && $params[$field] < 0) {
                throw new validation_exception('validation_invalidtiming');
            }
        }
        if ($params['startedat'] !== null && $params['finishedat'] !== null
                && $params['finishedat'] < $params['startedat']) {
            throw new validation_exception('validation_invalidtiming');
        }
    }

    /**
     * Rejects a line range that cannot describe a real excerpt.
     *
     * @param array|null $source the source block of one result, absent when the IDE sent none
     */
    private static function validate_source(?array $source): void {
        if ($source === null) {
            return;
        }

        foreach (['startline', 'endline'] as $field) {
            if ($source[$field] !== null && $source[$field] < 0) {
                throw new validation_exception('validation_invalidsourcelines');
            }
        }

        if ($source['startline'] !== null && $source['endline'] !== null
                && $source['endline'] < $source['startline']) {
            throw new validation_exception('validation_invalidsourcelines');
        }
    }

    /**
     * Writes the run and its results.
     *
     * @param repository $repository the activity's database access
     * @param \stdClass $instance the activity the run belongs to
     * @param int $userid the student the run is attributed to
     * @param array $params the validated call parameters
     * @return \stdClass the stored run, carrying its new id
     */
    private static function store_run(repository $repository, \stdClass $instance, int $userid, array $params): \stdClass {
        $now = time();
        $counts = self::tally_statuses($params['results']);

        $run = new \stdClass();
        $run->idetestfeedbackid = $instance->id;
        $run->userid            = $userid;
        $run->ide               = self::clip($params['ide'], 'ide');
        $run->projectname       = self::clip($params['projectname'], 'projectname');
        $run->commithash        = self::clip($params['commithash'], 'commithash');
        $run->startedat         = $params['startedat'];
        $run->finishedat        = $params['finishedat'];
        $run->status            = self::resolve_run_status($counts)->value;
        $run->passedcount       = $counts[status::PASSED->value];
        $run->failedcount       = $counts[status::FAILED->value];
        $run->skippedcount      = $counts[status::SKIPPED->value];
        $run->errorcount        = $counts[status::ERROR->value];
        $run->timecreated       = $now;
        $run->capturedisabled     = (int) $params['capturedisabled'];
        $run->warningacknowledged = (int) $params['warningacknowledged'];

        $results = [];
        foreach ($params['results'] as $r) {
            $source = $r['source'] ?? null;
            [$code, $truncated] = self::clip_code(
                $source['code'] ?? null,
                !empty($source['truncated']),
                self::MAX_SOURCE_BYTES
            );

            $result                  = new \stdClass();
            $result->testsuite       = self::clip($r['testsuite'], 'testsuite');
            $result->testname        = self::clip($r['testname'], 'testname');
            $result->status          = self::normalise_status($r['status']);
            $result->durationms      = $r['durationms'];
            $result->message         = $r['message'];
            $result->timecreated     = $now;
            $result->sourcekind      = self::clip($source['kind'] ?? null, 'sourcekind');
            $result->sourcefilepath  = self::clip($source['filepath'] ?? null, 'sourcefilepath');
            $result->sourcestartline = $source['startline'] ?? null;
            $result->sourceendline   = $source['endline'] ?? null;
            $result->sourcecode      = $code;
            $result->sourcetruncated = $truncated;
            $result->sourcecodehash  = self::clip($source['normalizedcodehash'] ?? null, 'sourcecodehash');
            $results[] = $result;
        }

        $files = [];
        foreach ($params['testfiles'] as $f) {
            [$content, $truncated] = self::clip_code($f['content'], !empty($f['truncated']), self::MAX_FILE_BYTES);

            $file              = new \stdClass();
            $file->path        = self::clip($f['path'], 'filepath');
            $file->sha256      = self::clip($f['sha256'], 'filesha256');
            $file->content     = $content;
            $file->truncated   = $truncated;
            $file->timecreated = $now;
            $files[] = $file;
        }

        $run->id = $repository->insert_run_with_results($run, $results, $files);

        return $run;
    }

    /**
     * Re-evaluates the custom completion rule for the student who submitted.
     *
     * @param repository $repository the activity's database access
     * @param \stdClass $instance the activity the run belongs to
     * @param \stdClass $cm the activity's course module
     * @param int $userid the student the run is attributed to
     */
    private static function update_completion(repository $repository, \stdClass $instance, \stdClass $cm,
                                              int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/completionlib.php');

        if (empty($instance->completionpassrun)) {
            return;
        }

        $completion = new \completion_info($repository->get_course($cm->course));
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }

    /**
     * @param \stdClass $cm the activity's course module
     * @param \stdClass $run the stored run
     * @param int $userid the student the run is attributed to
     */
    private static function log_submission(\stdClass $cm, \stdClass $run, int $userid): void {
        test_run_submitted::create([
            'objectid'      => $run->id,
            'context'       => context_module::instance($cm->id),
            'relateduserid' => $userid,
            'other'         => ['status' => $run->status],
        ])->trigger();
    }

    /**
     * @param array $results the submitted test case results
     * @return array<string, int> result count per status, every status present
     */
    private static function tally_statuses(array $results): array {
        $counts = array_fill_keys(array_column(status::cases(), 'value'), 0);

        foreach ($results as $result) {
            $counts[self::normalise_status($result['status'])]++;
        }

        return $counts;
    }

    /**
     * The worst outcome any test in the run reported.
     *
     * @param array<string, int> $counts from {@see tally_statuses()}
     * @return status
     */
    private static function resolve_run_status(array $counts): status {
        if ($counts[status::ERROR->value] > 0) {
            return status::ERROR;
        }
        if ($counts[status::FAILED->value] > 0) {
            return status::FAILED;
        }
        if ($counts[status::PASSED->value] > 0) {
            return status::PASSED;
        }

        return status::SKIPPED;
    }

    /**
     * @param string $status a status as the IDE reported it
     * @return string the status in the form it is stored in
     */
    private static function normalise_status(string $status): string {
        return \core_text::strtoupper(trim($status));
    }

    /**
     * Trims a value and clips it to the width of the column it is stored in.
     *
     * @param string|null $value the submitted value
     * @param string $field the key into {@see MAX_LENGTHS}
     * @return string|null
     */
    private static function clip(?string $value, string $field): ?string {
        if ($value === null) {
            return null;
        }

        return \core_text::substr(trim($value), 0, self::MAX_LENGTHS[$field]);
    }

    /**
     * Clips captured code to its byte cap, keeping the submission rather than
     * rejecting it; the truncated flag already means "this is not the whole thing".
     *
     * @param string|null $code the submitted code
     * @param bool $truncated whether the IDE already reported it truncated
     * @param int $max the byte cap
     * @return array{0:?string,1:int} [the code to store, the truncated flag]
     */
    private static function clip_code(?string $code, bool $truncated, int $max): array {
        if ($code === null) {
            return [null, (int) $truncated];
        }

        if (\core_text::strlen($code) > $max) {
            return [\core_text::substr($code, 0, $max), 1];
        }

        return [$code, (int) $truncated];
    }
}
