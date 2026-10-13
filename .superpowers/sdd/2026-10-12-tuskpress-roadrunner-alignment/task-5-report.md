# Task 5 implementation report

## Status

Implemented the Task 5 README, CI smoke gate, Composer command, and architecture contracts. The changes are prepared for commit as `docs: explain the supported TuskPress runtime and setup`.

## Changes

- Replaced the retired-runtime README with verified Docker first-run commands, installer-selected administrator credentials, volume and cleanup behavior, health/log commands, troubleshooting, local tooling prerequisites, limited feature claims, GPL-2.0-or-later, and the approved CSRF issue.
- Documented Tusk Engine as validating the application and managing RoadRunner, while RoadRunner owns HTTP serving and worker lifecycle; stated that the PHP application remains loaded across requests and that request state needs isolation.
- Kept the PHP 8.2/8.5 matrix, set workflow permissions to `contents: read`, and added a dependent Ubuntu Compose smoke job with non-empty job-scoped CI database credentials.
- Added Composer `test:smoke` delegating to `bash tests/Smoke/compose-smoke.sh` and contracts for README claims, workflow setup, and delegation.

## RED/GREEN evidence

Before changing README, workflow, or Composer, the focused PHPUnit contracts ran and failed as expected (2 tests, 4 assertions, 2 failures): the README lacked current setup details and the workflow lacked read-only permissions and smoke gating.

After implementation, focused contracts passed: 2 tests, 19 assertions. Full `composer test` passed: 27 tests, 116 assertions.

## Verification

- `composer validate --strict` — passed (`./composer.json is valid`).
- Focused documentation/CI contracts — passed (2 tests, 19 assertions).
- `composer test` — passed (27 tests, 116 assertions).
- `composer analyse` — completed with exit code 1 and 112 existing PHPStan findings across `src/`. Findings include missing iterable value types, incompatible return/argument types, undefined methods/constants, and unused/incorrect property types. No baseline or suppression was added; application source is outside this task's allowed scope.
- `composer test:smoke` — could not start because the controller denied Bash process creation (`Bash/Service/CreateInstance/E_ACCESSDENIED`). Docker is installed, but the script did not run and produced no Docker-level smoke result in this environment. Task 4's prior end-to-end Docker/Git Bash smoke evidence is recorded in the SDD progress ledger.
- Workflow syntax — inspected the YAML structure, job indentation, keys, expressions, and step mappings. No YAML parser was available in the installed Node/Python dependencies, so parser-based validation was unavailable.
- `git diff --check` — passed; only line-ending normalization warnings were printed.

## Limitations and concerns

The requested README, CI, Composer, and contract changes are in scope. The full app still has the 112 PHPStan findings noted above. This Windows controller cannot launch the Bash smoke script, so the smoke gate was not rerun here. Workflow syntax was reviewed by inspection only.

## Commit

`docs: explain the supported TuskPress runtime and setup`

## Self-review

- Reviewed the full staged diff against the Task 5 brief; application/runtime, security middleware, installer, Dockerfile, Compose, and smoke script behavior are unchanged.
- Confirmed workflow keeps the PHP 8.2/8.5 matrix and requests only `contents: read`; the smoke job needs the matrix job and uses job-scoped non-empty CI values.
- Confirmed `.env.example` values satisfy Compose interpolation and are described as local development values. README cleanup instructions distinguish preserving volumes from deleting them.
- Confirmed the CSRF text states the middleware does not protect forms and points to the already approved issue.
- `git diff --check` reports no whitespace errors.
