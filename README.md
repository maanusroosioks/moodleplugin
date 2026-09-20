# IDE Test Feedback (`mod_idetestfeedback`)

A Moodle activity module that receives unit-test results submitted from a
student's IDE and displays them per assignment. Students see their own runs and a
pass rate; teachers see every student's runs and can drill into individual test
cases.

Submissions arrive through a Moodle web service, typically called by a Java
middleware that authenticates the student via OAuth and forwards the test run.

## How it works

1. A teacher adds an **IDE Test Feedback** activity to a course. Saving it
   generates a unique **assignment key**.
2. The student pastes that key into their IDE plugin.
3. When the student runs their tests, the IDE (via the Java middleware) calls the
   `mod_idetestfeedback_submit_test_run` web service with the student's email,
   the assignment key, and the list of test results.
4. The plugin validates the submission, stores one **run** plus its individual
   **results**, and returns the new run ID.
5. Students and teachers review the results on the activity page.

## Data model

Three tables (see [db/install.xml](db/install.xml) for the full schema):

- **`idetestfeedback`** — the activity instance, including the unique
  `assignmentkey`, the optional `timeopen` / `timeclose` submission window, and
  the optional `requiredtests` list (see below).
- **`idetestfeedback_run`** — one row per API submission (the student, IDE,
  commit, overall status and per-status counts). Nothing about the
  `requiredtests` list is copied onto the run; it is scored live, see below.
- **`idetestfeedback_result`** — one row per test case within a run. Also holds
  the optional per-test teacher feedback (`feedback`, `feedbackformat`,
  `feedbackby`, `feedbackmodified`; see *Teacher feedback* below).

Deleting an activity instance cascades to its runs and results, and **Course
reset** can clear the submitted runs while keeping the activities. The plugin
implements the Moodle Privacy API for export and deletion of a user's data.

Moodle backup and restore are supported. Runs, their results and their captured
test files travel with the backup only when *include user data* is selected. The assignment key is preserved when
the destination site does not already use it, and reissued otherwise — so
duplicating an activity always yields a fresh key, and students have to copy the
new one into their IDE.

## Web service API

**Function:** `mod_idetestfeedback_submit_test_run`
**Service:** `IDE Test Results Service` (shortname `ide_test_results`, restricted
users, **disabled until an administrator enables it**)
**Required capability:** `mod/idetestfeedback:submit` (system context, granted to
no role by default)
**Type:** write · not AJAX-callable

### Parameters

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `email` | email | yes | Identity asserted by the calling middleware. Must match exactly one active (not deleted, not suspended) Moodle user. |
| `assignmentkey` | alphanumext | yes | The key shown on the activity. |
| `ide` | text | yes | IDE identifier, e.g. `VSCODE`. Must not be blank. |
| `projectname` | text | no | |
| `commithash` | text | no | |
| `startedat` | int | no | Epoch **milliseconds**. |
| `finishedat` | int | no | Epoch **milliseconds**. |
| `results` | list | yes | Non-empty list of result objects (see below). |
| `testfiles` | list | no | Test files captured with the run (see below). At most 200. |
| `capturedisabled` | bool | no | The student turned off sending source code. Any code posted alongside it is dropped, but the hashes are not (see Validation). |
| `warningacknowledged` | bool | no | The student was warned that some tests are empty and submitted anyway. |

The parameter names are lowercase: a middleware posting the IDE's camelCase
payload maps `assignmentKey` → `assignmentkey`, `testFiles` → `testfiles`,
`normalizedCodeHash` → `normalizedcodehash`, and so on.

Each `results` entry:

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `testname` | text | yes | Must not be blank. |
| `status` | alpha | yes | One of `PASSED`, `FAILED`, `SKIPPED`, `ERROR`. |
| `testsuite` | text | no | |
| `durationms` | int | no | |
| `message` | text | no | Failure / error message. |
| `source` | object | no | Where the test case came from (see below). |

