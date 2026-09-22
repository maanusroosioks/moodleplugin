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

namespace mod_idetestfeedback\local;

/**
 * Derives the hashes a run is stored with, from the bytes being stored.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capture {

    /**
     * Rewrites each file's body in its canonical form and hashes it. A body
     * that canonicalises to nothing is left without one.
     *
     * @param \stdClass[] $files rows carrying path and content
     * @return array<string, \stdClass> the same rows, by path, with contenthash set
     */
    public static function canonicalise_files(array $files): array {
        $bypath = [];

        foreach ($files as $file) {
            $content = source_code::canonicalise((string) ($file->content ?? ''));

            $file->content = $content === '' ? null : $content;
            $file->contenthash = $content === '' ? null : source_code::hash($content);
            $bypath[(string) $file->path] = $file;
        }

        return $bypath;
    }

    /**
     * The hash a result is compared by on later runs: the whole body it names,
     * or just the declaration when it located one.
     *
     * @see \mod_idetestfeedback\output\run_detail::test_source()
     * @param \stdClass $result one test case result
     * @param array<string, \stdClass> $files the run's canonicalised files, by path
     * @return string|null null when nothing comparable was captured
     */
    public static function result_hash(\stdClass $result, array $files): ?string {
        $kind = source_kind::of($result);
        if ($kind === source_kind::NONE) {
            return null;
        }

        $file = $files[(string) ($result->sourcefilepath ?? '')] ?? null;
        if ($file === null || ($file->contenthash ?? null) === null) {
            return null;
        }

        if ($kind === source_kind::FILE) {
            return $file->contenthash;
        }

        [$excerpt, $truncated] = source_code::excerpt(
            (string) $file->content,
            (int) $result->sourcestartline,
            (int) $result->sourceendline
        );

        // A declaration the file stopped short of rehashes whenever the cut moves.
        if ($truncated || trim($excerpt) === '') {
            return null;
        }

        return source_code::hash($excerpt);
    }
}
