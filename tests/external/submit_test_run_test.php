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
use mod_idetestfeedback\local\source_code;
use mod_idetestfeedback\local\status;
use mod_idetestfeedback\local\validation_exception;

/**
 * Tests for the web service the IDE plugin posts a finished test run to.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\external\submit_test_run::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\run_recorder::class)]
final class submit_test_run_test extends \advanced_testcase {
    /** @var string The declaration the fixture's line range points at. */
    private const DECLARATION = "def test_add_returns_sum():\n    assert add(2, 3) == 5";

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
     * Builds valid submission parameters.
     *
     * @param array $overrides parameters to override the happy-path defaults with
     * @return array the parameters {@see submit()} sends
     */
    private function params(array $overrides = []): array {
        return array_merge([
            'email'         => $this->student->email,
            'assignmentkey' => $this->instance->assignmentkey,
            'ide'           => 'VSCODE',
            'projectname'   => 'myproject',
            'commithash'    => 'abc123',
            'repourl'       => 'https://github.com/ada/calc.git',
            'startedatms'   => 1758134400000,
            'finishedatms'  => 1758134403120,
            'results'       => [
                ['testname' => 'testAdd', 'status' => 'PASSED', 'testsuite' => null,
                    'durationms' => 5, 'message' => null],
            ],
            'capturedisabled' => false,
        ], $overrides);
    }

    /**
     * Builds a result's source block.
     *
     * @param array $overrides fields to override the defaults with
     * @return array one 'source' block, as the IDE sends it
     */
    private function source(array $overrides = []): array {
        return array_merge([
            'path'      => 'tests/test_calculator.py',
            'startline' => 1,
            'endline'   => 2,
        ], $overrides);
    }

    /**
     * Builds the test file the default source block points at.
     *
     * @return array one testfile holding the declaration the fixture points at
     */
    private function sourcefile(): array {
        return [[
            'path'      => 'tests/test_calculator.py',
            'content'   => self::DECLARATION,
            'truncated' => false,
        ]];
    }

    /**
     * Calls the web service with the given parameters.
     *
     * @param array $params from {@see params()}
     * @return array the return value of execute()
     */
    private function submit(array $params): array {
        $payload = [
            'results'   => $params['results'] ?? [],
            'testfiles' => $params['testfiles'] ?? [],
        ];
        unset($params['results'], $params['testfiles']);
        $params['payload'] = json_encode($payload);

        return submit_test_run::execute(...$params);
    }

    /**
     * Calls the web service with a raw payload string.
     *
     * @param array $params from {@see params()}
     * @param string $payload the raw payload string to send in place of the encoded lists
     * @return array the return value of execute()
     */
    private function submit_payload(array $params, string $payload): array {
        unset($params['results'], $params['testfiles']);
        $params['payload'] = $payload;

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
        $this->assertSame('https://github.com/ada/calc.git', $run->repourl);
        $this->assertSame(1758134400000, (int) $run->startedatms);
        $this->assertSame(1758134403120, (int) $run->finishedatms);
        $this->assertSame(status::PASSED->value, $run->status);
        $this->assertSame(1, (int) $run->passedcount);

        $results = array_values($repository->get_results($run->id));
        $this->assertCount(1, $results);
        $this->assertSame('testAdd', $results[0]->testname);
        $this->assertSame(5, (int) $results[0]->durationms);
    }

    /**
     * Data provider for test_run_status_is_the_worst_outcome_reported().
     *
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
     * The run status is the worst status any result reported.
     *
     * @param string[] $statuses the statuses to submit, one result each
     * @param status $expected the overall run status that should be stored
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('run_status_provider')]
    public function test_run_status_is_the_worst_outcome_reported(array $statuses, status $expected): void {
        global $DB;

        $results = array_map(fn($s) => [
            'testname' => 'test_' . $s, 'status' => $s, 'testsuite' => null,
            'durationms' => null, 'message' => null,
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
                'durationms' => null, 'message' => null],
        ]]));

        $results = array_values((new repository($DB))->get_results($returned['runid']));
        $this->assertSame(status::PASSED->value, $results[0]->status);
    }

    public function test_string_fields_are_trimmed_and_clipped_to_column_width(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'ide' => str_repeat('i', 60),
            'commithash' => str_repeat('h', 150),
            'repourl' => '  https://example.com/' . str_repeat('r', 1000) . '  ',
            'results' => [[
                'testname' => '  ' . str_repeat('n', 1100) . '  ',
                'status' => 'PASSED',
                'testsuite' => '  ' . str_repeat('s', 300) . '  ',
                'durationms' => null,
                'message' => null,
                'source' => $this->source(['path' => str_repeat('p', 1100)]),
            ]],
        ]));

        $repository = new repository($DB);
        $run = $repository->get_run($returned['runid'], $this->instance->id);
        $this->assertSame(str_repeat('i', 50), $run->ide);
        $this->assertSame(str_repeat('h', 100), $run->commithash);
        $this->assertSame('https://example.com/' . str_repeat('r', 1000), $run->repourl);

        $results = array_values($repository->get_results($returned['runid']));
        $this->assertSame(str_repeat('n', 1024), $results[0]->testname);
        $this->assertSame(str_repeat('s', 255), $results[0]->testsuite);
        $this->assertSame(str_repeat('p', 1024), $results[0]->sourcefilepath);
    }

    public function test_null_optional_fields_are_stored_as_null(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'projectname' => null,
            'commithash' => null,
            'repourl' => null,
            'startedatms' => null,
            'finishedatms' => null,
        ]));

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertNull($run->projectname);
        $this->assertNull($run->commithash);
        $this->assertNull($run->repourl);
        $this->assertNull($run->startedatms);
        $this->assertNull($run->finishedatms);
    }

    public function test_blank_optional_fields_are_stored_as_null(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'projectname' => '   ',
            'commithash' => '',
            'repourl' => '  ',
            'results' => [[
                'testname' => 'testAdd',
                'status' => 'PASSED',
                'testsuite' => '  ',
                'durationms' => null,
                'message' => null,
                'source' => ['path' => ' ', 'startline' => null, 'endline' => null],
            ]],
        ]));

        $repository = new repository($DB);
        $run = $repository->get_run($returned['runid'], $this->instance->id);
        $this->assertNull($run->projectname);
        $this->assertNull($run->commithash);
        $this->assertNull($run->repourl);

        $results = array_values($repository->get_results($returned['runid']));
        $this->assertNull($results[0]->testsuite);
        $this->assertNull($results[0]->sourcefilepath);
    }

    public function test_it_rejects_an_ide_that_is_not_an_identifier(): void {
        $this->expectException(\invalid_parameter_exception::class);

        $this->submit($this->params(['ide' => 'Visual Studio Code']));
    }

    public function test_it_rejects_a_commithash_that_is_not_alphanumeric(): void {
        $this->expectException(\invalid_parameter_exception::class);

        $this->submit($this->params(['commithash' => 'abc123; rm -rf']));
    }

    public function test_credentials_in_the_repo_url_are_never_stored(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'repourl' => 'https://ada:ghp_secret@github.com/ada/calc.git',
        ]));

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertSame('https://github.com/ada/calc.git', $run->repourl);
    }

    public function test_a_repo_url_too_long_to_store_whole_is_dropped(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'repourl' => 'https://example.com/' . str_repeat('r', 1024),
        ]));

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertNull($run->repourl);
    }

    public function test_a_blank_repo_url_is_stored_as_null(): void {
        global $DB;

        $returned = $this->submit($this->params(['repourl' => '   ']));

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertNull($run->repourl);
    }

    public function test_it_stores_the_source_block_of_a_result(): void {
        global $DB;

        $returned = $this->submit($this->params(['results' => [[
            'testname' => 'test_add_returns_sum',
            'status' => 'PASSED',
            'testsuite' => 'test_calculator',
            'durationms' => 12,
            'message' => null,
            'source' => $this->source(),
        ]], 'testfiles' => $this->sourcefile()]));

        $results = array_values((new repository($DB))->get_results($returned['runid']));
        $this->assertSame('tests/test_calculator.py', $results[0]->sourcefilepath);
        $this->assertSame(1, (int) $results[0]->sourcestartline);
        $this->assertSame(2, (int) $results[0]->sourceendline);
    }

    public function test_a_result_without_a_source_block_stores_nulls(): void {
        global $DB;

        $returned = $this->submit($this->params());

        $results = array_values((new repository($DB))->get_results($returned['runid']));
        $this->assertNull($results[0]->sourcefilepath);
        $this->assertNull($results[0]->sourcestartline);
    }

    public function test_it_stores_the_submitted_test_files(): void {
        global $DB;

        $returned = $this->submit($this->params(['testfiles' => [
            [
                'path' => 'tests/test_calculator.py',
                'content' => "import pytest\n\ndef test_add_returns_sum():\n    assert add(2, 3) == 5\n",
                'truncated' => false,
            ],
            [
                'path' => 'tests/conftest.py',
                'content' => "import sys\n",
                'truncated' => true,
            ],
        ]]));

        $files = array_values((new repository($DB))->get_files($returned['runid']));
        $this->assertCount(2, $files);

        // Files are ordered by path, so conftest.py comes first.
        $this->assertSame('tests/conftest.py', $files[0]->path);
        $this->assertSame(1, (int) $files[0]->truncated);
        $this->assertSame('tests/test_calculator.py', $files[1]->path);
        $this->assertSame(source_code::hash($files[1]->content), $files[1]->contenthash);
        $this->assertStringContainsString('import pytest', $files[1]->content);
        $this->assertSame(0, (int) $files[1]->truncated);
    }

    public function test_a_submission_with_no_test_files_stores_none(): void {
        global $DB;

        $returned = $this->submit($this->params());

        $this->assertSame([], (new repository($DB))->get_files($returned['runid']));
    }

    public function test_it_stores_the_capture_flag(): void {
        global $DB;

        $returned = $this->submit($this->params(['capturedisabled' => true]));

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertSame(1, (int) $run->capturedisabled);
    }

    public function test_a_run_with_capture_disabled_stores_no_code(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'capturedisabled' => true,
            'results' => [[
                'testname' => 'test_add_returns_sum',
                'status' => 'PASSED',
                'testsuite' => 'test_calculator',
                'durationms' => 12,
                'message' => null,
                'source' => $this->source(),
            ]],
            'testfiles' => [[
                'path' => 'tests/test_calculator.py',
                'content' => "import pytest\n",
                'truncated' => true,
            ]],
        ]));

        $repository = new repository($DB);
        $results = array_values($repository->get_results($returned['runid']));
        $files = array_values($repository->get_files($returned['runid']));

        $this->assertCount(1, $files);
        $this->assertNull($files[0]->content);
        $this->assertSame(0, (int) $files[0]->truncated);
    }

    public function test_capture_disabled_leaves_no_body_hash(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'capturedisabled' => true,
            'results' => [[
                'testname' => 'test_add_returns_sum',
                'status' => 'PASSED',
                'testsuite' => 'test_calculator',
                'durationms' => 12,
                'message' => null,
                'source' => $this->source(),
            ]],
            'testfiles' => [[
                'path' => 'tests/test_calculator.py',
                'content' => "import pytest\n",
                'truncated' => false,
            ]],
        ]));

        $repository = new repository($DB);
        $results = array_values($repository->get_results($returned['runid']));
        $files = array_values($repository->get_files($returned['runid']));

        $this->assertSame('tests/test_calculator.py', $files[0]->path);
        $this->assertNull($files[0]->contenthash);
        $this->assertNull($files[0]->blobid);
    }

    public function test_capture_disabled_keeps_where_a_test_lives(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'capturedisabled' => true,
            'results' => [[
                'testname' => 'test_add_returns_sum',
                'status' => 'PASSED',
                'testsuite' => 'test_calculator',
                'durationms' => 12,
                'message' => null,
                'source' => $this->source(),
            ]],
        ]));

        $results = array_values((new repository($DB))->get_results($returned['runid']));
        $this->assertSame('tests/test_calculator.py', $results[0]->sourcefilepath);
        $this->assertSame(1, (int) $results[0]->sourcestartline);
        $this->assertSame(2, (int) $results[0]->sourceendline);
    }

    public function test_a_run_with_capture_enabled_keeps_its_code(): void {
        global $DB;

        $returned = $this->submit($this->params([
            'results' => [[
                'testname' => 'test_add_returns_sum',
                'status' => 'PASSED',
                'testsuite' => 'test_calculator',
                'durationms' => 12,
                'message' => null,
                'source' => $this->source(),
            ]],
            'testfiles' => $this->sourcefile(),
        ]));

        $repository = new repository($DB);
        $results = array_values($repository->get_results($returned['runid']));
        $files = array_values($repository->get_files($returned['runid']));

        $this->assertSame(self::DECLARATION, $files[0]->content);
        $this->assertCount(1, $files);
    }

    public function test_the_capture_flag_defaults_to_off(): void {
        global $DB;

        $returned = $this->submit($this->params());

        $run = (new repository($DB))->get_run($returned['runid'], $this->instance->id);
        $this->assertSame(0, (int) $run->capturedisabled);
    }

    public function test_oversized_file_content_is_clipped_and_marked_truncated(): void {
        global $DB;

        $returned = $this->submit($this->params(['testfiles' => [[
            'path' => 'tests/big.py',
            'content' => str_repeat('y', 600000),
            'truncated' => false,
        ]]]));

        $files = array_values((new repository($DB))->get_files($returned['runid']));
        $this->assertSame(524288, strlen($files[0]->content));
        $this->assertSame(1, (int) $files[0]->truncated);
    }

    public function test_oversized_multibyte_content_is_clipped_by_bytes_on_a_character_boundary(): void {
        global $DB;

        $returned = $this->submit($this->params(['testfiles' => [[
            'path' => 'tests/big.py',
            'content' => str_repeat('õ', 300000),
            'truncated' => false,
        ]]]));

        $files = array_values((new repository($DB))->get_files($returned['runid']));
        $this->assertLessThanOrEqual(524288, strlen($files[0]->content));
        $this->assertTrue(mb_check_encoding($files[0]->content, 'UTF-8'));
        $this->assertSame(1, (int) $files[0]->truncated);
    }

    public function test_it_rejects_more_than_the_maximum_number_of_files(): void {
        $files = array_fill(0, 201, [
            'path' => 'tests/test.py', 'content' => null, 'truncated' => false,
        ]);

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_toomanyfiles', 'mod_idetestfeedback', 200));

        $this->submit($this->params(['testfiles' => $files]));
    }

    public function test_it_rejects_a_file_without_a_path(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_nofilepath', 'mod_idetestfeedback'));

        $this->submit($this->params(['testfiles' => [
            ['path' => '   ', 'content' => null, 'truncated' => false],
        ]]));
    }

    /**
     * Data provider for test_it_rejects_an_impossible_source_line_range().
     *
     * @return array[] [the source line numbers to submit]
     */
    public static function invalid_source_lines_provider(): array {
        return [
            'negative start' => [-1, 10],
            'negative end' => [1, -10],
            'ends before it starts' => [13, 12],
        ];
    }

    /**
     * An impossible source line range is rejected.
     *
     * @param int $startline the first line to submit
     * @param int $endline the last line to submit
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_source_lines_provider')]
    public function test_it_rejects_an_impossible_source_line_range(int $startline, int $endline): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_invalidsourcelines', 'mod_idetestfeedback'));

        $this->submit($this->params(['results' => [[
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'testsuite' => null,
            'durationms' => null,
            'message' => null,
            'source' => $this->source(['startline' => $startline, 'endline' => $endline]),
        ]]]));
    }

    public function test_it_triggers_a_test_run_submitted_event(): void {
        $sink = $this->redirectEvents();

        $returned = $this->submit($this->params());

        $events = $sink->get_events();
        $matching = array_values(array_filter($events, fn($e) => $e instanceof \mod_idetestfeedback\event\test_run_submitted));
        $this->assertCount(1, $matching);
        $event = $matching[0];
        $this->assertSame($returned['runid'], (int) $event->objectid);
        $this->assertSame((int) $this->student->id, (int) $event->userid);
        $this->assertSame((int) $this->student->id, (int) $event->relateduserid);
        $this->assertSame(status::PASSED->value, $event->other['status']);
    }

    public function test_a_source_of_kind_none_stores_nothing_about_where_the_test_lives(): void {
        global $DB;

        $returned = $this->submit($this->params(['results' => [[
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'testsuite' => null,
            'durationms' => null,
            'message' => null,
            'source' => ['path' => null, 'startline' => null, 'endline' => null],
        ]]]));

        $results = array_values((new repository($DB))->get_results($returned['runid']));
        $this->assertNull($results[0]->sourcefilepath);
        $this->assertNull($results[0]->sourcestartline);
        $this->assertNull($results[0]->sourceendline);
    }

    public function test_a_source_of_kind_file_drops_line_numbers_it_cannot_mean(): void {
        global $DB;

        $returned = $this->submit($this->params(['results' => [[
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'testsuite' => null,
            'durationms' => null,
            'message' => null,
            'source' => $this->source(['startline' => null, 'endline' => null]),
        ]], 'testfiles' => $this->sourcefile()]));

        $results = array_values((new repository($DB))->get_results($returned['runid']));
        $this->assertSame('tests/test_calculator.py', $results[0]->sourcefilepath);
        $this->assertNull($results[0]->sourcestartline);
        $this->assertNull($results[0]->sourceendline);
    }

    public function test_it_rejects_a_located_source_without_a_file(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(
            get_string('validation_nosourcefilepath', 'mod_idetestfeedback')
        );

        $this->submit($this->params(['results' => [[
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'testsuite' => null,
            'durationms' => null,
            'message' => null,
            'source' => $this->source(['path' => '']),
        ]]]));
    }

    public function test_it_rejects_half_a_line_range(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_invalidsourcelines', 'mod_idetestfeedback'));

        $this->submit($this->params(['results' => [[
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'testsuite' => null,
            'durationms' => null,
            'message' => null,
            'source' => $this->source(['endline' => null]),
        ]]]));
    }

    public function test_it_rejects_a_result_without_a_test_name(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_notestname', 'mod_idetestfeedback'));

        $this->submit($this->params(['results' => [
            ['testname' => '  ', 'status' => 'PASSED', 'testsuite' => null,
                'durationms' => null, 'message' => null],
        ]]));
    }

    public function test_it_stores_one_row_per_test_file_path(): void {
        global $DB;

        $returned = $this->submit($this->params(['testfiles' => [
            ['path' => 'tests/test_calculator.py', 'content' => "import pytest\n",
                'truncated' => false],
            ['path' => 'tests/test_calculator.py', 'content' => "import sys\n",
                'truncated' => false],
        ]]));

        $files = array_values((new repository($DB))->get_files($returned['runid']));
        $this->assertCount(1, $files);
        $this->assertSame('import pytest', $files[0]->content);
        $this->assertSame(source_code::hash("import pytest\n"), $files[0]->contenthash);
    }

    public function test_it_rejects_a_hash_a_client_tries_to_assert(): void {
        $this->expectException(\invalid_parameter_exception::class);

        $this->submit($this->params(['testfiles' => [[
            'path' => 'tests/test_calculator.py',
            'sha256' => 'c7be1ed902fb8dd4',
            'content' => "import pytest\n",
            'truncated' => false,
        ]]]));
    }

    public function test_it_rejects_a_normalised_code_hash_a_client_tries_to_assert(): void {
        $this->expectException(\invalid_parameter_exception::class);

        $this->submit($this->params(['results' => [[
            'testname' => 'testAdd',
            'status' => 'PASSED',
            'testsuite' => null,
            'durationms' => null,
            'message' => null,
            'source' => $this->source(['normalizedcodehash' => 'b1946ac92492d234']),
        ]]]));
    }

    public function test_clipping_a_file_keeps_whole_lines(): void {
        global $DB;

        $returned = $this->submit($this->params(['testfiles' => [[
            'path' => 'tests/big.py',
            'content' => str_repeat("assert True\n", 60000),
            'truncated' => false,
        ]]]));

        $files = array_values((new repository($DB))->get_files($returned['runid']));
        $this->assertSame(1, (int) $files[0]->truncated);
        $this->assertStringEndsWith('assert True', $files[0]->content);
    }

    public function test_it_rejects_a_payload_that_is_not_json(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_invalidpayload', 'mod_idetestfeedback'));

        $this->submit_payload($this->params(), 'not json at all');
    }

    public function test_it_rejects_a_payload_that_is_not_an_object(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_invalidpayload', 'mod_idetestfeedback'));

        $this->submit_payload($this->params(), '"a string"');
    }

    public function test_it_rejects_a_payload_carrying_an_unexpected_key(): void {
        $this->expectException(\invalid_parameter_exception::class);

        $this->submit_payload($this->params(), json_encode(['results' => [], 'bogus' => 1]));
    }

    public function test_it_accepts_a_run_far_past_the_post_variable_limit(): void {
        global $DB;

        $results = array_fill(0, 1500, [
            'testname' => 'test', 'status' => 'PASSED', 'testsuite' => null,
            'durationms' => null, 'message' => null,
        ]);

        $returned = $this->submit($this->params(['results' => $results]));

        $this->assertCount(1500, (new repository($DB))->get_results($returned['runid']));
    }

    public function test_it_rejects_an_empty_results_array(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_noresults', 'mod_idetestfeedback'));

        $this->submit($this->params(['results' => []]));
    }

    public function test_it_rejects_more_than_the_maximum_number_of_results(): void {
        $results = array_fill(0, 5001, [
            'testname' => 'test', 'status' => 'PASSED', 'testsuite' => null,
            'durationms' => null, 'message' => null,
        ]);

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_toomanyresults', 'mod_idetestfeedback', 5000));

        $this->submit($this->params(['results' => $results]));
    }

    public function test_it_rejects_a_blank_ide(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_noide', 'mod_idetestfeedback'));

        $this->submit($this->params(['ide' => '']));
    }

    public function test_it_rejects_an_invalid_result_status(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['results' => [
            ['testname' => 'testAdd', 'status' => 'BOGUS', 'testsuite' => null,
                'durationms' => null, 'message' => null],
        ]]));
    }

    public function test_it_rejects_a_negative_duration(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['results' => [[
            'testname' => 'testAdd', 'status' => 'PASSED', 'testsuite' => null,
            'durationms' => -1, 'message' => null,
        ]]]));
    }

    public function test_it_rejects_a_negative_startedatms(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['startedatms' => -1]));
    }

    public function test_it_rejects_a_negative_finishedatms(): void {
        $this->expectException(validation_exception::class);

        $this->submit($this->params(['finishedatms' => -1]));
    }

    public function test_it_rejects_a_run_finishing_before_it_started(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_invalidtiming', 'mod_idetestfeedback'));

        $this->submit($this->params(['startedatms' => 2000, 'finishedatms' => 1000]));
    }

    public function test_it_rejects_an_unknown_email(): void {
        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_usernotfound', 'mod_idetestfeedback', 'nobody@example.com'));

        $this->submit($this->params(['email' => 'nobody@example.com']));
    }

    public function test_it_rejects_an_empty_email(): void {
        $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['email' => '']);

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_noemail', 'mod_idetestfeedback'));

        $this->submit($this->params(['email' => '']));
    }

    public function test_it_checks_the_capability_before_reading_the_payload(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);

        $this->submit_payload($this->params(), 'not json at all');
    }

    public function test_it_rejects_a_submission_to_a_hidden_activity(): void {
        set_coursemodule_visible($this->instance->cmid, 0);

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_activityunavailable', 'mod_idetestfeedback'));

        $this->submit($this->params());
    }

    public function test_it_rejects_a_student_who_may_not_view_the_activity(): void {
        global $DB;

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            'mod/idetestfeedback:view',
            CAP_PROHIBIT,
            $studentrole,
            \context_module::instance($this->instance->cmid)
        );

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_activityunavailable', 'mod_idetestfeedback'));

        $this->submit($this->params());
    }

    public function test_it_rejects_a_teacher_submitting_their_own_runs(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher', [
            'email' => 'teacher@example.com',
        ]);

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_cannotrecordruns', 'mod_idetestfeedback'));

        $this->submit($this->params(['email' => $teacher->email]));
    }

    public function test_it_rejects_a_student_who_may_not_record_runs(): void {
        global $DB;

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            'mod/idetestfeedback:recordruns',
            CAP_PROHIBIT,
            $studentrole,
            \context_module::instance($this->instance->cmid)
        );

        $this->expectException(validation_exception::class);
        $this->expectExceptionMessage(get_string('validation_cannotrecordruns', 'mod_idetestfeedback'));

        $this->submit($this->params());
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