Each `results[].source` object. `kind` is a discriminator, not a label: it
decides which of the other fields mean anything, and what the hash covers. The
fields a kind does not give meaning to are dropped rather than stored.

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `kind` | alphanumext | no | `NONE`, `FILE` or `TEST`, case insensitive. Inferred from the other fields when absent. |
| `filepath` | text | `FILE`, `TEST` | Repo-relative path of the file the test is defined in. Joins to `testfiles[].path`. |
| `startline` | int | `TEST` | 1-based, inclusive. |
| `endline` | int | `TEST` | 1-based, inclusive. Must not be below `startline`. |
| `code` | text | no | Clipped to 64 KB, setting `truncated`. |
| `truncated` | bool | no | The code is not the whole thing. |
| `normalizedcodehash` | alphanumext | no | Hash of the normalised source, for telling a rewritten test from a reformatted one. |

- `NONE` — the test was not found in the project's source at all. Nothing else
  about it is stored.
- `FILE` — the file was found but the test could not be picked out of it. The
  hash covers the whole file, so no line range is stored.
- `TEST` — the declaration was located. The line range and the hash cover just it.

A `FILE` hash and a `TEST` hash describe different things, so `kind` is stored
beside the hash and the two are never compared with each other.

`code` is a fallback, not the normal path. A test's body normally travels once,
in `testfiles`, and is repeated on the result only when the file could not carry
it &mdash; because the file's `content` was dropped, or because the file was cut
above that test's `endline`. The run detail page reads it that way: the result's
own copy wins where there is one, and otherwise the excerpt is cut out of the
run's copy of the file at `startline`&ndash;`endline`.

Each `testfiles` entry:

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `path` | text | yes | Repo-relative path. Must not be blank. One row per path: a repeated path keeps the first entry. |
| `sha256` | alphanumext | no | |
| `content` | text | no | Clipped to 512 KB, setting `truncated`. |
| `truncated` | bool | no | The content is not the whole file. |

### Returns

```json
{ "runid": 123 }
```

### Validation

The submission is rejected (with a localised message) when:

- no active user matches `email`, or more than one does;
- no activity matches `assignmentkey`;
- the user is not enrolled in the activity's course;
- the current time is before `timeopen` or after `timeclose`;
- any result has a `status` outside the allowed set;
- `results` is empty, or holds more than 2000 entries;
- `ide` is blank;
- `startedat`, `finishedat` or any `durationms` is negative, or the run finishes
  before it starts;
- any result has a blank `testname`;
- `testfiles` holds more than 200 entries, or any entry has a blank `path`;
- any `source` declares a `kind` outside `NONE`, `FILE` and `TEST`;
- a `FILE` or `TEST` source names no `filepath`;
- a `TEST` source has no line range, starts below line 1, or ends before it starts.

Oversized `source.code` or `testfiles[].content` does not reject the submission:
it is clipped and stored with `truncated` set, which is what the run detail page
then flags. The clip falls back to the last line boundary it kept, so the line
numbers a result carries still land on the right lines of what remains.

`capturedisabled` is enforced rather than taken on trust. A submission that sets
it is stored without any `source.code` and without any `testfiles[].content`,
whatever the client sent. The run detail page badges such a run as having no
captured code, so the badge and the stored data cannot disagree.

What is *not* dropped is everything that is not the code: `source.filepath`,
`startline`, `endline`, `kind`, `normalizedcodehash` and `testfiles[].sha256`.
Those are sent precisely because they outlive a run that carries no source, and
they are what answers "did this test change since last time" for such a run.

The stored run's overall `status` is derived from its results: `ERROR` if any
result errored, otherwise `FAILED` if any failed, otherwise `PASSED` if at least
one result passed, otherwise `SKIPPED` (every test was skipped).

### Defined test cases (optional)

A teacher can list the test cases that count for the activity in the
**Defined test cases** field, one per line, as either `testName` or
`testSuite#testName`. The list is stored on the instance as `requiredtests`;
blank lines, entries with no name part (`#`, `Suite#`) and case-insensitive
duplicates are dropped on save. Each run is scored **live** against the list as
it currently stands (nothing is copied onto the run):

