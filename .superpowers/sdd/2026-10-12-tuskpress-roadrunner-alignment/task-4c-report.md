# Task 4c implementation report

## Changes

- Updated `tests/Smoke/compose-smoke.sh` so `compose_project()` resolves Compose configuration with `config --format json` and uses the required PHP CLI to decode the JSON and read its top-level `name` field.
- Invalid JSON, a missing `name`, or a non-string `name` causes validation to fail. The existing exact comparison with the generated project ID and strict project-name regex remains in place. The initial check still runs before `cleanup_armed=1`; cleanup revalidates identity before logs or `down` operations.
- Added an architecture contract asserting JSON config and top-level `name` access, preserving the ID/regex assertion, and rejecting `config --project-name`.

## TDD evidence

### RED

Command:

```text
.\vendor\bin\phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php
```

Output:

```text
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.10
Configuration: C:\Users\wende\Projects\tusk\.worktrees\tuskpress-roadrunner\phpunit.xml

........F                                                           9 / 9 (100%)

Time: 00:00.041, Memory: 10.00 MB

There was 1 failure:

1) TuskPress\Tests\Architecture\DockerContractTest::test_smoke_validates_project_identity_from_compose_json_config
Failed asserting that the smoke script contains "config --format json".

FAILURES!
Tests: 9, Assertions: 50, Failures: 1.
EXIT_CODE=1
```

### GREEN — focused architecture suite

Command:

```text
.\vendor\bin\phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php
```

Output:

```text
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.10
Configuration: C:\Users\wende\Projects\tusk\.worktrees\tuskpress-roadrunner\phpunit.xml

.........                                                           9 / 9 (100%)

Time: 00:00.010, Memory: 10.00 MB

OK (9 tests, 53 assertions)
EXIT_CODE=0
```

### GREEN — full relevant test suite

Command:

```text
composer test
```

Output:

```text
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.10
Configuration: C:\Users\wende\Projects\tusk\.worktrees\tuskpress-roadrunner\phpunit.xml

......................                                            22 / 22 (100%)

Time: 00:00.277, Memory: 16.00 MB

OK (22 tests, 94 assertions)
EXIT_CODE=0
```

## Self-review and scope

- `git diff --check` completed with no whitespace errors. Git emitted only its line-ending advisory (LF will be replaced by CRLF).
- Reviewed the diff: only the requested Compose identity guard and architecture contract changed; existing cleanup protection and other smoke behavior are preserved.
- Docker and the live smoke script were not run.

## Commit

Subject: `fix: validate smoke project with Compose config JSON`
