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

use stdClass;

/**
 * Writes the per test case feedback a teacher submitted from the run detail page.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_saver {
    /**
     * Creates the saver.
     */
    public function __construct(
        /** @var repository The activity's database access */
        protected readonly repository $repository
    ) {
    }

    /**
     * Stores the feedback that changed; results the form did not post are left alone.
     *
     * @param int $runid the run being commented on
     * @param array<int, string> $submitted result id => submitted feedback
     * @param int $authorid the teacher writing the feedback
     * @return stdClass[] the results that now carry new feedback, not those cleared
     */
    public function save(int $runid, array $submitted, int $authorid): array {
        $changed = [];

        foreach ($this->repository->get_results($runid) as $result) {
            $resultid = (int) $result->id;

            if (!array_key_exists($resultid, $submitted)) {
                continue;
            }

            $new = trim($submitted[$resultid]);
            if ($new === trim((string) ($result->feedback ?? ''))) {
                continue;
            }

            $this->repository->update_result_feedback($resultid, $new, $authorid);

            if ($new !== '') {
                $result->feedback = $new;
                $changed[] = $result;
            }
        }

        return $changed;
    }
}
