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
 * Canonicalises, hashes and excerpts the source code an IDE captured with a run.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_code {
    /** @var string Matches the marker line an IDE appends to a string it cut short. */
    private const MARKER = '/\n?\x{2026} \[truncated[^\]\n]*\]\s*$/u';

    /**
     * Converts line endings to LF and removes the trailing truncation marker.
     *
     * @param string $code source as it was received
     * @return string the source without the marker; only line endings change if it is not valid UTF-8
     */
    private static function strip_marker(string $code): string {
        $code = str_replace(["\r\n", "\r"], "\n", $code);

        return preg_replace(self::MARKER, '', $code) ?? $code;
    }

    /**
     * Normalises source for hashing: drops the truncation marker and byte order mark,
     * converts line endings to LF, and strips trailing whitespace and trailing blank lines.
     *
     * @param string $code source as it was received
     * @return string the same source in the form it is hashed in
     */
    public static function canonicalise(string $code): string {
        $code = (string) preg_replace('/^\xEF\xBB\xBF/', '', self::strip_marker($code));
        $lines = array_map('rtrim', explode("\n", $code));

        return rtrim(implode("\n", $lines), "\n");
    }

    /**
     * Hashes source that is already canonical, as it is stored.
     *
     * @param string $canonical source as canonicalise() returned it
     * @return string the sha256 of exactly those bytes
     */
    public static function hash_canonical(string $canonical): string {
        return \hash('sha256', $canonical);
    }

    /**
     * Cuts a test's declaration out of the file it was found in.
     *
     * Line numbers are 1-based and inclusive, and hold for whatever a truncated
     * file retained, since a file is only ever cut from the bottom.
     *
     * @param string $content the file's contents, as canonicalise() leaves them
     * @param int $startline the declaration's first line
     * @param int $endline the declaration's last line
     * @return array{0:string,1:bool} [the excerpt, whether the file ended before $endline]
     */
    public static function excerpt(string $content, int $startline, int $endline): array {
        $lines = explode("\n", $content);
        $first = max(1, $startline);

        if ($first > count($lines)) {
            return ['', true];
        }

        $excerpt = array_slice($lines, $first - 1, max(1, $endline - $first + 1));

        return [implode("\n", $excerpt), $endline > count($lines)];
    }
}
