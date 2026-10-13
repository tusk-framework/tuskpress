# Task 1 report: Pin the published Framework package and establish tests

## Implementation

- Changed the project requirement to `tusk-framework/framework: ^1.0`, removed the project VCS repository override and `minimum-stability: dev`, and retained the application package identity `tuskpress/tuskpress` and GPL-2.0-or-later license.
- Updated `composer.lock` from Packagist. It now locks `tusk-framework/framework` at stable `v1.0.0` (source reference `7d11278907b9149d26ddf20477a009f5428b48dd`) and records stable minimum stability.
- Added a PHPUnit configuration for the `tests/` suite with Composer autoloading and wired `composer test` to use it.
- Added an architecture contract test for the package name/constraint, stable lock version, removed old alias, and absence of the VCS override and dev stability setting.
- Added GitHub Actions CI for pushes and pull requests, with PHP 8.2 and 8.5 and `fileinfo`, `mbstring`, and `pdo_mysql`; it validates Composer, installs dependencies, and runs `composer test`.

## TDD and verification evidence

1. Added `ComposerContractTest` before changing the manifest. The first PHPUnit run failed as expected: the `tusk-framework/framework` requirement was absent (`null`, expected `^1.0`).
2. The first dependency install against the original manifest/lock did not complete: local PHP lacked `ext-fileinfo`, and the old lock selected `symfony/string` 8.1.0, incompatible with the manifest's configured PHP 8.2.0 platform. No platform requirements were ignored. I used a temporary PHP configuration to enable the installed `fileinfo` extension for subsequent Composer and PHPUnit commands.
3. Packagist metadata showed the published package and `v1.0.0`; Composer update resolved that release and its dependencies. An intermediate test run identified Composer's `v` prefix in the locked version; the stable-version assertion was adjusted to allow that tag format.
4. Final `composer install --no-interaction` passed with optimized autoloading enabled in 5.17 seconds (PowerShell `Measure-Command`; output ended with `Generating optimized autoload files` and `69 packages ... looking for funding`).
5. `composer validate --strict` passed: `./composer.json is valid`.
6. `composer test` passed on PHP 8.5.10: `OK (1 test, 6 assertions)`.
7. Symfony YAML 8.1.8 parsed `.github/workflows/ci.yml` successfully: `workflow YAML syntax: valid`.
8. `git diff --check` passed. No `--ignore-platform-reqs` or equivalent was used.

The required red/green PHPUnit evidence was run using PHPUnit 11.5.57. A temporary isolated Composer tool directory supplied PHPUnit and Symfony YAML because the initial project lock could not be installed; `.tdd-tools/` and `.phpunit.cache/` were removed after verification. The project `vendor/` directory remains Composer-managed and ignored.

## Changed files

- `composer.json`
- `composer.lock`
- `phpunit.xml`
- `tests/Architecture/ComposerContractTest.php`
- `.github/workflows/ci.yml`
- `.superpowers/sdd/2026-10-12-tuskpress-roadrunner-alignment/task-1-report.md`

## Self-review

- Confirmed the manifest keeps the original project name and GPL license and now has the exact `^1.0` requirement without a VCS repository override or `minimum-stability: dev`.
- Confirmed the lock contains one `tusk-framework/framework` package at `v1.0.0`, sourced from its published v1.0.0 commit, and no `tusk/framework` package.
- Reviewed the complete manifest and new-file diffs and the lock's Composer validation and resolved package metadata. The lock refresh includes additional dependency updates from the full Packagist resolution (34 installs, 24 updates, one removal at resolution time); all locked dependencies install successfully.
- Confirmed CI triggers only on this repository's pushes and pull requests, uses the requested PHP matrix/extensions, and runs the same strict validation/install/test commands.
- Git Bash startup was attempted but unavailable in this environment: `bash.exe` failed with Windows `CreateFileMapping ... Win32 error 5`. PowerShell equivalents were used.

## Concerns and limits

- The local PHP 8.5.10 CLI configuration does not enable `fileinfo`; verification used a temporary `PHPRC` configuration that enabled the installed extension. CI explicitly enables it. The required CI matrix is configured but was not executed on hosted GitHub runners in this task.
- The lock update refreshed other dependencies within their declared constraints along with the Framework release; this was validated by Composer install and strict validation.

## Commit

Expected commit subject: `build: use the published Tusk Framework package`
