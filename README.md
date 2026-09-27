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
   the assignment key, and a JSON payload holding the test results.
4. The plugin validates the submission, stores one **run** plus its individual
   **results**, and returns the new run ID.
5. Students and teachers review the results on the activity page.

## Data model

Five tables (see [db/install.xml](db/install.xml) for the full schema):

- **`idetestfeedback`** — the activity instance, including the unique
  `assignmentkey` and the optional `timeopen` / `timeclose` submission window.
- **`idetestfeedback_run`** — one row per API submission (the student, IDE,
  commit and repository, overall status and per-status counts).
- **`idetestfeedback_result`** — one row per test case within a run. Also holds
  the optional per-test teacher feedback (`feedback`, `feedbackby`,
  `feedbackmodified`; see *Teacher feedback* below).
- **`idetestfeedback_file`** — one row per path a run captured. It records
  *that* the run captured something there, not what: the body itself is a
  reference, and is null when the run captured the path without content.
- **`idetestfeedback_blob`** — the bodies, stored once per distinct content per
  activity and keyed by their hash. A student running the same tests twenty
  times stores those files once, and two students who submit an identical file
  share the row.

A body is reclaimed when the last file row pointing at it goes, so erasing one
student leaves a file two students submitted standing until the second is erased
too. There is no reference count to drift: the sweep asks the file table.

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
| `ide` | alphanumext | yes | IDE identifier, e.g. `VSCODE`. Must not be blank. |
| `projectname` | text | no | |
| `commithash` | alphanum | no | |
| `repourl` | text | no | Git remote, e.g. `https://github.com/ada/calc.git` or `git@github.com:ada/calc.git`. Credentials in it (`user:token@`) are removed before storing. One longer than 1024 characters is not stored. When it points at a web host, the commit hash links to `<repo>/commit/<hash>`. |
| `startedatms` | int | no | Epoch **milliseconds**. |
| `finishedatms` | int | no | Epoch **milliseconds**. |
| `payload` | string | yes | JSON object holding `results` and `testfiles` (see below). |
| `capturedisabled` | bool | no | The student turned off sending source code. Any code posted alongside it is dropped, and with it every hash taken from it (see Validation). |

The parameter names are lowercase: a middleware posting the IDE's camelCase
payload maps `assignmentKey` → `assignmentkey`, `testFiles` → `testfiles`, and
so on. An unexpected parameter fails validation outright.

Typed parameters are checked, not silently cleaned: a value Moodle's cleaning
would change is rejected with `invalid_parameter_exception`. An `email` that
isn't a valid address, an `ide` or `assignmentkey` with characters outside
letters, digits, `-` and `_`, or a `projectname` or `repourl` containing HTML
tags all fail this way. `string` values are taken as sent.

The run's two lists travel as one JSON string rather than as nested form
parameters. Moodle's REST server reads its parameters from `$_POST`, where each
leaf of a nested array counts against PHP's `max_input_vars`; a run of a few
hundred tests silently overruns the usual 1000–5000 limit and the request is
truncated. As one parameter, a run of any size costs one input variable.

```
POST /webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=...&wsfunction=mod_idetestfeedback_submit_test_run
&moodlewsrestformat=json
&email=student%40example.com&assignmentkey=3f9c2a7b1e8d4c6a9b0e2f5d7c1a4b8e&ide=VSCODE
&payload=%7B%22results%22%3A%5B...%5D%2C%22testfiles%22%3A%5B...%5D%7D
```

The `payload` object:

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `results` | list | yes | Non-empty list of result objects (see below). |
| `testfiles` | list | no | Test files captured with the run (see below). At most 200. |

A `payload` that is not JSON, or that decodes to something other than an object,
is rejected. Its contents are validated against the same structure definitions
the other parameters use, so an unexpected key inside it still fails.

The whole request body still has to fit within `post_max_size`; only the input
variable count is decoupled from run size.

Each `results` entry:

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `testname` | string | yes | Must not be blank. |
| `status` | alpha | yes | One of `PASSED`, `FAILED`, `SKIPPED`, `ERROR`, in any letter case. |
| `testsuite` | string | no | |
| `durationms` | int | no | |
| `message` | string | no | Failure / error message. |
| `source` | object | no | Where the test case came from (see below). |

