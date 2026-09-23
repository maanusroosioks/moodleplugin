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

    if ($oldversion < 2026092200) {
        $blobs = new xmldb_table('idetestfeedback_blob');
        $blobs->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $blobs->add_field('idetestfeedbackid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $blobs->add_field('contenthash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $blobs->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $blobs->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $blobs->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $blobs->add_key('idetestfeedbackid_fk', XMLDB_KEY_FOREIGN, ['idetestfeedbackid'], 'idetestfeedback', ['id']);
        $blobs->add_index('instance_hash_idx', XMLDB_INDEX_UNIQUE, ['idetestfeedbackid', 'contenthash']);

        if (!$dbman->table_exists($blobs)) {
            $dbman->create_table($blobs);
        }

        $files = new xmldb_table('idetestfeedback_file');
        $field = new xmldb_field('blobid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'path');
        if (!$dbman->field_exists($files, $field)) {
            $dbman->add_field($files, $field);
            $dbman->add_key($files, new xmldb_key('blobid_fk', XMLDB_KEY_FOREIGN, ['blobid'],
                'idetestfeedback_blob', ['id']));
        }

        // Neither the hash nor the canonical form is portable SQL, so this one
        // migration reads its rows in PHP rather than updating them in place.
        upgrade_set_timeout(3600);

        $now = time();
        $seen = [];
        $caching = 0;
        $rs = $DB->get_recordset_sql(
            "SELECT f.id, f.content, r.idetestfeedbackid
               FROM {idetestfeedback_file} f
               JOIN {idetestfeedback_run} r ON r.id = f.runid
              WHERE f.content IS NOT NULL
           ORDER BY r.idetestfeedbackid ASC, f.id ASC"
        );

        foreach ($rs as $file) {
            $content = \mod_idetestfeedback\local\source_code::canonicalise((string) $file->content);
            if ($content === '') {
                continue;
            }

            $instanceid = (int) $file->idetestfeedbackid;
            $hash = \mod_idetestfeedback\local\source_code::hash($content);

            // Rows arrive grouped by activity, so only one activity's hashes are
            // worth holding.
            if ($caching !== $instanceid) {
                $seen = [];
                $caching = $instanceid;
            }

            if (!isset($seen[$hash])) {
                $existing = $DB->get_field('idetestfeedback_blob', 'id',
                    ['idetestfeedbackid' => $instanceid, 'contenthash' => $hash]);

                $seen[$hash] = $existing ?: $DB->insert_record('idetestfeedback_blob', (object) [
                    'idetestfeedbackid' => $instanceid,
                    'contenthash'       => $hash,
                    'content'           => $content,
                    'timecreated'       => $now,
                ]);
            }

            $DB->set_field('idetestfeedback_file', 'blobid', $seen[$hash], ['id' => $file->id]);
        }
        $rs->close();

        // The stored hashes were normalised by the IDE under rules this cannot
        // reproduce, so they are rebuilt rather than left to read as changes.
        $DB->execute("UPDATE {idetestfeedback_result} SET sourcecodehash = NULL");

        $rs = $DB->get_recordset_sql(
            "SELECT res.id, res.sourcekind, res.sourcefilepath, res.sourcestartline,
                    res.sourceendline, res.sourcecode, res.runid
               FROM {idetestfeedback_result} res
              WHERE res.sourcekind IS NOT NULL
                AND res.sourcekind <> :none
           ORDER BY res.runid ASC, res.id ASC",
            ['none' => \mod_idetestfeedback\local\source_kind::NONE->value]
        );

        foreach ($rs as $row) {
            $path = (string) ($row->sourcefilepath ?? '');
            $bodies = [];

            if ($path !== '') {
                $body = $DB->get_record_sql(
                    "SELECT b.contenthash, b.content
                       FROM {idetestfeedback_file} f
                       JOIN {idetestfeedback_blob} b ON b.id = f.blobid
                      WHERE f.runid = :runid AND f.path = :path",
                    ['runid' => $row->runid, 'path' => $path],
                    IGNORE_MULTIPLE
                );

                if ($body) {
                    $bodies[$path] = $body;
                }
            }

            $hash = \mod_idetestfeedback\local\capture::result_hash($row, $bodies);
            if ($hash !== null) {
                $DB->set_field('idetestfeedback_result', 'sourcecodehash', $hash, ['id' => $row->id]);
            }
        }
        $rs->close();

        foreach (['sha256', 'content'] as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($files, $field)) {
                $dbman->drop_field($files, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026092200, 'idetestfeedback');
    }

    if ($oldversion < 2026092201) {
        $result = new xmldb_table('idetestfeedback_result');

        // A test's code now travels only in the run's files, and what a result
        // names says which part of one it points at, so neither the body nor the
        // kind is stored beside it any more.
        foreach (['sourcecode', 'sourcetruncated', 'sourcekind'] as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($result, $field)) {
                $dbman->drop_field($result, $field);
            }
        }

        // Hashes taken from a body that lived on the result cannot be derived
        // again now that only the files hold one, so they are rebuilt under the
        // rule that survives; a test the files cannot carry keeps none.
        upgrade_set_timeout(3600);
        $DB->execute("UPDATE {idetestfeedback_result} SET sourcecodehash = NULL");

        $rs = $DB->get_recordset_sql(
            "SELECT res.id, res.runid, res.sourcefilepath, res.sourcestartline, res.sourceendline
               FROM {idetestfeedback_result} res
              WHERE res.sourcefilepath IS NOT NULL
           ORDER BY res.runid ASC, res.id ASC"
        );

        foreach ($rs as $row) {
            $path = (string) $row->sourcefilepath;
            $body = $DB->get_record_sql(
                "SELECT b.contenthash, b.content
                   FROM {idetestfeedback_file} f
                   JOIN {idetestfeedback_blob} b ON b.id = f.blobid
                  WHERE f.runid = :runid AND f.path = :path",
                ['runid' => $row->runid, 'path' => $path],
                IGNORE_MULTIPLE
            );

            $hash = $body
                ? \mod_idetestfeedback\local\capture::result_hash($row, [$path => $body])
                : null;

            if ($hash !== null) {
                $DB->set_field('idetestfeedback_result', 'sourcecodehash', $hash, ['id' => $row->id]);
            }
        }
        $rs->close();

        upgrade_mod_savepoint(true, 2026092201, 'idetestfeedback');
    }

    return true;
}
