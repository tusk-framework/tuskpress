# Task 4 review round 4 evidence

Reviewed starting head: `30066ca60408f67edf4c8224015b1898901c9b6e`.

## Ruling and TDD evidence

- The approved Task 4 brief specifies `ghcr.io/roadrunner-server/roadrunner:v2025.1.15`, but the official GHCR package publishes `ghcr.io/roadrunner-server/roadrunner:2025.1.15` without the leading `v`, including amd64 and arm64 manifests. Source: [official RoadRunner GHCR package](https://github.com/roadrunner-server/roadrunner/pkgs/container/roadrunner). The brief/plan tag is incorrect; this correction keeps the specified version and image.
- RED: changed the Docker contract first to require the official no-v tag and reject the old tag. `vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php` failed as expected: 6 tests, 1 failure, on the Dockerfile's old tag.
- GREEN: after correcting the Dockerfile tag, focused PHPUnit passed: `OK (6 tests, 44 assertions)`.
- Full suite: `composer test` passed: `OK (19 tests, 85 assertions)`.
- `git diff --check` passed.

## Changes and handoff

- `Dockerfile`: changed only the RoadRunner image tag to `2025.1.15`.
- `tests/Architecture/DockerContractTest.php`: requires the no-v tag and explicitly rejects `v2025.1.15`.
- Docker build and shell smoke were not retried, per controller direction; the controller will retry elevated build/smoke after this fix. No Docker or shell cleanup was performed.
- The requested SDD report is outside the writable worktree; this round's evidence is recorded here pending an authorized report update.
- No plan document edits, push, or pull request.
