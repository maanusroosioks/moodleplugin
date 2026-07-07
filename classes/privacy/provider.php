<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'idetestfeedback_run',
            [
                'userid'      => 'privacy:metadata:run:userid',
                'ide'         => 'privacy:metadata:run:ide',
                'projectname' => 'privacy:metadata:run:projectname',
                'commithash'  => 'privacy:metadata:run:commithash',
                'status'      => 'privacy:metadata:run:status',
                'timecreated' => 'privacy:metadata:run:timecreated',
            ],
            'privacy:metadata:run'
        );

        $collection->add_database_table(
            'idetestfeedback_result',
            [
                'testname' => 'privacy:metadata:result:testname',
                'status'   => 'privacy:metadata:result:status',
                'message'  => 'privacy:metadata:result:message',
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

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
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
                    'status'       => $run->status,
                    'passedcount'  => $run->passedcount,
                    'failedcount'  => $run->failedcount,
                    'skippedcount' => $run->skippedcount,
                    'errorcount'   => $run->errorcount,
                    'timecreated'  => transform::datetime($run->timecreated),
                    'results'      => array_values(array_map(
                        fn($r) => ['testname' => $r->testname, 'status' => $r->status, 'message' => $r->message],
                        $results
                    )),
                ];
                writer::with_context($context)->export_data(['run_' . $run->id], $data);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
        if (!$cm) {
            return;
        }

        $runids = $DB->get_fieldset_select('idetestfeedback_run', 'id', 'idetestfeedbackid = ?', [$cm->instance]);
        if ($runids) {
            [$insql, $inparams] = $DB->get_in_or_equal($runids);
            $DB->delete_records_select('idetestfeedback_result', "runid $insql", $inparams);
        }
        $DB->delete_records('idetestfeedback_run', ['idetestfeedbackid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('idetestfeedback', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $runids = $DB->get_fieldset_select(
                'idetestfeedback_run', 'id',
                'idetestfeedbackid = ? AND userid = ?',
                [$cm->instance, $userid]
            );
            if ($runids) {
                [$insql, $inparams] = $DB->get_in_or_equal($runids);
                $DB->delete_records_select('idetestfeedback_result', "runid $insql", $inparams);
                $DB->delete_records('idetestfeedback_run', ['idetestfeedbackid' => $cm->instance, 'userid' => $userid]);
            }
        }
    }
}
