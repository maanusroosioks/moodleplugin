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
 * Compares a run's test case results against the same tests on earlier runs.
 *
 * One caveat bounds every verdict: a TEST hash covers the declaration alone,
 * not the helpers it calls nor the code under test, so an unchanged body does
 * not mean nothing was edited.
 *
 * Two runs holding one blob captured identical bytes, the blob table being
 * unique on (activity, hash).
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_history {
    /** @var int How many of the student's earlier runs are compared against. */
    public const LOOKBACK_RUNS = 20;

    /** @var string Separates the suite from the name in a lookup key. */
    private const QUALIFIER = '#';

    /** @var array<string, \stdClass> The most recent earlier occurrence of each test. */
    protected array $lastrun = [];

    /** @var array<string, \stdClass> The most recent earlier occurrence carrying usable feedback. */
    protected array $lastfeedback = [];

    /** @var array<int, array<string, int>> The body each earlier run captured at each path. */
    protected array $fileblobs = [];

    /**
     * Indexes the earlier runs for lookup.
     *
     * @param \stdClass[] $priorresults earlier result rows, newest run first
     * @param \stdClass[] $priorfiles the file rows of those same runs
     * @param int $runtimecreated when the run being shown was submitted
     */
    public function __construct(array $priorresults, array $priorfiles, int $runtimecreated) {
        foreach ($priorresults as $row) {
            $key = self::key($row->testsuite ?? null, (string) ($row->testname ?? ''));

            if (!isset($this->lastrun[$key])) {
                $this->lastrun[$key] = $row;
            }

            // Feedback written after this run was submitted cannot be what the
            // student was answering, so it is not treated as an anchor.
            if (
                !isset($this->lastfeedback[$key])
                    && trim((string) ($row->feedback ?? '')) !== ''
                    && (int) ($row->feedbackmodified ?? 0) < $runtimecreated
            ) {
                $this->lastfeedback[$key] = $row;
            }
        }

        foreach ($priorfiles as $file) {
            $this->fileblobs[(int) $file->runid][(string) $file->path] = (int) ($file->blobid ?? 0);
        }
    }

    /**
     * How a test's code changed since the last run that reported it.
     *
     * @param \stdClass $result one test case result of the run being shown
     * @return source_change against the last earlier run that reported this test
     */
    public function since_last_run(\stdClass $result): source_change {
        return self::compare($result, $this->anchor($this->lastrun, $result));
    }

    /**
     * How a test's code changed since a teacher last commented on it.
     *
     * @param \stdClass $result one test case result of the run being shown
     * @return source_change against the occurrence a teacher last commented on
     */
    public function since_feedback(\stdClass $result): source_change {
        if (trim((string) ($result->feedback ?? '')) !== '') {
            return source_change::UNKNOWN;
        }

        return self::compare($result, $this->anchor($this->lastfeedback, $result));
    }

    /**
     * Whether the whole file a test lives in has moved since it was commented on.
     *
     * Widens what an unchanged TEST body can be said to cover, since that hash
     * stops at the declaration.
     *
     * @param \stdClass $result one test case result of the run being shown
     * @param int|null $currentblobid the body this run captured for that file
     * @return source_change
     */
    public function file_since_feedback(\stdClass $result, ?int $currentblobid): source_change {
        $anchor = $this->anchor($this->lastfeedback, $result);
        if ($anchor === null) {
            return source_change::UNKNOWN;
        }

        $path = (string) ($anchor->sourcefilepath ?? '');
        if ($path === '') {
            return source_change::UNKNOWN;
        }

        $now = (int) $currentblobid;
        $then = $this->fileblobs[(int) $anchor->runid][$path] ?? 0;
        if ($now === 0 || $then === 0) {
            return source_change::UNKNOWN;
        }

        return $now === $then ? source_change::UNCHANGED : source_change::CHANGED;
    }

    /**
     * The earlier occurrence of a result in an index.
     *
     * @param array<string, \stdClass> $index one of the folded lookups
     * @param \stdClass $result one test case result of the run being shown
     * @return \stdClass|null the earlier occurrence, if this index holds one
     */
    protected function anchor(array $index, \stdClass $result): ?\stdClass {
        return $index[self::key($result->testsuite ?? null, (string) ($result->testname ?? ''))] ?? null;
    }

    /**
     * Compares a result's code hash with an earlier occurrence.
     *
     * @param \stdClass $current a result of the run being shown
     * @param \stdClass|null $earlier the occurrence to compare it against
     * @return source_change
     */
    protected static function compare(\stdClass $current, ?\stdClass $earlier): source_change {
        if ($earlier === null) {
            return source_change::UNKNOWN;
        }

        // A TEST hash and a FILE hash describe different things, so a result
        // whose kind moved between runs says nothing about the code.
        $kind = source_kind::of($current);
        if ($kind === source_kind::NONE || $kind !== source_kind::of($earlier)) {
            return source_change::UNKNOWN;
        }

        $now = self::normalise($current->sourcecodehash ?? null);
        $then = self::normalise($earlier->sourcecodehash ?? null);
        if ($now === '' || $then === '') {
            return source_change::UNKNOWN;
        }

        return $now === $then ? source_change::UNCHANGED : source_change::CHANGED;
    }

    /**
     * Normalises a stored hash for comparison.
     *
     * @param string|null $hash a stored hash
     * @return string the form hashes are compared in
     */
    protected static function normalise(?string $hash): string {
        return \core_text::strtolower(trim((string) $hash));
    }

    /**
     * The key a test is indexed under.
     *
     * @param string|null $testsuite the suite the IDE reported, if any
     * @param string $testname the test name the IDE reported
     * @return string the key an occurrence of this test is held under
     */
    protected static function key(?string $testsuite, string $testname): string {
        return \core_text::strtolower(trim((string) $testsuite))
            . self::QUALIFIER
            . \core_text::strtolower(trim($testname));
    }
}
