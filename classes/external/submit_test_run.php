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
use mod_idetestfeedback\local\submission;
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

    /** @var int Most bytes of content kept per test file. */
    private const MAX_FILE_BYTES = 524288;

    /** @var array<string, int> Column widths from db/install.xml. */
    private const MAX_LENGTHS = [
        'ide'            => 50,
        'projectname'    => 255,
        'commithash'     => 100,
        'testsuite'      => 255,
        'testname'       => 255,
        'sourcefilepath' => 1024,
        'filepath'       => 1024,
    ];

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email'         => new external_value(PARAM_EMAIL, 'Email of the user, from the identity the middleware asserted'),
            'assignmentkey' => new external_value(PARAM_ALPHANUMEXT, 'Assignment key shown in the activity'),
            'ide'           => new external_value(PARAM_TEXT, 'IDE identifier, e.g. VSCODE'),
            'projectname'   => new external_value(PARAM_TEXT, 'Project name', VALUE_DEFAULT, null),
            'commithash'    => new external_value(PARAM_TEXT, 'Git commit hash', VALUE_DEFAULT, null),
            'startedat'     => new external_value(PARAM_INT, 'Run start (epoch milliseconds)', VALUE_DEFAULT, null),
            'finishedat'    => new external_value(PARAM_INT, 'Run end (epoch milliseconds)', VALUE_DEFAULT, null),
            'payload'       => new external_value(PARAM_RAW,
                'JSON object holding the run\'s "results" and "testfiles"; see payload_parameters()'),
            'capturedisabled' => new external_value(PARAM_BOOL, 'The student disabled code capture',
                VALUE_DEFAULT, 0),
            'warningacknowledged' => new external_value(PARAM_BOOL,
                'The student acknowledged the empty-test warning', VALUE_DEFAULT, 0),
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
                        'filepath'  => new external_value(PARAM_RAW, 'File the test lives in', VALUE_DEFAULT, null),
                        'startline' => new external_value(PARAM_INT, 'First line, 1-based inclusive',
                            VALUE_DEFAULT, null),
                        'endline'   => new external_value(PARAM_INT, 'Last line, 1-based inclusive',
                            VALUE_DEFAULT, null),
                    ], 'Where in the run\'s files the test case came from', VALUE_OPTIONAL),
                ]),
                'The test case results of the run, at least one'
            ),
            'testfiles'     => new external_multiple_structure(
                new external_single_structure([
                    'path'      => new external_value(PARAM_RAW, 'Repo-relative path'),
                    'content'   => new external_value(PARAM_RAW, 'File contents', VALUE_DEFAULT, null),
                    'truncated' => new external_value(PARAM_BOOL, 'The content was cut short', VALUE_DEFAULT, 0),
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
     * @param int|null $startedat when the run started, in epoch milliseconds
     * @param int|null $finishedat when the run finished, in epoch milliseconds
     * @param string $payload JSON holding the run's results and captured test files
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
        string $payload,
        bool $capturedisabled,
        bool $warningacknowledged
    ): array {
        global $DB;

        self::validate_context(context_system::instance());
        require_capability('mod/idetestfeedback:submit', context_system::instance());

        $params = self::validate_parameters(self::execute_parameters(), [
            'email'               => $email,
            'assignmentkey'       => $assignmentkey,
            'ide'                 => $ide,
            'projectname'         => $projectname,
            'commithash'          => $commithash,
            'startedat'           => $startedat,
            'finishedat'          => $finishedat,
            'payload'             => $payload,
            'capturedisabled'     => $capturedisabled,
            'warningacknowledged' => $warningacknowledged,
        ]);
        $submission = self::decode_submission($params);
        self::validate_payload($submission);

        $repository = new repository($DB);

        [$userid, $instance, $cm] = self::resolve_target($repository, $submission);

        $run = self::store_run($repository, $instance, $userid, $submission);

        self::update_completion($instance, $cm, $userid);
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
            startedat: $params['startedat'],
            finishedat: $params['finishedat'],
            results: $results,
            testfiles: $testfiles,
            capturedisabled: $params['capturedisabled'],
            warningacknowledged: $params['warningacknowledged'],
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
    private static function validate_payload(submission $submission): void {
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

        foreach ([$submission->startedat, $submission->finishedat] as $time) {
            if ($time !== null && $time < 0) {
                throw new validation_exception('validation_invalidtiming');
            }
        }
        if ($submission->startedat !== null && $submission->finishedat !== null
                && $submission->finishedat < $submission->startedat) {
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

        if (trim((string) $source['filepath']) === '') {
            throw new validation_exception('validation_nosourcefilepath');
        }

        if ($source['startline'] === null || $source['endline'] === null
                || $source['startline'] < 1 || $source['endline'] < $source['startline']) {
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

        if (!is_enrolled(context_course::instance($instance->course), $userid, '', true)) {
            throw new validation_exception('validation_notenrolled');
        }

        $cm = get_fast_modinfo($instance->course, $userid)->get_instances_of('idetestfeedback')[$instance->id] ?? null;
        if (!$cm || $cm->deletioninprogress || !$cm->uservisible) {
            throw new validation_exception('validation_activityunavailable');
        }

        $now = time();
        if ($instance->timeopen > 0 && $now < $instance->timeopen) {
            throw new validation_exception('validation_windownotopen', userdate($instance->timeopen));
        }
        if ($instance->timeclose > 0 && $now > $instance->timeclose) {
            throw new validation_exception('validation_windowclosed', userdate($instance->timeclose));
        }

        return [$userid, $instance, $cm];
    }

    /**
     * Writes the run, its results and its files.
     *
     * @param repository $repository the activity's database access
     * @param \stdClass $instance the activity the run belongs to
     * @param int $userid the student the run is attributed to
     * @param submission $submission
     * @return \stdClass the stored run, carrying its new id
     */
    private static function store_run(repository $repository, \stdClass $instance, int $userid,
                                      submission $submission): \stdClass {
        $now = time();
        $counts = self::tally_statuses($submission->results);

        $run = new \stdClass();
        $run->idetestfeedbackid   = $instance->id;
        $run->userid              = $userid;
        $run->ide                 = self::clip($submission->ide, 'ide');
        $run->projectname         = self::clip($submission->projectname, 'projectname');
        $run->commithash          = self::clip($submission->commithash, 'commithash');
        $run->startedat           = $submission->startedat;
        $run->finishedat          = $submission->finishedat;
        $run->status              = self::resolve_run_status($counts)->value;
        $run->passedcount         = $counts[status::PASSED->value];
        $run->failedcount         = $counts[status::FAILED->value];
        $run->skippedcount        = $counts[status::SKIPPED->value];
        $run->errorcount          = $counts[status::ERROR->value];
        $run->timecreated         = $now;
        $run->capturedisabled     = (int) $submission->capturedisabled;
        $run->warningacknowledged = (int) $submission->warningacknowledged;

        $results = [];
        foreach ($submission->results as $submitted) {
            $source = $submitted['source'] ?? [];
            $filepath = self::clip($source['filepath'] ?? null, 'sourcefilepath');

            $result                  = new \stdClass();
            $result->testsuite       = self::clip($submitted['testsuite'], 'testsuite');
            $result->testname        = self::clip($submitted['testname'], 'testname');
            $result->status          = $submitted['status'];
            $result->durationms      = $submitted['durationms'];
            $result->message         = $submitted['message'];
            $result->timecreated     = $now;
            $result->sourcefilepath  = $filepath === '' ? null : $filepath;
            $result->sourcestartline = $source['startline'] ?? null;
            $result->sourceendline   = $source['endline'] ?? null;
            $results[] = $result;
        }

        $files = [];
        foreach (self::unique_files($submission->testfiles) as $path => $submitted) {
            [$content, $truncated] = self::clip_code($submitted['content'], !empty($submitted['truncated']));

            $file              = new \stdClass();
            $file->path        = (string) $path;
            $file->content     = $content;
            $file->truncated   = $truncated;
            $file->timecreated = $now;
            $files[] = $file;
        }

        $run->id = $repository->insert_run_with_results($run, $results, $files);

        return $run;
    }

    /**
     * The run's test files, one per path and in path order; the first body for a path wins.
     *
     * @param array[] $testfiles the submitted test files
     * @return array[] the files, keyed by path
     */
    private static function unique_files(array $testfiles): array {
        $files = [];

        foreach ($testfiles as $file) {
            $path = self::clip($file['path'], 'filepath');
            if (!isset($files[$path])) {
                $files[$path] = $file;
            }
        }

        ksort($files);

        return $files;
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

        require_once($CFG->dirroot . '/lib/completionlib.php');

        $completion = new \completion_info($cm->get_course());
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }

    /**
     * @param \cm_info $cm the activity's course module
     * @param \stdClass $run the stored run
     * @param int $userid the student the run is attributed to
     */
    private static function log_submission(\cm_info $cm, \stdClass $run, int $userid): void {
        test_run_submitted::create([
            'objectid'      => $run->id,
            'context'       => context_module::instance($cm->id),
            'userid'        => $userid,
            'relateduserid' => $userid,
            'other'         => ['status' => $run->status],
        ])->trigger();
    }

    /**
     * @param array[] $results the submitted test case results
     * @return array<string, int> result count per status, every status present
     */
    private static function tally_statuses(array $results): array {
        $counts = array_fill_keys(array_column(status::cases(), 'value'), 0);

        foreach ($results as $result) {
            $counts[$result['status']]++;
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
     * Clips captured code to MAX_FILE_BYTES on a character boundary, and on a
     * line boundary where there is one so line ranges still line up.
     *
     * @param string|null $code the submitted code
     * @param bool $truncated whether the IDE already reported it truncated
     * @return array{0:?string,1:int} [the code to store, the truncated flag]
     */
    private static function clip_code(?string $code, bool $truncated): array {
        if ($code === null || strlen($code) <= self::MAX_FILE_BYTES) {
            return [$code, (int) $truncated];
        }

        $kept = mb_strcut($code, 0, self::MAX_FILE_BYTES, 'UTF-8');
        $break = strrpos($kept, "\n");

        return [$break ? substr($kept, 0, $break) : $kept, 1];
    }
}
