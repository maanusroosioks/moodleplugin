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
 * Parses and scores the list of test cases a teacher says count.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class required_tests {
    /** @var string Separates a suite from a test name in an entry. */
    private const QUALIFIER = '#';

    /**
     * Rewrites a teacher's list in canonical form.
     *
     * @param string|null $raw the teacher's list as typed
     * @return string the canonical list, one entry per line
     */
    public static function normalize(?string $raw): string {
        return implode("\n", self::parse($raw));
    }

    /**
     * Parses the teacher's list into canonical `name` / `suite#name` entries.
     *
     * @param string|null $raw the teacher's list as typed
     * @return string[] the distinct canonical entries, in input order
     */
    public static function parse(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $entries = [];
        $seen = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$suite, $name] = self::split_entry($line);
            if ($name === '') {
                continue;
            }
            $canonical = $suite === null ? $name : $suite . self::QUALIFIER . $name;

            $key = \core_text::strtolower($canonical);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $entries[] = $canonical;
            }
        }

        return $entries;
    }

    /**
     * Scores reported results against the defined entries.
     *
     * @param string[] $entries from {@see parse()}
     * @param array $results the run's result rows (associative arrays or objects),
     *        each with 'testname', an optional 'testsuite', and 'status'
     * @return array{total:int,passed:int,failed:int,skipped:int,missing:int}
     */
    public static function evaluate(array $entries, array $results): array {
        $tally = ['total' => count($entries), 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'missing' => 0];
        $reported = self::index_results($results);

        foreach ($entries as $entry) {
            [$suite, $name] = self::split_entry($entry);
            $name = \core_text::strtolower($name);
            $suite = $suite === null ? null : \core_text::strtolower($suite);

            $statuses = [];
            foreach ($reported[$name] ?? [] as $result) {
                if ($suite === null || $result['suite'] === $suite) {
                    $statuses[] = $result['status'];
                }
            }

            if (!$statuses) {
                $tally['missing']++;
            } else if (array_intersect([status::FAILED->value, status::ERROR->value], $statuses)) {
                $tally['failed']++;
            } else if (in_array(status::PASSED->value, $statuses, true)) {
                $tally['passed']++;
            } else {
                $tally['skipped']++;
            }
        }

        return $tally;
    }

    /**
     * Builds the lookup {@see is_required()} reads, so a run's results can be
     * flagged in one pass instead of rescanning the entries for every row.
     *
     * @param string[] $entries from {@see parse()}
     * @return array{names:array<string,true>,qualified:array<string,true>} an opaque lookup
     */
    public static function index_entries(array $entries): array {
        $index = ['names' => [], 'qualified' => []];

        foreach ($entries as $entry) {
            [$suite, $name] = self::split_entry($entry);
            $name = \core_text::strtolower($name);

            if ($suite === null) {
                $index['names'][$name] = true;
            } else {
                $index['qualified'][\core_text::strtolower($suite) . self::QUALIFIER . $name] = true;
            }
        }

        return $index;
    }

    /**
     * Whether a single reported test is covered by one of the defined entries.
     *
     * @param array $index from {@see index_entries()}
     * @param string|null $testsuite the suite the IDE reported, if any
     * @param string $testname the test name the IDE reported
     * @return bool
     */
    public static function is_required(array $index, ?string $testsuite, string $testname): bool {
        $name = \core_text::strtolower(trim($testname));
        $suite = \core_text::strtolower(trim((string) $testsuite));

        return isset($index['names'][$name])
            || isset($index['qualified'][$suite . self::QUALIFIER . $name]);
    }

    /**
     * Groups the reported results by lowercased test name.
     *
     * @param array $results the run's result rows
     * @return array<string, array{suite:string,status:string}[]>
     */
    private static function index_results(array $results): array {
        $reported = [];

        foreach ($results as $result) {
            $result = (array) $result;
            $name = \core_text::strtolower(trim((string) ($result['testname'] ?? '')));

            $reported[$name][] = [
                'suite' => \core_text::strtolower(trim((string) ($result['testsuite'] ?? ''))),
                'status' => (string) ($result['status'] ?? ''),
            ];
        }

        return $reported;
    }

    /**
     * Splits an entry into its suite and test name.
     *
     * @param string $entry a canonical entry
     * @return array{0:?string,1:string} [suite|null, name] for an entry
     */
    private static function split_entry(string $entry): array {
        $pos = strrpos($entry, self::QUALIFIER);
        if ($pos === false) {
            return [null, $entry];
        }

        $suite = trim(substr($entry, 0, $pos));
        $name = trim(substr($entry, $pos + 1));

        return [$suite === '' ? null : $suite, $name];
    }
}
