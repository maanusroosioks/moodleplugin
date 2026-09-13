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

use mod_idetestfeedback\local\status;
use renderable;
use renderer_base;
use templatable;

/**
 * The coloured badge showing one test case or run status.
 *
 * Owns the whole status to Bootstrap mapping, badges and table rows alike.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_badge implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param string $status a status as stored on the run or result row
     */
    public function __construct(
        protected readonly string $status
    ) {
    }

    /**
     * The Bootstrap table row class for a status.
     *
     * @param string $status a status as stored on the run or result row
     * @return string the row class, or '' for an unrecognised status
     */
    public static function row_class(string $status): string {
        return match (status::tryFrom($status)) {
            status::PASSED => 'table-success',
            status::FAILED, status::ERROR => 'table-danger',
            status::SKIPPED => 'table-warning',
            null => '',
        };
    }

    /**
     * Exports the badge for mod_idetestfeedback/status_badge.
     *
     * @param renderer_base $output
     * @return array
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $status = status::tryFrom($this->status);

        // ERROR shares the danger background with FAILED and is set apart by a plugin CSS class.
        $classes = match ($status) {
            status::PASSED => 'bg-success',
            status::FAILED, status::ERROR => 'bg-danger',
            status::SKIPPED, null => 'bg-secondary',
        } . ' text-white';

        if ($status === status::ERROR) {
            $classes .= ' idetestfeedback-badge-error';
        }

        return [
            'label' => $this->status,
            'classes' => $classes,
        ];
    }
}
