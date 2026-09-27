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

namespace mod_idetestfeedback\output;

use core\url;
use mod_idetestfeedback\local\feedback_history;

/**
 * Exposes the run detail's protected builders so they can be tested on their own.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_run_detail extends run_detail {
    /**
     * Builds a run detail with empty defaults for every argument not given.
     *
     * @param mixed ...$args named constructor arguments
     * @return self
     */
    public static function create(mixed ...$args): self {
        return new self(...$args + [
            'run' => (object) [],
            'results' => [],
            'files' => [],
            'studentname' => null,
            'context' => \context_system::instance(),
            'cancomment' => false,
            'history' => new feedback_history([]),
            'backurl' => new url('/'),
            'runurl' => new url('/'),
            'page' => 0,
            'perpage' => 50,
            'pagingbar' => '',
        ]);
    }

    /**
     * Exposes language_of().
     *
     * @param string $path the file the code came from
     * @return string the Prism language name
     */
    public static function language(string $path): string {
        return self::language_of($path);
    }

    /**
     * Exposes run_duration().
     *
     * @param int $ms a run's duration in milliseconds
     * @return string the duration as shown
     */
    public static function duration(int $ms): string {
        return self::run_duration($ms);
    }

    /**
     * Exposes page_results().
     *
     * @return \stdClass[] the results on the current page
     */
    public function page(): array {
        return $this->page_results();
    }

    /**
     * Exposes feedback_badges().
     *
     * @param \stdClass $result one test case result
     * @return array[] the exported badges
     */
    public function badges(\stdClass $result): array {
        return $this->feedback_badges($result);
    }

    /**
     * Exposes source_block().
     *
     * @param \stdClass $result one test case result
     * @return array|null the exported source block
     */
    public function block(\stdClass $result): ?array {
        return $this->source_block($result);
    }

    /**
     * Exposes file_rows().
     *
     * @return array[] the exported file rows
     */
    public function files(): array {
        return $this->file_rows();
    }
}
