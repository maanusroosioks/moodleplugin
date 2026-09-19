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
 * Database upgrade steps for the activity.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Runs the upgrade steps for this activity.
 *
 * @param int $oldversion the currently installed version
 * @return bool
 */
function xmldb_idetestfeedback_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026091700) {
        $run = new xmldb_table('idetestfeedback_run');

        // The IDE now reports epoch milliseconds, so these columns are declared
        // wide enough for them. Rows written before this are in seconds, except
        // where a database mapped the old width to a bigint and let millisecond
        // values through already, so only second-sized values are scaled up.
        // As seconds 1e11 is the year 5138; as milliseconds it is 1973, so no
        // real timestamp is ambiguous.
        foreach (['startedat', 'finishedat'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '18', null, null, null, null);
            $dbman->change_field_precision($run, $field);
            $DB->execute(
                "UPDATE {idetestfeedback_run}
                    SET {$name} = {$name} * 1000
                  WHERE {$name} IS NOT NULL
                    AND {$name} < 100000000000"
            );
        }

        $field = new xmldb_field('capturedisabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($run, $field)) {
            $dbman->add_field($run, $field);
        }

        $field = new xmldb_field('warningacknowledged', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0',
            'capturedisabled');
        if (!$dbman->field_exists($run, $field)) {
            $dbman->add_field($run, $field);
        }

        $result = new xmldb_table('idetestfeedback_result');

        // Replaced by sourcecodehash, which groups by the test body rather than the trace.
        $field = new xmldb_field('stacktracehash');
        if ($dbman->field_exists($result, $field)) {
            $dbman->drop_field($result, $field);
        }

        $source = [
            new xmldb_field('sourcekind', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'timecreated'),
            new xmldb_field('sourcefilepath', XMLDB_TYPE_CHAR, '1024', null, null, null, null, 'sourcekind'),
            new xmldb_field('sourcestartline', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sourcefilepath'),
            new xmldb_field('sourceendline', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sourcestartline'),
            new xmldb_field('sourcecode', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sourceendline'),
            new xmldb_field('sourcetruncated', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'sourcecode'),
            new xmldb_field('sourcecodehash', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'sourcetruncated'),
        ];
        foreach ($source as $field) {
            if (!$dbman->field_exists($result, $field)) {
                $dbman->add_field($result, $field);
            }
        }

        $files = new xmldb_table('idetestfeedback_file');
        $files->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $files->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $files->add_field('path', XMLDB_TYPE_CHAR, '1024', null, XMLDB_NOTNULL, null, null);
        $files->add_field('sha256', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $files->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $files->add_field('truncated', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $files->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $files->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $files->add_key('runid_fk', XMLDB_KEY_FOREIGN, ['runid'], 'idetestfeedback_run', ['id']);

        if (!$dbman->table_exists($files)) {
            $dbman->create_table($files);
        }

        upgrade_mod_savepoint(true, 2026091700, 'idetestfeedback');
    }

    return true;
}