Each `results[].source` object says where in the run's `testfiles` the test came
from. It carries no code of its own: a body travels once, in `testfiles`, and a
result points into it.

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `path` | string | with a line range | Repo-relative path of the file the test is defined in. Joins to `testfiles[].path`. |
| `startline` | int | no | 1-based, inclusive. Send both line numbers or neither. |
| `endline` | int | no | 1-based, inclusive. Must not be below `startline`. |

What the block names is what the IDE found, so no kind is sent or stored — it is
read back off those three fields:

| `path` | line range | means |
| --- | --- | --- |
| absent | — | the test was not found in the project's source at all |
| present | absent | the file was found but the test could not be picked out of it; the run page shows the whole file |
| present | present | the declaration was located; the run page cuts just it out of the file |

### Hashing

Moodle computes every stored hash itself, over the bytes it is about to store.
Nothing a client sends is taken on trust. The only hash kept is the one each
file body is stored under, so identical bodies share one `idetestfeedback_blob` row.

Before hashing, a body is canonicalised: CRLF and CR become LF, a trailing IDE
truncation marker (`… [truncated …]`) and a leading UTF-8 byte order mark are
removed, trailing whitespace goes from each line, and trailing newlines are
dropped. Nothing else. Comments, tokens and indentation are **not** folded away, because doing
that needs a parser per language and Moodle has no business owning one.

A test's body is always cut out of the run's copy of its file at
`startline`&ndash;`endline`. A test declared below the point a large file was
clipped at therefore has no body: nothing is stored twice to rescue it.

Each `testfiles` entry:

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `path` | string | yes | Repo-relative path. Must not be blank. One row per path: a repeated path keeps the first entry. |
| `content` | string | no | Clipped to 512 KB, setting `truncated`. Stored canonicalised, so it may differ from what was posted by a byte order mark, its line endings and trailing whitespace. |
| `truncated` | bool | no | The content is not the whole file. |

### Returns

```json
{ "runid": 123 }
```

### Errors

A rejected submission stores nothing and returns Moodle's standard error object
instead of `runid`, with the message in the site language:

```json
{
  "exception": "mod_idetestfeedback\\local\\validation_exception",
  "errorcode": "validation_usernotfound",
  "message": "No active user was found with the email \"student@example.com\"."
}
```

Match on `errorcode`; the message is for people.

### Validation

Checks run in this order, and the first failure is the one returned:

| Rejected when | `errorcode` |
| --- | --- |
| The calling account lacks `mod/idetestfeedback:submit` | `nopermissions` (`required_capability_exception`) |
| A parameter is missing, unexpected, or fails cleaning | `invalidparameter` (`invalid_parameter_exception`) |
| `payload` is not a JSON object | `validation_invalidpayload` |
| `results` holds more than 5000 entries, or `testfiles` more than 200 | `validation_toomanyresults`, `validation_toomanyfiles` |
| The `payload` contents don't match the structure above | `invalidparameter` |
| `email` is empty | `validation_noemail` |
| `results` is empty | `validation_noresults` |
| `ide` is blank | `validation_noide` |
| A result has a blank `testname` | `validation_notestname` |
| A result has a `status` outside the allowed set | `validation_invalidstatus` |
| A result has a negative `durationms` | `validation_invalidtiming` |
| A `source` names a line range but no `path` | `validation_nosourcefilepath` |
| A `source` sends one line number without the other, starts below line 1, or ends before it starts | `validation_invalidsourcelines` |
| A `testfiles` entry has a blank `path` | `validation_nofilepath` |
| `startedatms` or `finishedatms` is negative, or the run finishes before it starts | `validation_invalidtiming` |
| No active user has the `email`, or more than one does | `validation_usernotfound`, `validation_ambiguousemail` |
| No activity uses the `assignmentkey` | `validation_assignmentnotfound` |
| The user is not actively enrolled in the activity's course | `validation_notenrolled` |
| The activity is hidden from the user, or the user lacks `mod/idetestfeedback:view` in it | `validation_activityunavailable` |
| The user lacks `mod/idetestfeedback:recordruns` in the activity | `validation_cannotrecordruns` |
| The current time is before `timeopen` or after `timeclose` | `validation_windownotopen`, `validation_windowclosed` |
| Another write to the same activity held its lock for over 30 seconds | `locktimeout` |

Oversized `testfiles[].content` does not reject the submission:
it is clipped and stored with `truncated` set, which is what the run detail page
then flags. The clip falls back to the last line boundary it kept, so the line
numbers a result carries still land on the right lines of what remains.

