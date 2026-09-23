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
 * Tests for git remote cleaning and linking.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_idetestfeedback\local\git_remote::class)]
final class git_remote_test extends \basic_testcase {
    public function test_a_token_in_an_https_remote_is_dropped(): void {
        $this->assertSame(
            'https://github.com/ada/calc.git',
            git_remote::strip_credentials('https://ada:ghp_secret@github.com/ada/calc.git')
        );
        $this->assertSame(
            'https://github.com/ada/calc.git',
            git_remote::strip_credentials('https://ghp_secret@github.com/ada/calc.git')
        );
    }

    public function test_a_remote_without_credentials_is_kept(): void {
        $this->assertSame('git@github.com:ada/calc.git', git_remote::strip_credentials('  git@github.com:ada/calc.git '));
        $this->assertSame('https://github.com/ada/calc', git_remote::strip_credentials('https://github.com/ada/calc'));
    }

    public function test_a_blank_remote_is_null(): void {
        $this->assertNull(git_remote::strip_credentials(null));
        $this->assertNull(git_remote::strip_credentials('   '));
    }

    /**
     * Remotes and the web page each browses to.
     *
     * @return array
     */
    public static function remotes(): array {
        return [
            'https'           => ['https://github.com/ada/calc.git', 'https://github.com/ada/calc'],
            'https no suffix' => ['https://gitlab.com/group/sub/calc/', 'https://gitlab.com/group/sub/calc'],
            'https token'     => ['https://x:tok@github.com/ada/calc.git', 'https://github.com/ada/calc'],
            'http'            => ['http://git.example.edu/ada/calc', 'http://git.example.edu/ada/calc'],
            'scp-like'        => ['git@github.com:ada/calc.git', 'https://github.com/ada/calc'],
            'ssh url'         => ['ssh://git@GitLab.com:2222/ada/calc.git', 'https://gitlab.com/ada/calc'],
            'local path'      => ['/home/ada/calc', null],
            'file url'        => ['file:///home/ada/calc', null],
            'no path'         => ['https://github.com/', null],
            'garbage'         => ['not a remote', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('remotes')]
    public function test_web_url(string $remote, ?string $expected): void {
        $this->assertSame($expected, git_remote::web_url($remote));
    }

    public function test_commit_url_joins_the_page_and_the_hash(): void {
        $this->assertSame(
            'https://github.com/ada/calc/commit/a1b2c3d4',
            git_remote::commit_url('git@github.com:ada/calc.git', 'a1b2c3d4')
        );
    }

    public function test_commit_url_needs_a_hex_hash_and_a_browsable_remote(): void {
        $this->assertNull(git_remote::commit_url('https://github.com/ada/calc', null));
        $this->assertNull(git_remote::commit_url('https://github.com/ada/calc', 'a1b2'));
        $this->assertNull(git_remote::commit_url('https://github.com/ada/calc', '../../evil'));
        $this->assertNull(git_remote::commit_url('/home/ada/calc', 'a1b2c3d4'));
        $this->assertNull(git_remote::commit_url(null, 'a1b2c3d4'));
    }

    public function test_short_hash(): void {
        $this->assertSame('a1b2c3d', git_remote::short_hash('a1b2c3d4e5f6'));
        $this->assertSame('abc', git_remote::short_hash('abc'));
    }
}
