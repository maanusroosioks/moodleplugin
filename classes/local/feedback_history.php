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
 * Compares a run's test case results against the status each test had when a
 * teacher last commented on it.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_history {
    /** @var string Separates the suite from the name in a lookup key. */
    private const QUALIFIER = '#';

    /** @var array<string, \stdClass> The most recent earlier occurrence of each test that carries feedback. */
    protected array $lastfeedback = [];

    /**
     * Indexes the commented results for lookup.
     *
     * @param \stdClass[] $commented earlier results carrying feedback, newest run first
     */
    public function __construct(array $commented) {
        foreach ($commented as $row) {
            $this->lastfeedback[self::key($row->testsuite ?? null, (string) ($row->testname ?? ''))] ??= $row;
        }
    }

    /**
     * How a test's status moved since a teacher last commented on it.
     *
     * @param \stdClass $result one test case result of the run being shown
     * @return feedback_outcome|null null when there is nothing to report, or the result carries feedback of its own
     */
    public function outcome(\stdClass $result): ?feedback_outcome {
        if (trim((string) ($result->feedback ?? '')) !== '') {
            return null;
        }

        $anchor = $this->lastfeedback[self::key($result->testsuite ?? null, (string) ($result->testname ?? ''))] ?? null;
        $then = status::tryFrom((string) ($anchor->status ?? ''));
        $now = status::tryFrom((string) ($result->status ?? ''));

        return $then === null || $now === null ? null : feedback_outcome::between($then, $now);
    }

    /**
     * The key a test is indexed under.
     *
     * @param string|null $testsuite the suite the IDE reported, if any
     * @param string $testname the test name the IDE reported
     * @return string
     */
    protected static function key(?string $testsuite, string $testname): string {
        return \core_text::strtolower(trim((string) $testsuite))
            . self::QUALIFIER
            . \core_text::strtolower(trim($testname));
    }
}
