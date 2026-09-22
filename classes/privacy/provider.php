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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_idetestfeedback\local\repository;

/**
 * Privacy API implementation for the activity.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * @param collection $collection the metadata being built
     * @return collection
     */
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'idetestfeedback_run',
            [
                'userid'       => 'privacy:metadata:run:userid',
                'ide'          => 'privacy:metadata:run:ide',
                'projectname'  => 'privacy:metadata:run:projectname',
                'commithash'   => 'privacy:metadata:run:commithash',
                'startedat'    => 'privacy:metadata:run:startedat',
                'finishedat'   => 'privacy:metadata:run:finishedat',
                'status'       => 'privacy:metadata:run:status',
                'passedcount'  => 'privacy:metadata:run:passedcount',
                'failedcount'  => 'privacy:metadata:run:failedcount',
                'skippedcount' => 'privacy:metadata:run:skippedcount',
                'errorcount'   => 'privacy:metadata:run:errorcount',
                'timecreated'  => 'privacy:metadata:run:timecreated',
                'capturedisabled'     => 'privacy:metadata:run:capturedisabled',
                'warningacknowledged' => 'privacy:metadata:run:warningacknowledged',
            ],
            'privacy:metadata:run'
        );

        $collection->add_database_table(
            'idetestfeedback_result',
            [
                'testsuite'      => 'privacy:metadata:result:testsuite',
                'testname'       => 'privacy:metadata:result:testname',
                'status'         => 'privacy:metadata:result:status',
                'durationms'       => 'privacy:metadata:result:durationms',
                'message'          => 'privacy:metadata:result:message',
                'timecreated'      => 'privacy:metadata:result:timecreated',
                'feedback'         => 'privacy:metadata:result:feedback',
                'feedbackformat'   => 'privacy:metadata:result:feedbackformat',
                'feedbackby'       => 'privacy:metadata:result:feedbackby',
                'feedbackmodified' => 'privacy:metadata:result:feedbackmodified',
                'sourcefilepath'   => 'privacy:metadata:result:sourcefilepath',
                'sourcestartline'  => 'privacy:metadata:result:sourcestartline',
                'sourceendline'    => 'privacy:metadata:result:sourceendline',
                'sourcecodehash'   => 'privacy:metadata:result:sourcecodehash',
            ],
            'privacy:metadata:result'
        );

        $collection->add_database_table(
            'idetestfeedback_file',
            [
                'path'        => 'privacy:metadata:file:path',
                'blobid'      => 'privacy:metadata:file:blobid',
                'truncated'   => 'privacy:metadata:file:truncated',
                'timecreated' => 'privacy:metadata:file:timecreated',
            ],
            'privacy:metadata:file'
        );

        $collection->add_database_table(
            'idetestfeedback_blob',
            [
                'contenthash' => 'privacy:metadata:blob:contenthash',
                'content'     => 'privacy:metadata:blob:content',
                'timecreated' => 'privacy:metadata:blob:timecreated',
            ],
            'privacy:metadata:blob'
        );

        // Students are notified when a teacher leaves feedback on one of their runs.
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:messages');

        return $collection;
    }

    /**
     * @param int $userid the user to look for
     * @return contextlist the contexts holding data about them
     */
    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $joins = "FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'idetestfeedback'
                  JOIN {idetestfeedback} mp ON mp.id = cm.instance
                  JOIN {idetestfeedback_run} r ON r.idetestfeedbackid = mp.id";

        $contextlist = new contextlist();

        $contextlist->add_from_sql(
            "SELECT ctx.id {$joins} WHERE r.userid = :userid",
            ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]
        );

        $contextlist->add_from_sql(
            "SELECT ctx.id
               {$joins}
               JOIN {idetestfeedback_result} res ON res.runid = r.id
              WHERE res.feedbackby = :userid",
            ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]
        );

        return $contextlist;
    }

    /**
     * @param userlist $userlist the users found in one context
     */
    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {idetestfeedback_run} WHERE idetestfeedbackid = :instanceid",
            ['instanceid' => $cm->instance]
        );

        $userlist->add_from_sql(
            'feedbackby',
            "SELECT res.feedbackby
               FROM {idetestfeedback_result} res
               JOIN {idetestfeedback_run} r ON r.id = res.runid
              WHERE r.idetestfeedbackid = :instanceid
                AND res.feedbackby IS NOT NULL",
            ['instanceid' => $cm->instance]
        );
    }

    /**
     * @param approved_contextlist $contextlist the contexts approved for export
     */
    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        $repository = self::repository();
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $runs = $repository->get_runs_for_user((int) $cm->instance, $userid);

            foreach ($runs as $run) {
                $results = $repository->get_results((int) $run->id);
                $data = (object) [
                    'ide'          => $run->ide,
                    'projectname'  => $run->projectname,
                    'commithash'   => $run->commithash,
                    // Stored in milliseconds, as the IDE reports them.
                    'startedat'    => $run->startedat ? transform::datetime(intdiv((int) $run->startedat, 1000)) : null,
                    'finishedat'   => $run->finishedat
                        ? transform::datetime(intdiv((int) $run->finishedat, 1000)) : null,
                    'status'       => $run->status,
                    'passedcount'  => $run->passedcount,
                    'failedcount'  => $run->failedcount,
                    'skippedcount' => $run->skippedcount,
                    'errorcount'   => $run->errorcount,
                    'timecreated'  => transform::datetime($run->timecreated),
                    'capturedisabled'     => transform::yesno($run->capturedisabled),
                    'warningacknowledged' => transform::yesno($run->warningacknowledged),
                    'results'      => array_values(array_map(
                        fn($r) => [
                            'testsuite'        => $r->testsuite,
                            'testname'         => $r->testname,
                            'status'           => $r->status,
                            'durationms'       => $r->durationms,
                            'message'          => $r->message,
                            'timecreated'      => transform::datetime($r->timecreated),
                            'feedback'         => $r->feedback,
                            'feedbackmodified' => $r->feedbackmodified
                                ? transform::datetime($r->feedbackmodified) : null,
                            'sourcefilepath'   => $r->sourcefilepath,
                            'sourcestartline'  => $r->sourcestartline,
                            'sourceendline'    => $r->sourceendline,
                            'sourcecodehash'   => $r->sourcecodehash,
                        ],
                        $results
                    )),
                    'testfiles'    => array_values(array_map(
                        fn($f) => [
                            'path'        => $f->path,
                            'sha256'      => $f->sha256,
                            'content'     => $f->content,
                            'truncated'   => transform::yesno($f->truncated),
                            'timecreated' => transform::datetime($f->timecreated),
                        ],
                        $repository->get_files((int) $run->id)
                    )),
                ];
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:runs', 'mod_idetestfeedback'), 'run_' . $run->id],
                    $data
                );
            }

            self::export_feedback_given($repository, $context, (int) $cm->instance, $userid);
        }
    }

    /**
     * Exports the feedback the user wrote on other people's runs.
     *
     * @param repository $repository the activity's database access
     * @param \context_module $context the activity context
     * @param int $instanceid the activity instance id
     * @param int $userid the feedback author
     */
    private static function export_feedback_given(repository $repository, \context_module $context,
                                                  int $instanceid, int $userid): void {
        $results = $repository->get_feedback_authored_by($instanceid, $userid);

        if (!$results) {
            return;
        }

        writer::with_context($context)->export_data(
            [get_string('privacy:path:feedbackgiven', 'mod_idetestfeedback')],
            (object) [
                'feedback' => array_values(array_map(
                    fn($r) => [
                        'runid'            => $r->runid,
                        'testsuite'        => $r->testsuite,
                        'testname'         => $r->testname,
                        'feedback'         => $r->feedback,
                        'feedbackmodified' => $r->feedbackmodified
                            ? transform::datetime($r->feedbackmodified) : null,
                    ],
                    $results
                )),
            ]
        );
    }

    /**
     * @param \context $context the context to empty
     */
    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
        if (!$cm) {
            return;
        }

        self::repository()->delete_runs($cm->instance);
    }

    /**
     * @param approved_contextlist $contextlist the contexts approved for deletion
     */
    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
            if (!$cm) {
                continue;
            }

            self::forget_users($cm->instance, [$userid]);
        }
    }

    /**
     * @param approved_userlist $userlist the users approved for deletion
     */
    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
        if (!$cm) {
            return;
        }

        self::forget_users($cm->instance, $userlist->get_userids());
    }

    /**
     * Deletes the users' runs and detaches them from any feedback they wrote.
     *
     * @param int $instanceid the idetestfeedback instance id
     * @param int[] $userids the users to forget
     */
    private static function forget_users(int $instanceid, array $userids): void {
        $repository = self::repository();

        $repository->anonymise_feedback_authors($instanceid, $userids);
        $repository->delete_runs($instanceid, $userids);
    }

    /**
     * @return repository the activity's database access
     */
    private static function repository(): repository {
        global $DB;

        return new repository($DB);
    }
}
