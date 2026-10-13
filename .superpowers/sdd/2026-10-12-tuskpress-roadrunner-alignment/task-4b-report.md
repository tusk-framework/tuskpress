# Task 4b implementation report

## Scope

Added Debian `unzip` to the production image's existing apt package installation so Composer can extract `--prefer-dist` archives. Added an architecture contract that requires `unzip` in that install command. Existing PHP extensions, package versions, and apt cache cleanup are unchanged.

## TDD evidence

At starting HEAD `27c59358e0fc9f76a02b7e7d3527a5a62d0fe15c`, added `test_dockerfile_installs_archive_extractor_for_composer_dist_archives` and ran:

```text
vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php
```

Expected red result: 1 failure, 7 passing tests. The new assertion failed because the Dockerfile's apt install command contained `libonig-dev` but not `unzip`.

Updated the apt install command to include `libonig-dev unzip`, preserving `rm -rf /var/lib/apt/lists/*` and all existing PHP extensions.

## Verification

- Focused architecture tests: `vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php` — passed, 8 tests, 48 assertions.
- Full suite: `composer test` — passed, 21 tests, 89 assertions.
- `git diff --check` — passed. Git emitted only the existing-platform LF-to-CRLF working-copy notices for `Dockerfile` and `tests/Architecture/DockerContractTest.php`.
- Docker, Bash, and smoke checks were not run, as directed.

## Result

Composer archive extraction is available in the production build through Debian `unzip`. No other dependency or platform requirement was changed.
