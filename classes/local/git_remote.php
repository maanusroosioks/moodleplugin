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
 * Cleans git remote URLs and turns them into web links.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class git_remote {
    /** @var int Characters of a commit hash shown where space is short. */
    public const SHORT_HASH_LENGTH = 7;

    /**
     * Drops the user info from a remote URL, so a token embedded in it is never stored.
     *
     * @param string|null $url the remote as the IDE reported it
     * @return string|null the remote without credentials, or null when blank
     */
    public static function strip_credentials(?string $url): ?string {
        if ($url === null || trim($url) === '') {
            return null;
        }

        return preg_replace('~^([a-z][a-z0-9+.-]*://)[^/@]*@~i', '$1', trim($url));
    }

    /**
     * The web page of a repository hosted on a web forge.
     *
     * @param string|null $url a remote in http(s), ssh://, git:// or scp-like user@host:path form
     * @return string|null the page, or null when the remote cannot be browsed
     */
    public static function web_url(?string $url): ?string {
        $url = self::strip_credentials($url);
        if ($url === null) {
            return null;
        }

        if (preg_match('~^(https?)://([^/]+)/(.+)$~i', $url, $m)) {
            [, $scheme, $host, $path] = $m;
        } else if (preg_match('~^(?:ssh|git)://([^/:]+)(?::\d+)?/(.+)$~i', $url, $m)) {
            [, $host, $path] = $m;
            $scheme = 'https';
        } else if (preg_match('~^[^@/\s]+@([^:/\s]+):(?!/)(.+)$~', $url, $m)) {
            [, $host, $path] = $m;
            $scheme = 'https';
        } else {
            return null;
        }

        $path = preg_replace('~(\.git)?/*$~i', '', $path);
        if ($path === '' || preg_match('~[\s?#"<>]~', $host . $path)) {
            return null;
        }

        return strtolower($scheme) . '://' . strtolower($host) . '/' . $path;
    }

    /**
     * The web page of one commit in a repository.
     *
     * @param string|null $url the repository remote
     * @param string|null $commithash the commit
     * @return string|null the page, or null when the remote or hash cannot make one
     */
    public static function commit_url(?string $url, ?string $commithash): ?string {
        $web = self::web_url($url);
        if ($web === null || !preg_match('~^[0-9a-f]{7,64}$~i', (string) $commithash)) {
            return null;
        }

        return $web . '/commit/' . $commithash;
    }

    /**
     * The abbreviated form of a commit hash.
     *
     * @param string $commithash the full hash
     * @return string
     */
    public static function short_hash(string $commithash): string {
        return \core_text::substr($commithash, 0, self::SHORT_HASH_LENGTH);
    }
}
