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
     * @param repository $repository the activity's database access
     */
    public function __construct(protected readonly repository $repository) {
    }

    /**
     * Stores the feedback that actually changed, ignoring results the form did
     * not post so an absent textarea keeps whatever it already had.
     *
     * @param int $runid the run being commented on
     * @param array<int, string> $submitted result id => submitted feedback
     * @param int $byuserid the teacher writing the feedback
     * @return stdClass[] the results that now carry new feedback; cleared
     *         feedback is saved but left out, because there is nothing to announce
     */
    public function save(int $runid, array $submitted, int $byuserid): array {
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

            $this->repository->update_result_feedback($resultid, $new, FORMAT_PLAIN, $byuserid);

            if ($new !== '') {
                $result->feedback = $new;
                $changed[] = $result;
            }
        }

        return $changed;
    }
}
