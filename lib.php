<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function idetestfeedback_add_instance(stdClass $data): int {
    global $DB;

    $data->assignmentkey = bin2hex(random_bytes(16));
    $data->timeopen      = $data->timeopen  ?? 0;
    $data->timeclose     = $data->timeclose ?? 0;
    $data->requiredtests = \mod_idetestfeedback\local\required_tests::normalize($data->requiredtests ?? null);
    $data->timecreated   = time();
    $data->timemodified  = time();

    return $DB->insert_record('idetestfeedback', $data);
}

function idetestfeedback_update_instance(stdClass $data): bool {
    global $DB;

    $data->id           = $data->instance;
    $data->timeopen     = $data->timeopen  ?? 0;
    $data->timeclose    = $data->timeclose ?? 0;
    $data->requiredtests = \mod_idetestfeedback\local\required_tests::normalize($data->requiredtests ?? null);
    $data->timemodified = time();

    return $DB->update_record('idetestfeedback', $data);
}

function idetestfeedback_delete_instance(int $id): bool {
    global $DB;

    $runids = $DB->get_fieldset_select('idetestfeedback_run', 'id', 'idetestfeedbackid = ?', [$id]);
    if ($runids) {
        [$insql, $inparams] = $DB->get_in_or_equal($runids);
        $DB->delete_records_select('idetestfeedback_result', "runid $insql", $inparams);
    }
    $DB->delete_records('idetestfeedback_run', ['idetestfeedbackid' => $id]);
    $DB->delete_records('idetestfeedback', ['id' => $id]);

    return true;
}

function idetestfeedback_supports(string $feature): string|bool|null {
    return match ($feature) {
        FEATURE_MOD_INTRO                => true,
        FEATURE_SHOW_DESCRIPTION         => true,
        FEATURE_BACKUP_MOODLE2           => false,
        FEATURE_COMPLETION_TRACKS_VIEWS  => true,
        FEATURE_COMPLETION_HAS_RULES     => true,
        FEATURE_MOD_PURPOSE              => MOD_PURPOSE_ASSESSMENT,
        default                         => null,
    };
}

/**
 * Surfaces the custom completion rule to the course page and completion reports.
 */
function idetestfeedback_get_coursemodule_info($coursemodule) {
    global $DB;

    $instance = $DB->get_record(
        'idetestfeedback',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, completionpassrun'
    );
    if (!$instance) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('idetestfeedback', $instance, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionpassrun'] = $instance->completionpassrun;
    }

    return $info;
}
