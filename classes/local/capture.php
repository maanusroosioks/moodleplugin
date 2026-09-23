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
 * Derives the file hashes a run is stored with, from the bytes being stored.
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
}
