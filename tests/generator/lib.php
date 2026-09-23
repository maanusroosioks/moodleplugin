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
 * Test data generator for mod_idetestfeedback.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_idetestfeedback\local\repository;
use mod_idetestfeedback\local\status;

/**
 * Test data generator for mod_idetestfeedback.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_idetestfeedback_generator extends testing_module_generator {
    /**
     * Stores a run and its results, the way the web service would.
     *
     * Hashes are derived from the content, so a record cannot assert one.
     *
     * @param array $record 'idetestfeedbackid', 'userid' and 'results', where each
     *        result is ['testname' => …, 'status' => …, 'testsuite' => …]; optional
     *        'files', each ['path' => …, 'content' => …]
     * @return stdClass the stored run, carrying its new id
     */
    public function create_run(array $record): stdClass {
        global $DB;

        $now = $record['timecreated'] ?? time();
        $counts = array_fill_keys(array_column(status::cases(), 'value'), 0);

        $results = [];
        foreach ($record['results'] as $r) {
            $result = (object) [
                'testsuite' => $r['testsuite'] ?? null,
                'testname' => $r['testname'],
                'status' => $r['status'],
                'durationms' => $r['durationms'] ?? null,
                'message' => $r['message'] ?? null,
                'timecreated' => $now,
                'feedback' => null,
                'feedbackformat' => 0,
                'feedbackby' => null,
                'feedbackmodified' => null,
                'sourcefilepath' => $r['sourcefilepath'] ?? null,
                'sourcestartline' => $r['sourcestartline'] ?? null,
                'sourceendline' => $r['sourceendline'] ?? null,
            ];
            $counts[$result->status]++;
            $results[] = $result;
        }

        $run = (object) [
            'idetestfeedbackid' => $record['idetestfeedbackid'],
            'userid' => $record['userid'],
            'ide' => $record['ide'] ?? 'VSCODE',
            'projectname' => $record['projectname'] ?? null,
            'commithash' => $record['commithash'] ?? null,
            'startedat' => $record['startedat'] ?? null,
            'finishedat' => $record['finishedat'] ?? null,
            'status' => $record['status'] ?? self::worst_status($counts),
            'passedcount' => $counts[status::PASSED->value],
            'failedcount' => $counts[status::FAILED->value],
            'skippedcount' => $counts[status::SKIPPED->value],
            'errorcount' => $counts[status::ERROR->value],
            'timecreated' => $now,
            'capturedisabled' => (int) ($record['capturedisabled'] ?? 0),
        ];

        $files = [];
        foreach ($record['files'] ?? [] as $f) {
            $files[] = (object) [
                'path' => $f['path'],
                'content' => $f['content'] ?? null,
                'truncated' => (int) ($f['truncated'] ?? 0),
                'timecreated' => $now,
            ];
        }

        $run->id = (new repository($DB))->insert_run_with_results($run, $results, $files);

        return $run;
    }

    /**
     * The overall run status for a set of result counts.
     *
     * @param array<string, int> $counts result count per status
     * @return string the worst outcome any test reported
     */
    private static function worst_status(array $counts): string {
        if ($counts[status::ERROR->value] > 0) {
            return status::ERROR->value;
        }
        if ($counts[status::FAILED->value] > 0) {
            return status::FAILED->value;
        }
        if ($counts[status::PASSED->value] > 0) {
            return status::PASSED->value;
        }

        return status::SKIPPED->value;
    }
}