| `requiredtests` on the activity | How a run is scored | `completionpassrun` is met by |
| --- | --- | --- |
| empty (default) | overall `status` only | a run in which every reported test passed — a skipped test leaves the activity incomplete, even though the run's overall status is `PASSED` |
| non-empty | every listed entry lands in exactly one bucket per run: passed, failed (also covers errored), skipped, or missing (not reported) | a run in which every listed entry passed |

Matching is case-insensitive (after trimming) against the `testname` /
`testsuite` the IDE reports. A bare `testName` matches regardless of suite; a
`testSuite#testName` entry also requires the suite to match (everything after
the last `#` is the name).

Scoring always reflects the **current** list: the run-details page and the
`completionpassrun` rule re-evaluate past runs whenever the list changes.
Adding a list makes earlier runs incomplete until one passes every listed
entry; clearing the list reverts to the status-only rule. Results remain
self-reported from the student's environment and are not independently verified.

## Course reset

**Course → Reset** offers *Delete all submitted test runs*, which removes every
run and result from the course's activities while leaving the activities and
their assignment keys in place. A date shift moves `timeopen` and `timeclose`
along with the rest of the course.

## Teacher feedback

On a student's run-details page a user with `mod/idetestfeedback:comment` sees a
**Teacher feedback** column with a text box on every test row. Saving stores the
text against that `idetestfeedback_result` row; clearing a box removes it. The
student sees the same column read-only, and only when at least one row has
feedback.

Ticking **Notify student by message** when saving sends the affected student a
Moodle notification (message provider `mod_idetestfeedback/feedback`) whose body
contains the feedback text that changed plus a link back to the run. Without the
tick, nothing is sent. Students control delivery channels under their own
notification preferences.

## Capabilities

| Capability | Default roles | Purpose |
| --- | --- | --- |
| `mod/idetestfeedback:view` | student, teacher, editingteacher, manager | View own test results. |
| `mod/idetestfeedback:viewall` | teacher, editingteacher, manager | View all students' results (`RISK_PERSONAL`). |
| `mod/idetestfeedback:comment` | teacher, editingteacher, manager | Write per-test feedback on a student's run. |
| `mod/idetestfeedback:submit` | *none* | Submit results via the web service. Intended to be granted only to the middleware's service account. |
| `mod/idetestfeedback:addinstance` | editingteacher, manager | Add the activity to a course. |

## Installation

1. Copy this directory to `public/mod/idetestfeedback` in your Moodle tree
   (this repo is already checked out at that path).
2. Bump `$plugin->version` in [version.php](version.php) if you changed the
   schema or strings.
3. Run the upgrade:
   ```
   php admin/cli/upgrade.php
   ```
   or visit **Site administration → Notifications**.
4. Enable web services and create a token for the service account:
   - **Site administration → Server → Web services → Overview**, or
   - assign `mod/idetestfeedback:submit` to the service account and issue a token
     for the *IDE Test Results Service*.

### Requirements

- Moodle 4.5+ (`$plugin->requires = 2024100700`).

Languages: English and Estonian.

## Tests

```bash
vendor/bin/phpunit --filter mod_idetestfeedback
```

- `tests/local/required_tests_test.php` — parsing and scoring of the defined test
  case list (no database needed).
- `tests/custom_completion_test.php` — the `completionpassrun` rule, including
  the skipped-test behaviour described above.

## Development environment

Local development uses [moodle-docker](https://github.com/moodlehq/moodle-docker).
Minimal setup:

```bash
git clone https://github.com/moodle/moodle.git ~/moodle-dev/moodle
git clone https://github.com/moodlehq/moodle-docker.git ~/moodle-dev/moodle-docker

cd ~/moodle-dev/moodle-docker
export MOODLE_DOCKER_WWWROOT=$HOME/moodle-dev/moodle
export MOODLE_DOCKER_DB=pgsql
export MOODLE_DOCKER_DB_PORT=5433
bin/moodle-docker-compose up -d
```

After changing plugin files, apply the upgrade inside the container:

```bash
bin/moodle-docker-compose exec webserver php admin/cli/upgrade.php
```

On WSL, make sure your user can talk to the Docker daemon first
(`sudo usermod -aG docker $USER`, then `wsl --shutdown` and reopen).
