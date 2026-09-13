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

use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\status;
use mod_idetestfeedback\local\validation_exception;

/**
 * Tests for the web service the IDE plugin posts a finished test run to.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\external\submit_test_run
 */
final class submit_test_run_test extends \advanced_testcase {

    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $instance;

    /** @var \stdClass */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student', [
            'email' => 'student@example.com',
        ]);
    }

    /**
     * @param array $overrides parameters to override the happy-path defaults with
     * @return array the parameters {@see submit_test_run::execute()} expects
     */
    private function params(array $overrides = []): array {
        return array_merge([
            'email'         => $this->student->email,
            'assignmentkey' => $this->instance->assignmentkey,
            'ide'           => 'VSCODE',
            'projectname'   => 'myproject',
            'commithash'    => 'abc123',
            'startedat'     => 1000,
            'finishedat'    => 2000,
            'results'       => [
                ['testname' => 'testAdd', 'status' => 'PASSED', 'testsuite' => null,
                    'durationms' => 5, 'message' => null, 'stacktracehash' => null],
            ],
        ], $overrides);
    }

    /**
     * @param array $params from {@see params()}
     * @return array the return value of execute()
     */
    private function submit(array $params): array {
        return submit_test_run::execute(...$params);
    }

    public function test_a_valid_submission_stores_the_run_and_its_results(): void {
        global $DB;

        $result = $this->submit($this->params());

        $repository = new repository($DB);
        $run = $repository->get_run($result['runid'], $this->instance->id);
        $this->assertNotNull($run);
        $this->assertSame((int) $this->student->id, (int) $run->userid);
        $this->assertSame('VSCODE', $run->ide);
        $this->assertSame('myproject', $run->projectname);
        $this->assertSame('abc123', $run->commithash);
        $this->assertSame(1000, (int) $run->startedat);
        $this->assertSame(2000, (int) $run->finishedat);
        $this->assertSame(status::PASSED->value, $run->status);
        $this->assertSame(1, (int) $run->passedcount);

        $results = array_values($repository->get_results($run->id));
        $this->assertCount(1, $results);
        $this->assertSame('testAdd', $results[0]->testname);
        $this->assertSame(5, (int) $results[0]->durationms);
    }

    /**
     * @return array[] [result statuses submitted, expected overall run status]
     */
    public static function run_status_provider(): array {
        return [
            'all passed is passed' => [['PASSED', 'PASSED'], status::PASSED],
            'a failure outweighs a pass' => [['PASSED', 'FAILED'], status::FAILED],
            'an error outweighs a failure' => [['FAILED', 'ERROR'], status::ERROR],
            'all skipped is skipped' => [['SKIPPED', 'SKIPPED'], status::SKIPPED],
            'skips do not hide a pass' => [['SKIPPED', 'PASSED'], status::PASSED],
        ];
    }

    /**
     * @dataProvider run_status_provider
     * @param string[] $statuses the statuses to submit, one result each
     * @param status $expected the overall run status that should be stored
     */
    public function test_run_status_is_the_worst_outcome_reported(array $statuses, status $expected): void {
        global $DB;

        $results = array_map(fn($s) => [
            'testname' => 'test_' . $s, 'status' => $s, 'testsuite' => null,
            'durationms' => null, 'message' => null, 'stacktracehash' => null,
        ], $statuses);

        $returned = $this->submit($this->params(['results' => $results]));

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertSame($expected->value, $run->status);
    }

    public function test_result_status_is_normalised_to_uppercase(): void {
        global $DB;

        // The 'status' parameter is PARAM_ALPHA, which already rejects whitespace;
        // only the case is left for the server to normalise.
        $returned = $this->submit($this->params(['results' => [
            ['testname' => 'testAdd', 'status' => 'passed', 'testsuite' => null,
                'durationms' => null, 'message' => null, 'stacktracehash' => null],
        ]]));

        $results = array_values((new repository($DB))->get_results($returned['runid']));
        $this->assertSame(status::PASSED->value, $results[0]->status);
    }

    public function test_string_fields_are_trimmed_and_clipped_to_column_width(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'ide' => '  ' . str_repeat('i', 60) . '  ',
            'commithash' => '  ' . str_repeat('h', 150) . '  ',
            'results' => [[
                'testname' => '  ' . str_repeat('n', 300) . '  ',
                'status' => 'PASSED',
                'testsuite' => '  ' . str_repeat('s', 300) . '  ',
                'durationms' => null,
                'message' => null,
                'stacktracehash' => str_repeat('a', 100),
            ]],
        ]));

        $repository = new repository($DB);
        $run = $repository->get_run($returned['runid'], $this->instance->id);
        $this->assertSame(str_repeat('i', 50), $run->ide);
        $this->assertSame(str_repeat('h', 100), $run->commithash);

        $results = array_values($repository->get_results($returned['runid']));
        $this->assertSame(str_repeat('n', 255), $results[0]->testname);
        $this->assertSame(str_repeat('s', 255), $results[0]->testsuite);
        $this->assertSame(str_repeat('a', 64), $results[0]->stacktracehash);
    }

    public function test_null_optional_fields_are_stored_as_null(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'projectname' => null,
            'commithash' => null,
            'startedat' => null,
            'finishedat' => null,
        ]));

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertNull($run->projectname);
        $this->assertNull($run->commithash);
        $this->assertNull($run->startedat);
        $this->assertNull($run->finishedat);
    }

    public function test_it_triggers_a_test_run_submitted_event(): void {
        $sink = $this->redirectEvents();

        $returned = $this->submit($this->params());

        $events = $sink->get_events();
        $matching = array_values(array_filter($events, fn($e) => $e instanceof \mod_idetestfeedback\event\test_run_submitted));
        $this->assertCount(1, $matching);
        $event = $matching[0];
        $this->assertSame($returned['runid'], (int) $event->objectid);
        $this->assertSame((int) $this->student->id, (int) $event->relateduserid);
        $this->assertSame(status::PASSED->value, $event->other['status']);
    }

    public function test_it_rejects_an_empty_results_array(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_noresults', 'mod_idetestfeedback'));

        $this->submit($this->params(['results' => []]));
    }

    public function test_it_rejects_more_than_the_maximum_number_of_results(): void {
        $results = array_fill(0, 2001, [
            'testname' => 'test', 'status' => 'PASSED', 'testsuite' => null,
            'durationms' => null, 'message' => null, 'stacktracehash' => null,
        ]);

        $this->expectException(validation_exception::class);
        $this->submit($this->params(['results' => $results]));
    }

    public function test_it_rejects_a_blank_ide(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_noide', 'mod_idetestfeedback'));

        $this->submit($this->params(['ide' => '   ']));
    }

    public function test_it_rejects_an_invalid_result_status(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['results' => [
            ['testname' => 'testAdd', 'status' => 'BOGUS', 'testsuite' => null,
                'durationms' => null, 'message' => null, 'stacktracehash' => null],
        ]]));
    }

    public function test_it_rejects_a_negative_duration(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['results' => [[
            'testname' => 'testAdd', 'status' => 'PASSED', 'testsuite' => null,
            'durationms' => -1, 'message' => null, 'stacktracehash' => null,
        ]]]));
    }

    public function test_it_rejects_a_negative_startedat(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['startedat' => -1]));
    }

    public function test_it_rejects_a_negative_finishedat(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['finishedat' => -1]));
    }

    public function test_it_rejects_a_run_finishing_before_it_started(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_invalidtiming', 'mod_idetestfeedback'));

        $this->submit($this->params(['startedat' => 2000, 'finishedat' => 1000]));
    }

    public function test_it_rejects_an_unknown_email(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_usernotfound', 'mod_idetestfeedback', 'nobody@example.com'));

        $this->submit($this->params(['email' => 'nobody@example.com']));
    }

    public function test_it_rejects_an_ambiguous_email(): void {
        $this->getDataGenerator()->create_user(['email' => 'student@example.com']);

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(
            get_string('validation_ambiguousemail', 'mod_idetestfeedback', 'student@example.com')
        );

        $this->submit($this->params());
    }

    public function test_it_rejects_an_unknown_assignment_key(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(
            get_string('validation_assignmentnotfound', 'mod_idetestfeedback', 'nosuchkey')
        );

        $this->submit($this->params(['assignmentkey' => 'nosuchkey']));
    }

    public function test_it_rejects_a_user_who_is_not_enrolled(): void {
        $stranger = $this->getDataGenerator()->create_user(['email' => 'stranger@example.com']);

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_notenrolled', 'mod_idetestfeedback'));

        $this->submit($this->params(['email' => $stranger->email]));
    }

    public function test_it_rejects_a_submission_before_the_window_opens(): void {
        global $DB;
        $DB->set_field('idetestfeedback', 'timeopen', time() + DAYSECS, ['id' => $this->instance->id]);

        $this->expectException(validation_exception::class);

        $this->submit($this->params());
    }

    public function test_it_rejects_a_submission_after_the_window_closes(): void {
        global $DB;
        $DB->set_field('idetestfeedback', 'timeclose', time() - DAYSECS, ['id' => $this->instance->id]);

        $this->expectException(validation_exception::class);

        $this->submit($this->params());
    }

    public function test_it_accepts_a_submission_within_an_open_window(): void {
        global $DB;
        $DB->set_field('idetestfeedback', 'timeopen', time() - DAYSECS, ['id' => $this->instance->id]);
        $DB->set_field('idetestfeedback', 'timeclose', time() + DAYSECS, ['id' => $this->instance->id]);

        $result = $this->submit($this->params());

        $this->assertIsInt($result['runid']);
    }

    public function test_it_updates_completion_when_the_rule_is_enabled_and_the_run_passes(): void {
        $completioncourse = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $completioninstance = $this->getDataGenerator()->create_module('idetestfeedback', [
            'course' => $completioncourse->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpassrun' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($completioncourse, 'student', [
            'email' => 'completer@example.com',
        ]);
        $cm = get_coursemodule_from_instance('idetestfeedback', $completioninstance->id);

        $this->submit($this->params([
            'email' => $student->email,
            'assignmentkey' => $completioninstance->assignmentkey,
        ]));

        $completion = new \completion_info($completioncourse);
        $data = $completion->get_data($cm, false, $student->id);
        $this->assertSame(COMPLETION_COMPLETE, (int) $data->completionstate);
    }
}
