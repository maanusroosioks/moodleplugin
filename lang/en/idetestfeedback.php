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

/**
 * English strings for mod_idetestfeedback.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityname'] = 'Activity name';
$string['allstatuses'] = 'All statuses';
$string['allstudents'] = 'All students';
$string['assignmentkey'] = 'Assignment key';
$string['assignmentkey_generated'] = 'A unique key will be generated automatically when you save.';
$string['assignmentkey_help'] = 'Copy this key into your IDE plugin settings to link submissions to this activity.';
$string['back'] = 'Back';
$string['capturedisabled'] = 'Code capture disabled';
$string['closebeforeopen'] = 'The closing date must be after the opening date.';
$string['commithash'] = 'Commit hash';
$string['completionpassrun'] = 'Student must submit a passing test run';
$string['completionpassrun_desc'] = 'Submit a test run in which every test passed. Failed, errored and skipped tests all leave the activity incomplete.';
$string['duration'] = 'Duration';
$string['durationunit'] = '{$a} ms';
$string['event_test_run_submitted'] = 'Test run submitted';
$string['failed'] = 'Failed';
$string['failingsincefeedback'] = 'Failing since feedback';
$string['feedback'] = 'Teacher feedback';
$string['feedbackfor'] = 'Feedback on {$a}';
$string['feedbackmsgintro'] = 'Your teacher left feedback on your test run:';
$string['feedbackmsgsmall'] = 'Your teacher left feedback on your IDE test results.';
$string['feedbackmsgsubject'] = 'New feedback on your test results in {$a}';
$string['feedbacksaved'] = 'Feedback saved.';
$string['feedbacksavednotified'] = 'Feedback saved. The student has been notified.';
$string['filehash'] = 'File hash (SHA-256)';
$string['filenotfound'] = 'Test file not found.';
$string['fileopen'] = 'Open it on its own page.';
$string['filetoolarge'] = 'This file is too large to show with the run.';
$string['files'] = 'Test files';
$string['finishedat'] = 'Finished';
$string['fixedsincefeedback'] = 'Fixed since feedback';
$string['ide'] = 'IDE';
$string['idetestfeedback:addinstance'] = 'Add a new IDE Test Feedback activity';
$string['idetestfeedback:comment'] = 'Add feedback to students\' test results';
$string['idetestfeedback:submit'] = 'Submit IDE test results via API';
$string['idetestfeedback:view'] = 'View own IDE test results';
$string['idetestfeedback:viewall'] = 'View all students\' IDE test results';
$string['message'] = 'Failure message';
$string['messageprovider:feedback'] = 'Feedback on your IDE test results';
$string['modulename'] = 'IDE Test Feedback';
$string['modulename_help'] = 'The IDE Test Feedback activity receives unit test results submitted from a student\'s IDE and displays them per assignment.';
$string['modulenameplural'] = 'IDE Test Feedback activities';
$string['myresults'] = 'My test results';
$string['nomatchingruns'] = 'No test runs match the selected filters.';
$string['noresults'] = 'No test results found.';
$string['notifystudent'] = 'Notify student by message';
$string['passed'] = 'Passed';
$string['pluginadministration'] = 'IDE Test Feedback administration';
$string['pluginname'] = 'IDE Test Feedback';
$string['privacy:metadata:blob'] = 'Stores the file contents captured with runs. A body is kept once per activity however many runs captured it, so contents two students both submitted are only removed once neither student\'s runs refer to them.';
$string['privacy:metadata:blob:content'] = 'The contents of the test file at the time of submission, with line endings and trailing whitespace normalised.';
$string['privacy:metadata:blob:contenthash'] = 'The SHA-256 hash of the stored contents.';
$string['privacy:metadata:blob:timecreated'] = 'When these contents were first recorded.';
$string['privacy:metadata:file'] = 'Stores the test files captured with a run.';
$string['privacy:metadata:file:blobid'] = 'The stored contents this run captured at this path.';
$string['privacy:metadata:file:path'] = 'The path of the test file within the student\'s project.';
$string['privacy:metadata:file:timecreated'] = 'When the test file was recorded.';
$string['privacy:metadata:file:truncated'] = 'Whether the stored file contents were cut short.';
$string['privacy:metadata:messages'] = 'Notifications are sent to students when a teacher leaves feedback on one of their test runs.';
$string['privacy:metadata:result'] = 'Stores individual test case results within a run.';
$string['privacy:metadata:result:durationms'] = 'How long the test case took to run, in milliseconds.';
$string['privacy:metadata:result:feedback'] = 'Feedback a teacher wrote on the test case result.';
$string['privacy:metadata:result:feedbackby'] = 'The teacher who wrote the feedback.';
$string['privacy:metadata:result:feedbackformat'] = 'The text format the feedback is stored in.';
$string['privacy:metadata:result:feedbackmodified'] = 'When the feedback was last edited.';
$string['privacy:metadata:result:message'] = 'The failure or error message.';
$string['privacy:metadata:result:sourceendline'] = 'The last line of the test case in its file.';
$string['privacy:metadata:result:sourcefilepath'] = 'The path of the file the test case is defined in.';
$string['privacy:metadata:result:sourcestartline'] = 'The first line of the test case in its file.';
$string['privacy:metadata:result:status'] = 'The test case result status.';
$string['privacy:metadata:result:testname'] = 'The name of the test case.';
$string['privacy:metadata:result:testsuite'] = 'The test suite the test case belongs to.';
$string['privacy:metadata:result:timecreated'] = 'When the test case result was recorded.';
$string['privacy:metadata:run'] = 'Stores IDE test run submissions.';
$string['privacy:metadata:run:capturedisabled'] = 'Whether the student turned off sending source code with the run.';
$string['privacy:metadata:run:commithash'] = 'The git commit hash at the time of submission.';
$string['privacy:metadata:run:errorcount'] = 'Number of test cases that errored in the run.';
$string['privacy:metadata:run:failedcount'] = 'Number of test cases that failed in the run.';
$string['privacy:metadata:run:finishedat'] = 'When the test run finished.';
$string['privacy:metadata:run:ide'] = 'The IDE used to run the tests.';
$string['privacy:metadata:run:passedcount'] = 'Number of test cases that passed in the run.';
$string['privacy:metadata:run:projectname'] = 'The project name at the time of submission.';
$string['privacy:metadata:run:skippedcount'] = 'Number of test cases that were skipped in the run.';
$string['privacy:metadata:run:startedat'] = 'When the test run started.';
$string['privacy:metadata:run:status'] = 'The overall run status (PASSED, FAILED, ERROR, SKIPPED).';
$string['privacy:metadata:run:timecreated'] = 'When the run was submitted.';
$string['privacy:metadata:run:userid'] = 'The user who submitted the test run.';
$string['privacy:metadata:run:warningacknowledged'] = 'Whether the student was warned that some tests are empty and submitted anyway.';
$string['privacy:path:feedbackgiven'] = 'Feedback given to other students';
$string['privacy:path:runs'] = 'Test runs';
$string['projectname'] = 'Project';
$string['resetruns'] = 'Delete all submitted test runs';
$string['rundetail'] = 'Test run details';
$string['runduration'] = 'Run duration';
$string['runflags'] = 'Flags';
$string['runnotfound'] = 'Test run not found.';
$string['savefeedback'] = 'Save feedback';
$string['skipped'] = 'Skipped';
$string['sourcecode'] = 'Source code';
$string['sourcenotcaptured'] = 'No source code was captured.';
$string['sourcenotfound'] = 'Not found in the project source';
$string['sourcewholefile'] = 'The test could not be picked out of this file. The whole file is below.';
$string['startedat'] = 'Started';
$string['status'] = 'Status';
$string['stillfailingsincefeedback'] = 'Still failing since feedback';
$string['student'] = 'Student';
$string['submissionclosed'] = 'Submissions closed on {$a}.';
$string['submissionnotopen'] = 'Submissions open on {$a}.';
$string['submissionwindow'] = 'Submission window';
$string['summarytext'] = 'You have {$a->total} run(s) for this assignment. Pass rate: {$a->rate}%.';
$string['testname'] = 'Test name';
$string['testresults'] = 'Individual test results';
$string['testsuite'] = 'Test suite';
$string['timeclose'] = 'Close on';
$string['timecreated'] = 'Date';
$string['timeopen'] = 'Open from';
$string['totalruns'] = 'Total: {$a} run(s)';
$string['truncated'] = 'Truncated';
$string['upgradefromprerelease'] = 'This site has a pre-release build of IDE Test Feedback, which cannot be upgraded. Uninstall the plugin and install it again.';
$string['validation_activityunavailable'] = 'The activity is not available to the user.';
$string['validation_ambiguousemail'] = 'More than one active user was found with the email "{$a}".';
$string['validation_assignmentnotfound'] = 'No activity was found for the assignment key "{$a}".';
$string['validation_invalidpayload'] = 'The submitted payload is not a valid JSON object.';
$string['validation_invalidsourcelines'] = 'The submitted source line numbers are not valid. A located test needs a line range that starts at line 1 or later and does not end before it starts.';
$string['validation_invalidstatus'] = 'The result status "{$a}" is not valid. Expected one of: PASSED, FAILED, SKIPPED, ERROR.';
$string['validation_invalidtiming'] = 'The submitted times are not valid. Durations cannot be negative and a run cannot finish before it starts.';
$string['validation_noemail'] = 'No email was submitted with this test run.';
$string['validation_nofilepath'] = 'A submitted test file has no path.';
$string['validation_noide'] = 'No IDE identifier was submitted with this test run.';
$string['validation_noresults'] = 'No results were submitted with this test run.';
$string['validation_nosourcefilepath'] = 'A submitted result names a line range but no file to read it from.';
$string['validation_notenrolled'] = 'The user is not enrolled in the course for this activity.';
$string['validation_notestname'] = 'A submitted result has no test name.';
$string['validation_toomanyfiles'] = 'Too many test files were submitted with this test run. At most {$a} are accepted.';
$string['validation_toomanyresults'] = 'Too many results were submitted with this test run. At most {$a} are accepted.';
$string['validation_usernotfound'] = 'No active user was found with the email "{$a}".';
$string['validation_windowclosed'] = 'The submission window for this activity closed on {$a}.';
$string['validation_windownotopen'] = 'The submission window for this activity opens on {$a}.';
$string['viewdetail'] = 'View';
$string['viewresults'] = 'All test results';
$string['warningacknowledged'] = 'Empty-test warning acknowledged';
