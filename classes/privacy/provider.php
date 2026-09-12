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
                'stacktracehash'   => 'privacy:metadata:result:stacktracehash',
                'timecreated'      => 'privacy:metadata:result:timecreated',
                'feedback'         => 'privacy:metadata:result:feedback',
                'feedbackby'       => 'privacy:metadata:result:feedbackby',
                'feedbackmodified' => 'privacy:metadata:result:feedbackmodified',
            ],
            'privacy:metadata:result'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {course_modules} cm ON cm.id = ctx.instanceid
                AND ctx.contextlevel = :contextlevel
               JOIN {idetestfeedback} mp ON mp.id = cm.instance
               JOIN {modules} m ON m.id = cm.module AND m.name = 'idetestfeedback'
               JOIN {idetestfeedback_run} r ON r.idetestfeedbackid = mp.id
              WHERE r.userid = :userid",
            ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]
        );
        return $contextlist;
    }

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
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $runs = $DB->get_records(
                'idetestfeedback_run',
                ['idetestfeedbackid' => $cm->instance, 'userid' => $userid],
                'timecreated DESC'
            );

            foreach ($runs as $run) {
                $results = $DB->get_records('idetestfeedback_result', ['runid' => $run->id], 'id ASC');
                $data = (object) [
                    'ide'          => $run->ide,
                    'projectname'  => $run->projectname,
                    'commithash'   => $run->commithash,
                    'startedat'    => $run->startedat ? transform::datetime($run->startedat) : null,
                    'finishedat'   => $run->finishedat ? transform::datetime($run->finishedat) : null,
                    'status'       => $run->status,
                    'passedcount'  => $run->passedcount,
                    'failedcount'  => $run->failedcount,
                    'skippedcount' => $run->skippedcount,
                    'errorcount'   => $run->errorcount,
                    'timecreated'  => transform::datetime($run->timecreated),
                    'results'      => array_values(array_map(
                        fn($r) => [
                            'testsuite'        => $r->testsuite,
                            'testname'         => $r->testname,
                            'status'           => $r->status,
                            'durationms'       => $r->durationms,
                            'message'          => $r->message,
                            'stacktracehash'   => $r->stacktracehash,
                            'timecreated'      => transform::datetime($r->timecreated),
                            'feedback'         => $r->feedback,
                            'feedbackmodified' => $r->feedbackmodified
                                ? transform::datetime($r->feedbackmodified) : null,
                        ],
                        $results
                    )),
                ];
                writer::with_context($context)->export_data(['run_' . $run->id], $data);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
        if (!$cm) {
            return;
        }

        self::delete_runs($cm->instance);
    }

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

            self::delete_runs($cm->instance, [$userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
        if (!$cm) {
            return;
        }

        self::delete_runs($cm->instance, $userlist->get_userids());
    }

    /**
     * Deletes runs (and their results) for an instance, optionally limited to specific users.
     *
     * @param int $instanceid the idetestfeedback instance id
     * @param int[]|null $userids null = every user; otherwise only these users
     */
    private static function delete_runs(int $instanceid, ?array $userids = null): void {
        global $DB;

        $runselect = 'idetestfeedbackid = :instanceid';
        $runparams = ['instanceid' => $instanceid];

        if ($userids !== null) {
            if (empty($userids)) {
                return;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $runselect .= " AND userid $insql";
            $runparams += $inparams;
        }

        $runids = $DB->get_fieldset_select('idetestfeedback_run', 'id', $runselect, $runparams);
        if ($runids) {
            [$insql, $inparams] = $DB->get_in_or_equal($runids);
            $DB->delete_records_select('idetestfeedback_result', "runid $insql", $inparams);
        }
        $DB->delete_records_select('idetestfeedback_run', $runselect, $runparams);
    }
}
