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

namespace mod_idetestfeedback\local;

/**
 * Turns a validated submission into a stored run, its results and its files.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_recorder {
    /** @var int Most bytes of content kept per test file. */
    private const MAX_FILE_BYTES = 524288;

    /** @var array<string, int> Column widths from db/install.xml. */
    private const MAX_LENGTHS = [
        'ide'            => 50,
        'projectname'    => 255,
        'commithash'     => 100,
        'repourl'        => 1024,
        'testsuite'      => 255,
        'testname'       => 1024,
        'sourcefilepath' => 1024,
        'path'           => 1024,
    ];

    /**
     * Creates the recorder.
     */
    public function __construct(
        /** @var repository The activity's database access */
        protected readonly repository $repository
    ) {
    }

    /**
     * Writes the run, its results and its files.
     *
     * @param submission $submission the validated submission
     * @param int $instanceid the activity the run belongs to
     * @param int $userid the student the run is attributed to
     * @return \stdClass the stored run, carrying its new id
     */
    public function record(submission $submission, int $instanceid, int $userid): \stdClass {
        $now = time();
        $counts = status::tally(array_column($submission->results, 'status'));

        $run = new \stdClass();
        $run->idetestfeedbackid   = $instanceid;
        $run->userid              = $userid;
        $run->ide                 = self::clip($submission->ide, 'ide');
        $run->projectname         = self::clip($submission->projectname, 'projectname');
        $run->commithash          = self::clip($submission->commithash, 'commithash');
        $run->repourl             = self::whole_or_null($submission->repourl, 'repourl');
        $run->startedatms         = $submission->startedatms;
        $run->finishedatms        = $submission->finishedatms;
        $run->status              = status::worst($counts)->value;
        $run->passedcount         = $counts[status::PASSED->value];
        $run->failedcount         = $counts[status::FAILED->value];
        $run->skippedcount        = $counts[status::SKIPPED->value];
        $run->errorcount          = $counts[status::ERROR->value];
        $run->timecreated         = $now;
        $run->capturedisabled     = (int) $submission->capturedisabled;

        $run->id = $this->repository->insert_run(
            $run,
            self::build_results($submission->results),
            self::build_files($submission->testfiles)
        );

        return $run;
    }

    /**
     * The result rows to store.
     *
     * @param array[] $submitted the submitted test case results
     * @return \stdClass[]
     */
    private static function build_results(array $submitted): array {
        $results = [];

        foreach ($submitted as $entry) {
            $source = $entry['source'] ?? [];

            $result                  = new \stdClass();
            $result->testsuite       = self::clip($entry['testsuite'], 'testsuite');
            $result->testname        = self::clip($entry['testname'], 'testname');
            $result->status          = $entry['status'];
            $result->durationms      = $entry['durationms'];
            $result->message         = $entry['message'];
            $result->sourcefilepath  = self::clip($source['path'] ?? null, 'sourcefilepath');
            $result->sourcestartline = $source['startline'] ?? null;
            $result->sourceendline   = $source['endline'] ?? null;
            $results[] = $result;
        }

        return $results;
    }

    /**
     * The file rows to store, one per path and in path order; the first body for a path wins.
     *
     * @param array[] $testfiles the submitted test files
     * @return \stdClass[]
     */
    private static function build_files(array $testfiles): array {
        $unique = [];
        foreach ($testfiles as $entry) {
            $path = self::clip($entry['path'], 'path');
            if (!isset($unique[$path])) {
                $unique[$path] = $entry;
            }
        }
        ksort($unique);

        $files = [];
        foreach ($unique as $path => $entry) {
            [$content, $truncated] = self::clip_code($entry['content'], (bool) $entry['truncated']);

            $file              = new \stdClass();
            $file->path        = (string) $path;
            $file->content     = $content;
            $file->truncated   = $truncated;
            $files[] = $file;
        }

        return $files;
    }

    /**
     * Trims a value and clips it to the width of the column it is stored in.
     *
     * @param string|null $value the submitted value
     * @param string $column the key into {@see MAX_LENGTHS}
     * @return string|null null when the value is absent or blank
     */
    private static function clip(?string $value, string $column): ?string {
        $value = trim((string) $value);

        return $value === '' ? null : \core_text::substr($value, 0, self::MAX_LENGTHS[$column]);
    }

    /**
     * Trims a value and drops it if it does not fit its column.
     *
     * @param string|null $value the submitted value
     * @param string $column the key into {@see MAX_LENGTHS}
     * @return string|null null when the value is absent, blank or too long
     */
    private static function whole_or_null(?string $value, string $column): ?string {
        $value = trim((string) $value);

        return $value === '' || \core_text::strlen($value) > self::MAX_LENGTHS[$column] ? null : $value;
    }

    /**
     * Clips captured code to MAX_FILE_BYTES on a character boundary, and on a
     * line boundary where there is one so line ranges still line up.
     *
     * @param string|null $code the submitted code
     * @param bool $truncated whether the IDE already reported it truncated
     * @return array{0:?string,1:bool} [the code to store, whether it is cut short]
     */
    private static function clip_code(?string $code, bool $truncated): array {
        if ($code === null || strlen($code) <= self::MAX_FILE_BYTES) {
            return [$code, $truncated];
        }

        $kept = mb_strcut($code, 0, self::MAX_FILE_BYTES, 'UTF-8');
        $break = strrpos($kept, "\n");

        return [$break ? substr($kept, 0, $break) : $kept, true];
    }
}
