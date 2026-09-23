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

namespace mod_idetestfeedback\output;

/**
 * Exposes the protected page slice.
 */
class testable_run_detail_paging extends run_detail {

    /**
     * @return \stdClass[] the results on the current page
     */
    public function page(): array {
        return $this->page_results();
    }
}

/**
 * Tests for how the run detail pages its results.
 *
 * @package    mod_idetestfeedback
 * @category   test
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_idetestfeedback\output\run_detail
 */
final class run_detail_paging_test extends \advanced_testcase {

    /**
     * @param int $page zero based page number
     * @param int $perpage results per page
     * @return int[] the ids of the results on that page, out of five
     */
    private function ids(int $page, int $perpage): array {
        $results = array_map(fn(int $id) => (object) ['id' => $id], range(1, 5));

        $detail = new testable_run_detail_paging(
            (object) ['requiredtests' => null],
            (object) ['id' => 1],
            $results,
            [],
            null,
            \context_system::instance(),
            1,
            false,
            new \mod_idetestfeedback\local\source_history([], [], 0),
            $page,
            $perpage
        );

        return array_map(fn(\stdClass $result) => $result->id, $detail->page());
    }

    public function test_the_first_page_holds_the_first_results(): void {
        $this->assertSame([1, 2], $this->ids(0, 2));
    }

    public function test_the_last_page_holds_what_is_left(): void {
        $this->assertSame([5], $this->ids(2, 2));
    }

    public function test_no_page_size_shows_every_result(): void {
        $this->assertSame([1, 2, 3, 4, 5], $this->ids(0, 0));
    }
}
