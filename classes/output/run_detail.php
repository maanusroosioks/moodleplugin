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
use mod_idetestfeedback\local\required_tests;
use mod_idetestfeedback\local\source_change;
use mod_idetestfeedback\local\source_code;
use mod_idetestfeedback\local\source_history;
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

    /** @var array<string, stdClass> The run's files, by the path results join to them on. */
    protected readonly array $filesbypath;

    /**
     * @param stdClass $instance the activity instance
     * @param stdClass $run the run being shown
     * @param stdClass[] $results the run's test case results
     * @param stdClass[] $files the test files captured with the run
     * @param string|null $studentname the run's owner, or null to leave it out
     * @param context $context the activity context, for formatting feedback
     * @param bool $cancomment whether the viewer may edit feedback
     * @param source_history $history the same tests as the student's earlier runs reported them
     * @param url $backurl the run list this run was opened from
     * @param url $formurl where the feedback form posts to
     * @param int $page zero based page number within the results
     * @param int $perpage results per page, or 0 to show them all
     * @param string $pagingbar the rendered paging bar
     */
    public function __construct(
        protected readonly stdClass $instance,
        protected readonly stdClass $run,
        protected readonly array $results,
        protected readonly array $files,
        protected readonly ?string $studentname,
        protected readonly context $context,
        protected readonly bool $cancomment,
        protected readonly source_history $history,
        protected readonly url $backurl,
        protected readonly url $formurl,
        protected readonly int $page = 0,
        protected readonly int $perpage = 0,
        protected readonly string $pagingbar = ''
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
        $entries = required_tests::parse($this->instance->requiredtests ?? null);
        $showrequired = $entries !== [];
        $showfeedback = $this->cancomment || $this->has_feedback();
        $files = $this->file_rows();

        return [
            'backurl' => $this->backurl->out(false),
            'formurl' => $this->formurl->out(false),
            'meta' => $this->meta_rows($output, $entries, $showrequired),
            'hasresults' => $this->results !== [],
            'showrequired' => $showrequired,
            'showfeedback' => $showfeedback,
            'cancomment' => $this->cancomment,
            'colspan' => 6 + (int) $showrequired + (int) $showfeedback,
            'rows' => $this->result_rows($output, $entries, $showrequired),
            'pagingbar' => $this->pagingbar,
            'hasfiles' => $files !== [],
            'files' => $files,
            'hascode' => array_filter($files, fn(array $file) => $file['hascontent'] && $file['language'] !== '') !== [],
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
     * @param string[] $entries the activity's defined test cases
     * @param bool $showrequired whether the activity defines test cases at all
     * @return array[]
     */
    protected function meta_rows(renderer_base $output, array $entries, bool $showrequired): array {
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
        if ($this->run->commithash) {
            $rows[] = [
                'label' => get_string('commithash', 'mod_idetestfeedback'),
                'code' => $this->run->commithash,
            ];
        }

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

        if ($showrequired) {
            $rows = array_merge($rows, $this->required_rows($entries));
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

        foreach (['startedat', 'finishedat'] as $field) {
            if ($this->run->$field !== null) {
                $rows[] = [
                    'label' => get_string($field, 'mod_idetestfeedback'),
                    'text' => userdate(intdiv((int) $this->run->$field, 1000)),
                ];
            }
        }

        if ($this->run->startedat !== null && $this->run->finishedat !== null) {
            $rows[] = [
                'label' => get_string('runduration', 'mod_idetestfeedback'),
                'text' => self::run_duration((int) $this->run->finishedat - (int) $this->run->startedat),
            ];
        }

        return $rows;
    }

    /**
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
     * What the student chose about code capture, which decides how much of this
     * run can be taken at face value.
     *
     * @return array[] at most one row, holding a badge per flag that is set
     */
    protected function flag_rows(): array {
        $badges = [];

        if (!empty($this->run->capturedisabled)) {
            $badges[] = [
                'label' => get_string('capturedisabled', 'mod_idetestfeedback'),
                'classes' => 'bg-secondary text-white',
            ];
        }
        if (!empty($this->run->warningacknowledged)) {
            $badges[] = [
                'label' => get_string('warningacknowledged', 'mod_idetestfeedback'),
                'classes' => 'bg-warning text-dark',
            ];
        }

        if (!$badges) {
            return [];
        }

        return [[
            'label' => get_string('runflags', 'mod_idetestfeedback'),
            'badges' => $badges,
        ]];
    }

    /**
     * Builds the summary rows scoring this run against the defined test cases.
     *
     * @param string[] $entries the activity's defined test cases
     * @return array[]
     */
    protected function required_rows(array $entries): array {
        $required = required_tests::evaluate($entries, $this->results);
        if ($required['total'] === 0) {
            return [];
        }

        $rows = [[
            'label' => get_string('requiredprogress', 'mod_idetestfeedback'),
            'strong' => $required['passed'] . ' / ' . $required['total'],
        ]];

        $outstanding = [];
        if ($required['failed'] > 0) {
            $outstanding[] = get_string('requiredfailedn', 'mod_idetestfeedback', $required['failed']);
        }
        if ($required['skipped'] > 0) {
            $outstanding[] = get_string('requiredskippedn', 'mod_idetestfeedback', $required['skipped']);
        }
        if ($required['missing'] > 0) {
            $outstanding[] = get_string('requiredmissingn', 'mod_idetestfeedback', $required['missing']);
        }

        if ($outstanding) {
            $rows[] = [
                'label' => get_string('requiredoutstanding', 'mod_idetestfeedback'),
                'text' => implode('; ', $outstanding),
            ];
        }

        return $rows;
    }

    /**
     * The results on the current page.
     *
     * @return stdClass[]
     */
    protected function page_results(): array {
        if ($this->perpage <= 0) {
            return $this->results;
        }

        return array_slice($this->results, $this->page * $this->perpage, $this->perpage);
    }

    /**
     * Builds one template row per test case result on the current page.
     *
     * @param renderer_base $output
     * @param string[] $entries the activity's defined test cases
     * @param bool $showrequired whether the activity defines test cases at all
     * @return array[]
     */
    protected function result_rows(renderer_base $output, array $entries, bool $showrequired): array {
        $rows = [];
        $index = $showrequired ? required_tests::index_entries($entries) : [];

        foreach ($this->page_results() as $result) {
            $feedback = trim((string) ($result->feedback ?? ''));
            $source = $this->source_block($result);

            $rows[] = [
                'rowclass' => status_badge::row_class($result->status),
                'badges' => $this->history_badges($result),
                'hassource' => $source !== null,
                'source' => $source,
                'sourceid' => 'idetestfeedback-source-' . $result->id,
                'testsuite' => (string) ($result->testsuite ?? ''),
                'testname' => $result->testname,
                'required' => $showrequired && required_tests::is_required(
                    $index,
                    $result->testsuite ?? null,
                    $result->testname
                ),
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
                    (int) $result->feedbackformat,
                    ['context' => $this->context]
                ),
            ];
        }

        return $rows;
    }

    /**
     * How this test's code has moved since the student's earlier runs.
     *
     * The feedback axis wins where it has an answer, being the one a reader
     * acts on. An unchanged TEST body only rules out the declaration, so the
     * file it lives in decides how much the label may claim.
     *
     * @param stdClass $result one test case result
     * @return array[] zero or one badge
     */
    protected function history_badges(stdClass $result): array {
        $badge = match ($this->history->since_feedback($result)) {
            source_change::CHANGED => $this->badge('sourcefeedbackchanged', 'bg-info text-dark'),
            source_change::UNCHANGED => $this->unchanged_badge($result),
            source_change::UNKNOWN => $this->history->since_last_run($result) === source_change::CHANGED
                ? $this->badge('sourcechanged', 'bg-info text-dark')
                : null,
        };

        return $badge === null ? [] : [$badge];
    }

    /**
     * @param stdClass $result a test case whose body has not changed since it was commented on
     * @return array the widest badge the captured hashes support
     */
    protected function unchanged_badge(stdClass $result): array {
        if (source_kind::of($result) === source_kind::FILE) {
            return $this->badge('sourcefeedbackunchangedfile', 'bg-warning text-dark');
        }

        $file = $this->file_of($result);
        $samefile = $this->history->file_since_feedback($result, isset($file->blobid) ? (int) $file->blobid : null);

        return $samefile === source_change::UNCHANGED
            ? $this->badge('sourcefeedbackunchangedfile', 'bg-warning text-dark')
            : $this->badge('sourcefeedbackunchanged', 'bg-secondary text-white');
    }

    /**
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
        $hash = (string) ($result->sourcecodehash ?? '');

        [$code, $truncated] = $this->test_source($result, $kind);

        return [
            'summary' => $this->source_summary($result, $path, $kind),
            'kind' => $kind->value,
            'expandable' => $code !== '' || $hash !== '',
            'wholefile' => $kind === source_kind::FILE && trim($this->content_of($this->file_of($result))) !== '',
            'code' => $code,
            'hascode' => $code !== '',
            'language' => self::language_of($path),
            'linenumbers' => $code === '' ? '' : self::line_numbers($code, $result->sourcestartline),
            'hash' => $hash,
            'hashlabel' => get_string(
                $kind === source_kind::FILE ? 'sourcehashfile' : 'sourcehash',
                'mod_idetestfeedback'
            ),
            'truncated' => $truncated,
        ];
    }

    /**
     * The lines one test case occupies, cut out of the run's copy of its file.
     *
     * A test's body normally travels once, in the run's copy of its file, and is
     * repeated on the result only when the file could not carry it. So the
     * result's own copy wins where there is one, and otherwise the excerpt is
     * cut out of the file at the line range the IDE recorded.
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
     * @param stdClass $result one test case result
     * @return stdClass|null the run's copy of the file the result names, if it has one
     */
    protected function file_of(stdClass $result): ?stdClass {
        return $this->filesbypath[(string) ($result->sourcefilepath ?? '')] ?? null;
    }

    /**
     * @param stdClass|null $file one of the run's files
     * @return string the source the file carries, stored canonicalised
     */
    protected function content_of(?stdClass $file): string {
        return (string) ($file->content ?? '');
    }

    /**
     * The gutter beside a source block, numbering its lines as the file numbers them.
     *
     * @param string $code the captured source, never empty
     * @param int|null $startline the line the capture began at, null when the IDE did not say
     * @return string one number per line of code, newline separated
     */
    protected static function line_numbers(string $code, ?int $startline): string {
        $first = $startline !== null ? max(1, (int) $startline) : 1;
        $body = str_replace(["\r\n", "\r"], "\n", rtrim($code, "\r\n"));

        return implode("\n", range($first, $first + substr_count($body, "\n")));
    }

    /**
     * The one line shown while a source block is collapsed.
     *
     * @param stdClass $result one test case result
     * @param string $path the file the code came from, possibly empty
     * @param source_kind $kind how much of that file the result names
     * @return string
     */
    protected function source_summary(stdClass $result, string $path, source_kind $kind): string {
        if ($kind === source_kind::NONE) {
            return get_string('sourcenotfound', 'mod_idetestfeedback');
        }

        $label = $path !== '' ? $path : get_string('sourcecode', 'mod_idetestfeedback');

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
     * @return array[]
     */
    protected function file_rows(): array {
        $rows = [];

        foreach ($this->files as $file) {
            $content = $this->content_of($file);
            $hascontent = trim($content) !== '';

            $rows[] = [
                'path' => $file->path,
                'contenthash' => (string) ($file->contenthash ?? ''),
                'content' => $content,
                'hascontent' => $hascontent,
                'language' => self::language_of((string) $file->path),
                'linenumbers' => $hascontent ? self::line_numbers($content, 1) : '',
                'truncated' => !empty($file->truncated),
            ];
        }

        return $rows;
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
