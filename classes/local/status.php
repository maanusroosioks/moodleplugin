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
 * The statuses a test case result, and a run as a whole, can carry.
 *
 * Reported by the IDE and stored verbatim, so they are not translated. Values
 * read back from the database are plain strings; resolve them with tryFrom(),
 * which gives null for anything an older version or a manual edit left behind.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum status: string {
    case PASSED = 'PASSED';
    case FAILED = 'FAILED';
    case ERROR = 'ERROR';
    case SKIPPED = 'SKIPPED';

    /**
     * @var string Filter menu sentinel meaning "any status". Empty so that it can
     * never collide with a status added later, and so tryFrom() rejects it.
     */
    public const ANY = '';

    /**
     * Options for the run list's status filter menu, keyed by filter value.
     *
     * @return array<string, string>
     */
    public static function filter_options(): array {
        $options = [self::ANY => get_string('allstatuses', 'mod_idetestfeedback')];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->value;
        }

        return $options;
    }

    /**
     * Counts the statuses.
     *
     * @param string[] $statuses status values, one per result
     * @return array<string, int> count per status value, every status present
     */
    public static function tally(array $statuses): array {
        $counts = array_fill_keys(array_column(self::cases(), 'value'), 0);

        foreach ($statuses as $status) {
            $counts[$status]++;
        }

        return $counts;
    }

    /**
     * The statuses in the order a run's results are listed, the ones needing attention first.
     *
     * @return self[]
     */
    public static function listing_order(): array {
        return [self::ERROR, self::FAILED, self::SKIPPED, self::PASSED];
    }

    /**
     * The worst outcome in a set of counts, which is the status of the run as a whole.
     *
     * @param array<string, int> $counts from {@see tally()}
     * @return self
     */
    public static function worst(array $counts): self {
        foreach ([self::ERROR, self::FAILED, self::PASSED] as $status) {
            if ($counts[$status->value] > 0) {
                return $status;
            }
        }

        return self::SKIPPED;
    }
}
