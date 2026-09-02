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
  `assignmentkey` and the optional `timeopen` / `timeclose` submission window.
- **`idetestfeedback_run`** — one row per API submission (the student, IDE,
  commit, overall status, and per-status counts).
- **`idetestfeedback_result`** — one row per test case within a run.

Deleting an activity instance cascades to its runs and results. The plugin
implements the Moodle Privacy API for export and deletion of a user's data.
Moodle backup/restore is **not** supported.

## Web service API

**Function:** `mod_idetestfeedback_submit_test_run`
**Service:** `IDE Test Results Service` (shortname `ide_test_results`, restricted
users, enabled by default)
**Required capability:** `mod/idetestfeedback:submit` (system context)
**Type:** write · not AJAX-callable

### Parameters

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `email` | email | yes | Identity asserted by the calling middleware. Must match exactly one active (not deleted, not suspended) Moodle user. |
| `assignmentkey` | alphanumext | yes | The key shown on the activity. |
| `ide` | alphaext | yes | IDE identifier, e.g. `VSCODE`. |
| `projectname` | text | no | |
| `commithash` | text | no | |
| `startedat` | int | no | Unix timestamp. |
| `finishedat` | int | no | Unix timestamp. |
| `results` | list | yes | Non-empty list of result objects (see below). |

Each `results` entry:

| Name | Type | Required | Notes |
| --- | --- | --- | --- |
| `testname` | text | yes | |
| `status` | alpha | yes | One of `PASSED`, `FAILED`, `SKIPPED`, `ERROR`. |
| `testsuite` | text | no | |
| `durationms` | int | no | |
| `message` | text | no | Failure / error message. |
| `stacktracehash` | alphanumext | no | |

### Returns

```json
{ "runid": 123 }
```

### Validation

The submission is rejected (with a localised message) when:

- no single active user matches `email`;
- no activity matches `assignmentkey`;
- the user is not enrolled in the activity's course;
- the current time is before `timeopen` or after `timeclose`;
- any result has a `status` outside the allowed set;
- `results` is empty.

The stored run's overall `status` is derived from its results: `ERROR` if any
result errored, otherwise `FAILED` if any failed, otherwise `PASSED` if at least
one result passed, otherwise `SKIPPED` (every test was skipped). Only a `PASSED`
run satisfies the `completionpassrun` rule.

## Capabilities

| Capability | Default roles | Purpose |
| --- | --- | --- |
| `mod/idetestfeedback:view` | student, teacher, editingteacher, manager | View own test results. |
| `mod/idetestfeedback:viewall` | teacher, editingteacher, manager | View all students' results (`RISK_PERSONAL`). |
| `mod/idetestfeedback:submit` | manager | Submit results via the web service. Intended to be granted only to the middleware's service account. |
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

- Moodle 4.4+ (`$plugin->requires = 2024042200`).

Languages: English and Estonian.

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
