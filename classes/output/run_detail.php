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

use core\context;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core\url;
use mod_idetestfeedback\local\feedback_history;
use mod_idetestfeedback\local\feedback_outcome;
use mod_idetestfeedback\local\git_remote;
use mod_idetestfeedback\local\source_code;
use mod_idetestfeedback\local\source_kind;
use stdClass;

/**
 * One test run in full: its summary and every test case result.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_detail implements renderable, templatable {
    /** @var int Most bytes of file content shown on the run page; larger files open on their own page. */
    public const INLINE_FILE_BYTES = 1048576;

    /** @var array<string, stdClass> The run's files, by the path results join to them on. */
    protected readonly array $filesbypath;

    /**
     * Creates the run detail.
     *
     * @param stdClass $run the run being shown
     * @param stdClass[] $results the run's test case results
     * @param stdClass[] $files the test files captured with the run
     * @param string|null $studentname the run's owner, or null to leave it out
     * @param context $context the activity context, for formatting feedback
     * @param bool $cancomment whether the viewer may edit feedback
     * @param feedback_history $history the tests a teacher commented on in the student's earlier runs
     * @param url $backurl the run list this run was opened from
     * @param url $runurl the run's own page, which the feedback form posts to and files open from
     * @param int $page zero based page number within the results
     * @param int $perpage results per page
     * @param string $pagingbar the rendered paging bar
     */
    public function __construct(
        /** @var stdClass The run being shown */
        protected readonly stdClass $run,
        /** @var stdClass[] The run's test case results */
        protected readonly array $results,
        /** @var stdClass[] The test files captured with the run */
        protected readonly array $files,
        /** @var string|null The run's owner, or null to leave it out */
        protected readonly ?string $studentname,
        /** @var context The activity context, for formatting feedback */
        protected readonly context $context,
        /** @var bool Whether the viewer may edit feedback */
        protected readonly bool $cancomment,
        /** @var feedback_history The tests a teacher commented on in the student's earlier runs */
        protected readonly feedback_history $history,
        /** @var url The run list this run was opened from */
        protected readonly url $backurl,
        /** @var url The run's own page, which the feedback form posts to and files open from */
        protected readonly url $runurl,
        /** @var int Zero based page number within the results */
        protected readonly int $page,
        /** @var int Results per page */
        protected readonly int $perpage,
        /** @var string The rendered paging bar */
        protected readonly string $pagingbar
    ) {
        $this->filesbypath = array_column($files, null, 'path');
    }

    /**
     * Exports the run summary and results for the run detail templates.
     *
     * @param renderer_base $output
     * @return array
     */
    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $showfeedback = $this->cancomment || $this->has_feedback();
        $files = $this->file_rows();
        $rows = $this->result_rows($output);

        $highlighted = array_merge(
            array_filter($files, fn(array $file) => $file['inline'] && $file['hascontent']),
            array_filter(array_filter(array_column($rows, 'source')), fn(array $source) => $source['hascode'])
        );

        return [
            'backurl' => $this->backurl->out(false),
            'runurl' => $this->runurl->out(false),
            'meta' => $this->meta_rows($output),
            'hasresults' => $this->results !== [],
            'showfeedback' => $showfeedback,
            'cancomment' => $this->cancomment,
            'colspan' => 6 + (int) $showfeedback,
            'rows' => $rows,
            'pagingbar' => $this->pagingbar,
            'hasfiles' => $files !== [],
            'files' => $files,
            'hascode' => array_filter($highlighted, fn(array $block) => $block['language'] !== '') !== [],
        ];
    }

    /**
     * Whether any result on this run already carries feedback.
     *
     * @return bool
     */
    protected function has_feedback(): bool {
        foreach ($this->results as $result) {
            if (trim((string) ($result->feedback ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the run summary rows.
     *
     * Each row carries a label plus exactly one of text, code, strong or
     * badge, so the template decides the markup and nothing is exported
     * pre-escaped.
     *
     * @param renderer_base $output
     * @return array[]
     */
    protected function meta_rows(renderer_base $output): array {
        $rows = [];

        if ($this->studentname !== null) {
            $rows[] = [
                'label' => get_string('student', 'mod_idetestfeedback'),
                'text' => $this->studentname,
            ];
        }

        $rows[] = ['label' => get_string('ide', 'mod_idetestfeedback'), 'text' => $this->run->ide];

        if ($this->run->projectname) {
            $rows[] = [
                'label' => get_string('projectname', 'mod_idetestfeedback'),
                'text' => $this->run->projectname,
            ];
        }
        if (!empty($this->run->repourl)) {
            $rows[] = self::linked_code_row(
                get_string('repourl', 'mod_idetestfeedback'),
                $this->run->repourl,
                git_remote::web_url($this->run->repourl)
            );
        }
        $rows[] = $this->run->commithash
            ? self::linked_code_row(
                get_string('commithash', 'mod_idetestfeedback'),
                $this->run->commithash,
                git_remote::commit_url($this->run->repourl ?? null, $this->run->commithash)
            )
            : ['label' => get_string('commithash', 'mod_idetestfeedback'), 'text' => '—'];

        $rows[] = [
            'label' => get_string('status', 'mod_idetestfeedback'),
            'badge' => (new status_badge($this->run->status))->export_for_template($output),
        ];

        $total = (int) $this->run->passedcount + (int) $this->run->failedcount
            + (int) $this->run->skippedcount + (int) $this->run->errorcount;
        $rows[] = [
            'label' => get_string('passed', 'mod_idetestfeedback'),
            'strong' => (int) $this->run->passedcount . ' / ' . $total,
        ];

        if ((int) $this->run->skippedcount > 0) {
            $rows[] = [
                'label' => get_string('skipped', 'mod_idetestfeedback'),
                'text' => (string) (int) $this->run->skippedcount,
            ];
        }

        $rows = array_merge($rows, $this->timing_rows(), $this->flag_rows());

        $rows[] = [
            'label' => get_string('timecreated', 'mod_idetestfeedback'),
            'text' => userdate((int) $this->run->timecreated),
        ];

        return $rows;
    }

    /**
     * The run's own clock, as the IDE reported it in milliseconds.
     *
     * @return array[]
     */
    protected function timing_rows(): array {
        $rows = [];

        foreach (['startedat' => $this->run->startedatms, 'finishedat' => $this->run->finishedatms] as $label => $ms) {
            if ($ms !== null) {
                $rows[] = [
                    'label' => get_string($label, 'mod_idetestfeedback'),
                    'text' => userdate(intdiv((int) $ms, 1000)),
                ];
            }
        }

        if ($this->run->startedatms !== null && $this->run->finishedatms !== null) {
            $rows[] = [
                'label' => get_string('runduration', 'mod_idetestfeedback'),
                'text' => self::run_duration((int) $this->run->finishedatms - (int) $this->run->startedatms),
            ];
        }

        return $rows;
    }

    /**
     * Formats a run's duration.
     *
     * @param int $ms how long the run took, in milliseconds
     * @return string milliseconds under a second, format_time() units from there on
     */
    protected static function run_duration(int $ms): string {
        if ($ms < 1000) {
            return get_string('durationunit', 'mod_idetestfeedback', $ms);
        }

        return format_time((int) round($ms / 1000));
    }

    /**
     * A metadata row showing a value as code, linked when there is a page for it.
     *
     * @param string $label the row heading
     * @param string $code the value
     * @param string|null $url the page the value links to
     * @return array
     */
    protected static function linked_code_row(string $label, string $code, ?string $url): array {
        return $url === null
            ? ['label' => $label, 'code' => $code]
            : ['label' => $label, 'link' => ['url' => $url, 'code' => $code]];
    }

    /**
     * What the student chose about code capture, which decides how much of this
     * run can be taken at face value.
     *
     * @return array[] at most one row, holding a badge when capture was disabled
     */
    protected function flag_rows(): array {
        if (empty($this->run->capturedisabled)) {
            return [];
        }

        return [[
            'label' => get_string('runflags', 'mod_idetestfeedback'),
            'badges' => [[
                'label' => get_string('capturedisabled', 'mod_idetestfeedback'),
                'classes' => 'bg-secondary text-white',
            ]],
        ]];
    }

    /**
     * The results on the current page.
     *
     * @return stdClass[]
     */
    protected function page_results(): array {
        return array_slice($this->results, $this->page * $this->perpage, $this->perpage);
    }

    /**
     * Builds one template row per test case result on the current page.
     *
     * @param renderer_base $output
     * @return array[]
     */
    protected function result_rows(renderer_base $output): array {
        $rows = [];

        foreach ($this->page_results() as $result) {
            $feedback = trim((string) ($result->feedback ?? ''));
            $source = $this->source_block($result);

            $rows[] = [
                'rowclass' => status_badge::row_class($result->status),
                'badges' => $this->feedback_badges($result),
                'hassource' => $source !== null,
                'source' => $source,
                'sourceid' => 'idetestfeedback-source-' . $result->id,
                'testsuite' => (string) ($result->testsuite ?? ''),
                'testname' => $result->testname,
                'badge' => (new status_badge($result->status))->export_for_template($output),
                'duration' => $result->durationms !== null
                    ? get_string('durationunit', 'mod_idetestfeedback', (int) $result->durationms)
                    : '',
                'message' => (string) ($result->message ?? ''),
                'feedbackname' => 'feedback[' . $result->id . ']',
                'feedbacklabel' => get_string('feedbackfor', 'mod_idetestfeedback', $result->testname),
                'feedback' => $feedback,
                // Stored feedback is plain text, but format_text() still owns the
                // sanitising, so this is the one field the template prints unescaped.
                'feedbackhtml' => $feedback === '' ? '' : format_text(
                    $result->feedback,
                    FORMAT_PLAIN,
                    ['context' => $this->context]
                ),
            ];
        }

        return $rows;
    }

    /**
     * How this test's status moved since a teacher last commented on it.
     *
     * @param stdClass $result one test case result
     * @return array[] zero or one badge
     */
    protected function feedback_badges(stdClass $result): array {
        $badge = match ($this->history->outcome($result)) {
            feedback_outcome::FIXED => $this->badge('fixedsincefeedback', 'bg-success text-white'),
            feedback_outcome::STILLFAILING => $this->badge('stillfailingsincefeedback', 'bg-warning text-dark'),
            feedback_outcome::REGRESSED => $this->badge('failingsincefeedback', 'bg-danger text-white'),
            null => null,
        };

        return $badge === null ? [] : [$badge];
    }

    /**
     * Builds a badge.
     *
     * @param string $identifier the label's string identifier
     * @param string $classes the Bootstrap classes the badge carries
     * @return array
     */
    protected function badge(string $identifier, string $classes): array {
        return ['label' => get_string($identifier, 'mod_idetestfeedback'), 'classes' => $classes];
    }

    /**
     * The code one test case ran, for the collapsible row beneath its result.
     *
     * @param stdClass $result one test case result
     * @return array|null null when the IDE said nothing at all about this test
     */
    protected function source_block(stdClass $result): ?array {
        $kind = source_kind::of($result);

        // Naming no file is only a report that the test was not found when the
        // run looked; a run carrying no files at all says nothing either way.
        if ($kind === source_kind::NONE && !$this->files) {
            return null;
        }

        $path = (string) ($result->sourcefilepath ?? '');
        $wholefile = $kind === source_kind::FILE && trim($this->content_of($this->file_of($result))) !== '';

        [$code, $truncated] = $this->test_source($result, $kind);

        return [
            'summary' => $this->source_summary($result, $kind),
            'expandable' => $code !== '' || $wholefile,
            'code' => $code,
            'hascode' => $code !== '',
            'language' => self::language_of($path),
            'linenumbers' => $code === '' ? '' : self::line_numbers($code, $result->sourcestartline),
            'truncated' => $truncated,
        ];
    }

    /**
     * The lines one test case occupies, cut out of the run's copy of its file
     * at the line range the IDE recorded.
     *
     * @param stdClass $result one test case result
     * @param source_kind $kind what the result's source block describes
     * @return array{0:string,1:bool} [the code, whether it is cut short]
     */
    protected function test_source(stdClass $result, source_kind $kind): array {
        if ($kind !== source_kind::TEST) {
            return ['', false];
        }

        $content = $this->content_of($this->file_of($result));
        if ($content === '') {
            return ['', false];
        }

        return source_code::excerpt($content, (int) $result->sourcestartline, (int) $result->sourceendline);
    }

    /**
     * The run's copy of the file a result names.
     *
     * @param stdClass $result one test case result
     * @return stdClass|null the run's copy of the file the result names, if it has one
     */
    protected function file_of(stdClass $result): ?stdClass {
        return $this->filesbypath[(string) ($result->sourcefilepath ?? '')] ?? null;
    }

    /**
     * The content of a file.
     *
     * @param stdClass|null $file one of the run's files
     * @return string the source the file carries, stored canonicalised
     */
    protected function content_of(?stdClass $file): string {
        return (string) ($file->content ?? '');
    }

    /**
     * The gutter beside a source block, numbering its lines as the file numbers them.
     *
     * @param string $code the captured source in canonical form, never empty
     * @param int|null $startline the line the capture began at, null when the IDE did not say
     * @return string one number per line of code, newline separated
     */
    protected static function line_numbers(string $code, ?int $startline): string {
        $first = max(1, (int) $startline);

        return implode("\n", range($first, $first + substr_count(rtrim($code, "\n"), "\n")));
    }

    /**
     * The one line shown while a source block is collapsed.
     *
     * @param stdClass $result one test case result
     * @param source_kind $kind how much of its file the result names
     * @return string
     */
    protected function source_summary(stdClass $result, source_kind $kind): string {
        if ($kind === source_kind::NONE) {
            return get_string('sourcenotfound', 'mod_idetestfeedback');
        }

        $label = (string) $result->sourcefilepath;

        if ($result->sourcestartline === null) {
            return $label;
        }

        $lines = (string) (int) $result->sourcestartline;
        if ($result->sourceendline !== null && (int) $result->sourceendline !== (int) $result->sourcestartline) {
            $lines .= '-' . (int) $result->sourceendline;
        }

        return $label . ':' . $lines;
    }

    /**
     * Builds one template row per captured test file.
     *
     * Bodies are shown in path order until INLINE_FILE_BYTES is spent; the
     * rest link to a page of their own instead.
     *
     * @return array[]
     */
    protected function file_rows(): array {
        $rows = [];
        $budget = self::INLINE_FILE_BYTES;

        foreach ($this->files as $file) {
            $size = strlen($this->content_of($file));
            $inline = $size <= $budget;
            $budget -= $inline ? $size : 0;

            $row = self::file_row($file);
            $rows[] = [
                'inline' => $inline,
                'fileurl' => (new url($this->runurl, ['fileid' => $file->id]))->out(false),
            ] + ($inline ? $row : ['content' => '', 'linenumbers' => ''] + $row);
        }

        return $rows;
    }

    /**
     * The template row for one captured test file, body included.
     *
     * @param stdClass $file the file, with the body it points at
     * @return array
     */
    public static function file_row(stdClass $file): array {
        $content = (string) ($file->content ?? '');
        $hascontent = trim($content) !== '';

        return [
            'path' => $file->path,
            'contenthash' => (string) ($file->contenthash ?? ''),
            'content' => $content,
            'hascontent' => $hascontent,
            'language' => self::language_of((string) $file->path),
            'linenumbers' => $hascontent ? self::line_numbers($content, 1) : '',
            'truncated' => !empty($file->truncated),
        ];
    }

    /**
     * The Prism language a file's extension names, limited to what the
     * filter_codehighlighter build actually ships a grammar for.
     *
     * @param string $path the file the code came from
     * @return string the Prism language name, '' when it is not one we highlight
     */
    protected static function language_of(string $path): string {
        $languages = [
            'c' => 'c', 'h' => 'c',
            'cpp' => 'cpp', 'cc' => 'cpp', 'cxx' => 'cpp', 'hpp' => 'cpp', 'hh' => 'cpp',
            'cs' => 'csharp',
            'css' => 'css',
            'htm' => 'markup', 'html' => 'markup', 'svg' => 'markup', 'xml' => 'markup',
            'java' => 'java',
            'cjs' => 'javascript', 'js' => 'javascript', 'jsx' => 'javascript', 'mjs' => 'javascript',
            'php' => 'php',
            'py' => 'python',
            'rb' => 'ruby',
        ];

        $extension = \core_text::strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $languages[$extension] ?? '';
    }
}
