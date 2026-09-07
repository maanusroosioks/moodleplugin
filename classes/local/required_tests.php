<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_idetestfeedback\local;

defined('MOODLE_INTERNAL') || die();

class required_tests {

    private const QUALIFIER = '#';

    public static function normalize(?string $raw): string {
        return implode("\n", self::parse($raw));
    }

    /**
     * @return string[] the distinct, trimmed entries from $raw, in input order
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
            $key = \core_text::strtolower($line);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $entries[] = $line;
            }
        }

        return $entries;
    }

    /**
     * Scores reported results against the defined entries.
     *
     * Each entry lands in exactly one bucket, so
     * passed + failed + skipped + missing === total:
     *  - failed:  at least one matching result is FAILED or ERROR;
     *  - passed:  otherwise, at least one matching result is PASSED;
     *  - skipped: otherwise, the only matching results are SKIPPED;
     *  - missing: no reported result matches the entry.
     *
     * @param string[] $entries from {@see parse()}
     * @param array $results the run's result rows, each with 'testname', an
     *        optional 'testsuite', and 'status'
     * @return array{total:int,passed:int,failed:int,skipped:int,missing:int}
     */
    public static function evaluate(array $entries, array $results): array {
        $tally = ['total' => count($entries), 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'missing' => 0];

        $reported = [];
        foreach ($results as $r) {
            $reported[] = [
                'name'   => \core_text::strtolower(trim((string) ($r['testname'] ?? ''))),
                'suite'  => \core_text::strtolower(trim((string) ($r['testsuite'] ?? ''))),
                'status' => $r['status'] ?? '',
            ];
        }

        foreach ($entries as $entry) {
            [$suite, $name] = self::split_entry($entry);
            $name  = \core_text::strtolower($name);
            $suite = $suite === null ? null : \core_text::strtolower($suite);

            $statuses = [];
            foreach ($reported as $r) {
                if ($r['name'] !== $name) {
                    continue;
                }
                if ($suite !== null && $r['suite'] !== $suite) {
                    continue;
                }
                $statuses[] = $r['status'];
            }

            if (!$statuses) {
                $tally['missing']++;
            } else if (array_intersect(['FAILED', 'ERROR'], $statuses)) {
                $tally['failed']++;
            } else if (in_array('PASSED', $statuses, true)) {
                $tally['passed']++;
            } else {
                $tally['skipped']++;
            }
        }

        return $tally;
    }

    /**
     * @return array{0:?string,1:string} [suite|null, name] for an entry
     */
    private static function split_entry(string $entry): array {
        $pos = strrpos($entry, self::QUALIFIER);
        if ($pos === false) {
            return [null, $entry];
        }

        $suite = trim(substr($entry, 0, $pos));
        $name  = trim(substr($entry, $pos + 1));

        return [$suite === '' ? null : $suite, $name];
    }
}
