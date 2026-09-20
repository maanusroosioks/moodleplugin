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

use context;
use mod_idetestfeedback\local\required_tests;
use mod_idetestfeedback\local\source_code;
use mod_idetestfeedback\local\source_kind;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * One test run in full: its summary and every test case result.
 *
 * @package    mod_idetestfeedback
 * @copyright  2026 Maanus Roosioks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_detail implements renderable, templatable {

    /** @var array<string, stdClass>|null The run's files by path, built on first use. */
    protected ?array $filesbypath = null;

    /**
     * @param stdClass $instance the activity instance
     * @param stdClass $run the run being shown
     * @param stdClass[] $results the run's test case results
     * @param stdClass[] $files the test files captured with the run
     * @param string|null $studentname the run's owner, or null to leave it out
     * @param context $context the activity context, for formatting feedback
     * @param int $cmid the course module id
     * @param bool $cancomment whether the viewer may edit feedback
     */
    public function __construct(
        protected readonly stdClass $instance,
        protected readonly stdClass $run,
        protected readonly array $results,
        protected readonly array $files,
        protected readonly ?string $studentname,
        protected readonly context $context,
        protected readonly int $cmid,
        protected readonly bool $cancomment
    ) {
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

        return [
            'backurl' => (new moodle_url('/mod/idetestfeedback/view.php', ['id' => $this->cmid]))->out(false),
            'formurl' => (new moodle_url('/mod/idetestfeedback/view.php', [
                'id' => $this->cmid,
                'runid' => $this->run->id,
            ]))->out(false),
            'meta' => $this->meta_rows($output, $entries, $showrequired),
            'hasresults' => $this->results !== [],
            'showrequired' => $showrequired,
            'showfeedback' => $showfeedback,
            'cancomment' => $this->cancomment,
            'colspan' => 6 + (int) $showrequired + (int) $showfeedback,
            'rows' => $this->result_rows($output, $entries, $showrequired),
            'hasfiles' => $this->files !== [],
            'files' => $this->file_rows(),
            'hascode' => $this->has_code(),
        ];
    }

    /**
     * Whether anything on this run is worth loading a syntax highlighter for.
     *
     * @return bool
     */
    protected function has_code(): bool {
        foreach ($this->results as $result) {
            if (trim((string) ($result->sourcecode ?? '')) !== '') {
                return true;
            }
        }
        foreach ($this->files as $file) {
            if (trim((string) ($file->content ?? '')) !== '') {
                return true;
            }
        }

        return false;
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
                'text' => get_string(
                    'durationunit',
                    'mod_idetestfeedback',
                    (int) $this->run->finishedat - (int) $this->run->startedat
                ),
            ];
        }

        return $rows;
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
     * Builds one template row per test case result.
     *
     * @param renderer_base $output
     * @param string[] $entries the activity's defined test cases
     * @param bool $showrequired whether the activity defines test cases at all
     * @return array[]
     */
    protected function result_rows(renderer_base $output, array $entries, bool $showrequired): array {
        $rows = [];
        $index = $showrequired ? required_tests::index_entries($entries) : [];

        foreach ($this->results as $result) {
            $feedback = trim((string) ($result->feedback ?? ''));
            $source = $this->source_block($result);

            $rows[] = [
                'rowclass' => status_badge::row_class($result->status),
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
                'feedbackname' => 'feedback_' . $result->id,
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
     * The code one test case ran, for the collapsible row beneath its result.
     *
     * @param stdClass $result one test case result
     * @return array|null null when the IDE said nothing at all about this test
     */
    protected function source_block(stdClass $result): ?array {
        $kind = source_kind::resolve($result->sourcekind ?? null);
        $path = (string) ($result->sourcefilepath ?? '');
        $hash = (string) ($result->sourcecodehash ?? '');

        [$code, $truncated] = $this->test_source($result, $kind);

        if ($kind === null && $code === '' && $path === '') {
            return null;
        }

        return [
            'summary' => $this->source_summary($result, $path, $kind),
            'kind' => $kind?->value ?? '',
            'expandable' => $code !== '' || $hash !== '',
            'wholefile' => $kind === source_kind::FILE && $this->has_file_content($path),
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
     * The lines one test case occupies, wherever this run happens to carry them.
     *
     * A test's body normally travels once, in the run's copy of its file, and is
     * repeated on the result only when the file could not carry it. So the
     * result's own copy wins where there is one, and otherwise the excerpt is
     * cut out of the file at the line range the IDE recorded.
     *
     * @param stdClass $result one test case result
     * @param source_kind|null $kind what the result's source block describes
     * @return array{0:string,1:bool} [the code, whether it is cut short]
     */
    protected function test_source(stdClass $result, ?source_kind $kind): array {
        $code = (string) ($result->sourcecode ?? '');
        if ($code !== '') {
            return [source_code::strip_marker($code), !empty($result->sourcetruncated)];
        }

        if ($kind === source_kind::NONE || $kind === source_kind::FILE
                || $result->sourcestartline === null || $result->sourceendline === null) {
            return ['', false];
        }

        $file = $this->files_by_path()[(string) ($result->sourcefilepath ?? '')] ?? null;
        $content = (string) ($file->content ?? '');
        if ($content === '') {
            return ['', false];
        }

        return source_code::excerpt($content, (int) $result->sourcestartline, (int) $result->sourceendline);
    }

    /**
     * Whether this run carries the contents of one of its files, rather than
     * just the fact that the file was there.
     *
     * @param string $path the path a result names
     * @return bool
     */
    protected function has_file_content(string $path): bool {
        $file = $this->files_by_path()[$path] ?? null;

        return trim((string) ($file->content ?? '')) !== '';
    }

    /**
     * @return array<string, stdClass> the run's files, by the path results join to them on
     */
    protected function files_by_path(): array {
        if ($this->filesbypath === null) {
            $this->filesbypath = [];
            foreach ($this->files as $file) {
                $this->filesbypath[(string) $file->path] = $file;
            }
        }

        return $this->filesbypath;
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
     * @param source_kind|null $kind what the result's source block describes
     * @return string
     */
    protected function source_summary(stdClass $result, string $path, ?source_kind $kind): string {
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
            $content = source_code::strip_marker((string) ($file->content ?? ''));

            $rows[] = [
                'path' => $file->path,
                'sha256' => (string) ($file->sha256 ?? ''),
                'content' => $content,
                'hascontent' => $content !== '',
                'language' => self::language_of((string) $file->path),
                'linenumbers' => $content === '' ? '' : self::line_numbers($content, 1),
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