The run detail page shows file bodies in path order until 1 MB of them has been
shown; every file after that links to a page of its own instead, so a run at the
limits does not turn into one enormous page.

`capturedisabled` is enforced rather than taken on trust. A submission that sets
it is stored without any `testfiles[].content`, whatever the client sent, and so
without any file hash either. What describes where the code was is kept:
`testfiles[].path`, `source.path`, `startline` and `endline`. The run detail
page badges such a run as having no captured code, so the badge and the stored
data cannot disagree.

The stored run's overall `status` is derived from its results: `ERROR` if any
result errored, otherwise `FAILED` if any failed, otherwise `PASSED` if at least
one result passed, otherwise `SKIPPED` (every test was skipped).

### Completion

The `completionpassrun` rule is met by a run in which every reported test
passed. A skipped test leaves the activity incomplete, even though the run's
overall status is `PASSED`. Results are self-reported from the student's
environment and are not independently verified.

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

A test the student's earlier runs carried feedback on is badged by how its
status moved since then, compared against the most recent commented result
written before the run was submitted:

| At feedback | Now | Badge |
| --- | --- | --- |
| `FAILED` / `ERROR` | `PASSED` | Fixed since feedback |
| `FAILED` / `ERROR` | `FAILED` / `ERROR` | Still failing since feedback |
| `PASSED` | `FAILED` / `ERROR` | Failing since feedback |

Any other pair, including a skipped test on either side, gets no badge, and
neither does a result that carries feedback of its own. Tests are matched by
suite and name, ignoring case.

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
| `mod/idetestfeedback:comment` | teacher, editingteacher, manager | Write per-test feedback on a student's run (`RISK_SPAM`: sends notifications). Only takes effect together with `viewall`. |
| `mod/idetestfeedback:recordruns` | student | Have own test runs accepted by the web service. Runs sent for a user without it are rejected, so a teacher's own IDE runs never mix with students'. |
| `mod/idetestfeedback:submit` | *none* | Submit results via the web service on behalf of any user (`RISK_PERSONAL`, `RISK_SPAM`). Intended to be granted only to the middleware's service account. |
| `mod/idetestfeedback:addinstance` | editingteacher, manager | Add the activity to a course. |

## Installation

1. Copy this directory to `public/mod/idetestfeedback` in your Moodle tree
   (this repo is already checked out at that path).
2. Run the upgrade:
   ```
   php admin/cli/upgrade.php
   ```
   or visit **Site administration → Notifications**.
3. Set up the account the middleware calls Moodle as:
   1. Create a user for the middleware. It needs no course enrolments.
   2. Create a role that can be assigned in the system context, allowing
      `mod/idetestfeedback:submit` and `webservice/rest:use`, and assign it to
      that user in the system context. No role has either capability by default.
   3. Under **Site administration → Server → Web services**, enable web services
      and the REST protocol. Enable the *IDE Test Results Service*, which is
      installed disabled, and add the user to its authorised users.
   4. Create a token for the user on that service and configure the middleware
      with it.

### Requirements

- Moodle 4.5 to 5.3 (`$plugin->requires = 2024100700`, `$plugin->supported = [405, 503]`).

Languages: English. Translations are managed through AMOS.

## Tests

Run these in the Moodle root inside the `webserver` container (see below).
Initialise PHPUnit once, and again after any schema change, then run the
plugin's suite:

```bash
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite mod_idetestfeedback_testsuite
```

Tests create runs with `create_run()` from `tests/generator/lib.php`, which
stores a run without going through the web service.

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

After changing anything under `db/`, bump `$plugin->version` in
[version.php](version.php) and apply the upgrade inside the container. After
changing language strings or templates, purging caches is enough:

```bash
bin/moodle-docker-compose exec webserver php admin/cli/upgrade.php
bin/moodle-docker-compose exec webserver php admin/cli/purge_caches.php
```

Check the coding style with moodle-cs, installed globally in the container
(`vendor/bin/phpcs` in the Moodle root is a broken link):

```bash
bin/moodle-docker-compose exec webserver /root/.composer/vendor/bin/phpcs \
    --standard=moodle-extra public/mod/idetestfeedback
```

On WSL, make sure your user can talk to the Docker daemon first
(`sudo usermod -aG docker $USER`, then `wsl --shutdown` and reopen).
