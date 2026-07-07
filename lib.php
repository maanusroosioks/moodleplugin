<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function idetestfeedback_add_instance(stdClass $data): int {
    global $DB;

    $data->assignmentkey = bin2hex(random_bytes(16));
    $data->timeopen      = $data->timeopen  ?? 0;
    $data->timeclose     = $data->timeclose ?? 0;
    $data->timecreated   = time();
    $data->timemodified  = time();

    return $DB->insert_record('idetestfeedback', $data);
}

function idetestfeedback_update_instance(stdClass $data): bool {
    global $DB;

    $data->id           = $data->instance;
    $data->timeopen     = $data->timeopen  ?? 0;
    $data->timeclose    = $data->timeclose ?? 0;
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

function idetestfeedback_supports(string $feature): ?bool {
    return match ($feature) {
        FEATURE_MOD_INTRO        => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_BACKUP_MOODLE2   => false,
        FEATURE_MOD_PURPOSE      => MOD_PURPOSE_ASSESSMENT,
        default                  => null,
    };
}
