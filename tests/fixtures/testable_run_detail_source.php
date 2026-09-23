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

/**
 * Exposes the protected source block so one result can be exported on its own.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_run_detail_source extends run_detail {
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
     * Exposes run_duration().
     *
     * @param int $ms a run's duration in milliseconds
     * @return string the duration as shown
     */
    public static function duration(int $ms): string {
        return self::run_duration($ms);
    }
}
