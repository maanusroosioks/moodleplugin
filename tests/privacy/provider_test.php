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

namespace mod_idetestfeedback\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\source_code;

/**
 * Tests for the privacy API implementation.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\privacy\provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $instance;

    /** @var \context_module */
    private \context_module $context;

    /** @var \stdClass */
    private \stdClass $student;

    /** @var \stdClass */
    private \stdClass $teacher;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('idetestfeedback', ['course' => $this->course->id]);
        $this->context = \context_module::instance(get_coursemodule_from_instance('idetestfeedback', $this->instance->id)->id);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
    }

    /**
     * The repository under test.
     *
     * @return repository the activity's database access
     */
    private function repository(): repository {
        global $DB;

        return new repository($DB);
    }

    /**
     * Stores a run for a user in the test activity.
     *
     * @param int $userid the run's owner
     * @return \stdClass the stored run, with a single test case result
     */
    private function create_run(int $userid): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $userid,
            'results' => [['testname' => 'testAdd', 'status' => 'PASSED']],
        ]);
    }

    public function test_get_metadata_documents_both_tables_and_the_message_subsystem(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_idetestfeedback'));
        $items = $collection->get_collection();

        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains('idetestfeedback_run', $names);
        $this->assertContains('idetestfeedback_result', $names);
        $this->assertContains('core_message', $names);
    }

    public function test_get_contexts_for_userid_includes_the_run_owners_context(): void {
        $this->create_run($this->student->id);

        $contextlist = provider::get_contexts_for_userid($this->student->id);

        $this->assertEqualsCanonicalizing([$this->context->id], $contextlist->get_contextids());
    }

    public function test_get_contexts_for_userid_includes_a_feedback_authors_context(): void {
        $run = $this->create_run($this->student->id);
        $repository = $this->repository();
        [$result] = array_values($repository->get_results($run->id));
        $repository->update_result_feedback((int) $result->id, 'Nice', $this->teacher->id);

        $contextlist = provider::get_contexts_for_userid($this->teacher->id);

        $this->assertEqualsCanonicalizing([$this->context->id], $contextlist->get_contextids());
    }

    public function test_get_contexts_for_userid_is_empty_for_an_uninvolved_user(): void {
        $this->create_run($this->student->id);
        $bystander = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $contextlist = provider::get_contexts_for_userid($bystander->id);

        $this->assertSame([], $contextlist->get_contextids());
    }

    public function test_get_users_in_context_lists_run_owners_and_feedback_authors(): void {
        $run = $this->create_run($this->student->id);
        $repository = $this->repository();
        [$result] = array_values($repository->get_results($run->id));
        $repository->update_result_feedback((int) $result->id, 'Nice', $this->teacher->id);

        $userlist = new userlist($this->context, 'mod_idetestfeedback');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing(
            [(int) $this->student->id, (int) $this->teacher->id],
            array_map('intval', $userlist->get_userids())
        );
    }

    public function test_get_users_in_context_ignores_a_non_module_context(): void {
        $userlist = new userlist(\context_system::instance(), 'mod_idetestfeedback');
        provider::get_users_in_context($userlist);

        $this->assertSame([], $userlist->get_userids());
    }

    public function test_export_user_data_writes_the_students_own_runs(): void {
        $run = $this->create_run($this->student->id);

        $approved = new approved_contextlist($this->student, 'mod_idetestfeedback', [$this->context->id]);
        provider::export_user_data($approved);

        $data = writer::with_context($this->context)->get_data(
            [get_string('privacy:path:runs', 'mod_idetestfeedback'), 'run_' . $run->id]
        );
        $this->assertNotFalse($data);
        $this->assertSame('VSCODE', $data->ide);
        $this->assertCount(1, $data->results);
        $this->assertSame('testAdd', $data->results[0]['testname']);
    }

    public function test_export_user_data_writes_the_test_files_captured_with_a_run(): void {
        $run = $this->getDataGenerator()->get_plugin_generator('mod_idetestfeedback')->create_run([
            'idetestfeedbackid' => $this->instance->id,
            'userid' => $this->student->id,
            'results' => [['testname' => 'testAdd', 'status' => 'PASSED']],
            'files' => [[
                'path' => 'tests/test_calculator.py',
                'content' => "import pytest\n",
            ]],
        ]);

        $approved = new approved_contextlist($this->student, 'mod_idetestfeedback', [$this->context->id]);
        provider::export_user_data($approved);

        $data = writer::with_context($this->context)->get_data(
            [get_string('privacy:path:runs', 'mod_idetestfeedback'), 'run_' . $run->id]
        );
        $this->assertCount(1, $data->testfiles);
        $this->assertSame('tests/test_calculator.py', $data->testfiles[0]['path']);
        $this->assertSame(source_code::hash_canonical('import pytest'), $data->testfiles[0]['contenthash']);
    }

    public function test_export_user_data_writes_feedback_the_user_gave_on_others_runs(): void {
        $run = $this->create_run($this->student->id);
        $repository = $this->repository();
        [$result] = array_values($repository->get_results($run->id));
        $repository->update_result_feedback((int) $result->id, 'Nice work', $this->teacher->id);

        $approved = new approved_contextlist($this->teacher, 'mod_idetestfeedback', [$this->context->id]);
        provider::export_user_data($approved);

        $data = writer::with_context($this->context)->get_data(
            [get_string('privacy:path:feedbackgiven', 'mod_idetestfeedback')]
        );
        $this->assertNotFalse($data);
        $this->assertCount(1, $data->feedback);
        $this->assertSame('Nice work', $data->feedback[0]['feedback']);
    }

    public function test_delete_data_for_all_users_in_context_removes_every_run(): void {
        global $DB;

        $this->create_run($this->student->id);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->create_run($other->id);

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertSame(0, $DB->count_records('idetestfeedback_run', ['idetestfeedbackid' => $this->instance->id]));
    }

    public function test_delete_data_for_user_removes_only_that_users_runs(): void {
        global $DB;

        $mine = $this->create_run($this->student->id);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $theirs = $this->create_run($other->id);

        $approved = new approved_contextlist($this->student, 'mod_idetestfeedback', [$this->context->id]);
        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('idetestfeedback_run', ['id' => $mine->id]));
        $this->assertTrue($DB->record_exists('idetestfeedback_run', ['id' => $theirs->id]));
    }

    public function test_delete_data_for_user_detaches_their_authored_feedback(): void {
        $run = $this->create_run($this->student->id);
        $repository = $this->repository();
        [$result] = array_values($repository->get_results($run->id));
        $repository->update_result_feedback((int) $result->id, 'Nice', $this->teacher->id);

        $approved = new approved_contextlist($this->teacher, 'mod_idetestfeedback', [$this->context->id]);
        provider::delete_data_for_user($approved);

        [$result] = array_values($repository->get_results($run->id));
        $this->assertNull($result->feedbackby);
        $this->assertSame('Nice', $result->feedback);
    }

    public function test_delete_data_for_users_removes_only_the_listed_users(): void {
        global $DB;

        $remove = $this->create_run($this->student->id);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $keep = $this->create_run($other->id);

        $approved = new approved_userlist($this->context, 'mod_idetestfeedback', [$this->student->id]);
        provider::delete_data_for_users($approved);

        $this->assertFalse($DB->record_exists('idetestfeedback_run', ['id' => $remove->id]));
        $this->assertTrue($DB->record_exists('idetestfeedback_run', ['id' => $keep->id]));
    }
}
