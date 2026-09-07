<?php
// This file is part of Moodle - http://moodle.org/

$string['pluginname']            = 'IDE Test Feedback';
$string['modulename']            = 'IDE Test Feedback';
$string['modulenameplural']      = 'IDE Test Feedback activities';
$string['modulename_help']       = 'The IDE Test Feedback activity receives unit test results submitted from a student\'s IDE and displays them per assignment.';
$string['activityname']          = 'Activity name';
$string['pluginadministration']  = 'IDE Test Feedback administration';

// Assignment key
$string['assignmentkey']           = 'Assignment key';
$string['assignmentkey_help']      = 'Copy this key into your IDE plugin settings to link submissions to this activity.';
$string['assignmentkey_generated'] = 'A unique key will be generated automatically when you save.';

// Defined test cases
$string['requiredtestsheading'] = 'Defined test cases';
$string['requiredtests']        = 'Test cases that count';
$string['requiredtests_help']   = 'Optionally list the test cases that count for this activity, one per line, as either `testName` or `testSuite#testName`.

When set, each run is scored against this list: defined tests that are missing, failing or skipped in a run are shown on the run details, and the "passing test run" completion rule requires a run that passes every defined test.

Leave this empty to accept every test the IDE reports (a run then completes the activity whenever its overall status is PASSED).

Matching ignores case; enter names as they appear on the run details page. Results are still self-reported from the student\'s environment and are not independently verified.';
$string['requiredprogress']    = 'Defined tests passed';
$string['requiredoutstanding'] = 'Defined tests outstanding';
$string['requiredfailedn']     = '{$a} failing';
$string['requiredskippedn']    = '{$a} skipped';
$string['requiredmissingn']    = '{$a} not reported';

// Submission window
$string['submissionwindow']  = 'Submission window';
$string['timeopen']          = 'Open from';
$string['timeclose']         = 'Close on';
$string['submissionnotopen'] = 'Submissions open on {$a}.';
$string['submissionclosed']  = 'Submissions closed on {$a}.';
$string['error_closebeforeopen'] = 'The closing date must be after the opening date.';

// Validation error messages
$string['idetestfeedback_validation_usernotfound']       = 'No active user was found with the email "{$a}".';
$string['idetestfeedback_validation_assignmentnotfound'] = 'No activity was found for the assignment key "{$a}".';
$string['idetestfeedback_validation_notenrolled']        = 'The user is not enrolled in the course for this activity.';
$string['idetestfeedback_validation_windownotopen']      = 'The submission window for this activity opens on {$a}.';
$string['idetestfeedback_validation_windowclosed']       = 'The submission window for this activity closed on {$a}.';
$string['idetestfeedback_validation_invalidstatus']      = 'The result status "{$a}" is not valid. Expected one of: PASSED, FAILED, SKIPPED, ERROR.';
$string['idetestfeedback_validation_noresults']          = 'No results were submitted with this test run.';

// Headings
$string['viewresults'] = 'All test results';
$string['myresults']   = 'My test results';
$string['rundetail']   = 'Test run details';
$string['testresults'] = 'Individual test results';
$string['back']        = 'Back';

// Table columns
$string['student']     = 'Student';
$string['ide']         = 'IDE';
$string['projectname'] = 'Project';
$string['commithash']  = 'Commit hash';
$string['status']      = 'Status';
$string['passed']      = 'Passed';
$string['failed']      = 'Failed';
$string['skipped']     = 'Skipped';
$string['timecreated'] = 'Date';
$string['testsuite']   = 'Test suite';
$string['testname']    = 'Test name';
$string['required']    = 'Required';
$string['duration']    = 'Duration';
$string['message']     = 'Failure message';
$string['viewdetail']  = 'View';

// Filters
$string['allstudents']   = 'All students';
$string['allstatuses']   = 'All statuses';

// Dynamic messages
$string['noresults']      = 'No test results found.';
$string['nomatchingruns'] = 'No test runs match the selected filters.';
$string['runnotfound']    = 'Test run not found.';
$string['totalruns']      = 'Total: {$a} run(s)';
$string['summarytext'] = 'You have {$a->total} run(s) for this assignment. Pass rate: {$a->rate}%.';

// Events
$string['event_test_run_submitted'] = 'Test run submitted';

// Completion
$string['completionpassrun']      = 'Student must submit a passing test run';
$string['completionpassrun_desc'] = 'Submit a test run with no failed or errored tests. When test cases are defined for the activity, the run must pass every defined test.';

// Capabilities
$string['idetestfeedback:view']          = 'View own IDE test results';
$string['idetestfeedback:viewall']       = 'View all students\' IDE test results';
$string['idetestfeedback:submit']        = 'Submit IDE test results via API';
$string['idetestfeedback:addinstance']   = 'Add a new IDE Test Feedback activity';

// Privacy
$string['privacy:metadata:run']              = 'Stores IDE test run submissions.';
$string['privacy:metadata:run:userid']       = 'The user who submitted the test run.';
$string['privacy:metadata:run:ide']          = 'The IDE used to run the tests.';
$string['privacy:metadata:run:projectname']  = 'The project name at the time of submission.';
$string['privacy:metadata:run:commithash']   = 'The git commit hash at the time of submission.';
$string['privacy:metadata:run:startedat']    = 'When the test run started.';
$string['privacy:metadata:run:finishedat']   = 'When the test run finished.';
$string['privacy:metadata:run:status']       = 'The overall run status (PASSED, FAILED, ERROR, SKIPPED).';
$string['privacy:metadata:run:passedcount']  = 'Number of test cases that passed in the run.';
$string['privacy:metadata:run:failedcount']  = 'Number of test cases that failed in the run.';
$string['privacy:metadata:run:skippedcount'] = 'Number of test cases that were skipped in the run.';
$string['privacy:metadata:run:errorcount']   = 'Number of test cases that errored in the run.';
$string['privacy:metadata:run:timecreated']  = 'When the run was submitted.';
$string['privacy:metadata:result']           = 'Stores individual test case results within a run.';
$string['privacy:metadata:result:testsuite']      = 'The test suite the test case belongs to.';
$string['privacy:metadata:result:testname']       = 'The name of the test case.';
$string['privacy:metadata:result:status']         = 'The test case result status.';
$string['privacy:metadata:result:durationms']     = 'How long the test case took to run, in milliseconds.';
$string['privacy:metadata:result:message']        = 'The failure or error message.';
$string['privacy:metadata:result:stacktracehash'] = 'A hash of the failure stack trace, used to group similar failures.';
$string['privacy:metadata:result:timecreated']    = 'When the test case result was recorded.';
