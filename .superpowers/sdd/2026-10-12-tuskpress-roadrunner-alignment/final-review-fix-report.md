# Consolidated final-review fix wave

Base: `23b31679b935c5f4df9b0c3c88558faac2117482`. Existing branch/worktree retained. No subagents, pushes, PRs, or merges. Plan, spec, and ledger read before editing. `.phpunit.cache/` preserved; test commands use `--do-not-cache-result`.

Final status: all seven findings addressed and self-reviewed. PHP suite 49 tests / 228 assertions PASS; reviewed PHPStan gate PASS; Composer validation PASS; actual expanded container smoke PASS; CI YAML and Bash syntax PASS. Local code commits `d5dc5af` and `c0dea89`; this report is committed separately. Remaining concerns: 97 explicitly inventoried legacy PHPStan findings, deferred CSRF, and unobserved hosted CI execution.

## Preflight

- Main checkout `C:/Users/wende/Projects/tusk/tuskpress` was clean. `git pull --ff-only origin main`: already up to date; local main and origin/main both `9cf4451e1f6279d8fbb52e8140dc632cd26f9a58`.
- `composer test -- --do-not-cache-result`: PASS, 27 tests / 128 assertions (PHP 8.5.10).
- The specified report did not exist at start; created here. Full prior review conversation is unavailable; the seven exact findings in the user request and ledger govern this wave.

## 1. PHP session identity

Root cause: cookie-less PSR requests never assign an empty ID; `session_write_close()` retains the prior ID. Exceptions bypass all post-handler cleanup. Native cookie/global handling is inappropriate for successive PSR requests.

- RED `php vendor/bin/phpunit --do-not-cache-result tests/Runtime/SessionLifecycleTest.php`: 3 tests / 6 assertions / 3 failures: inherited authenticated user; A and B share a cookie; handler exception leaves PHP_SESSION_ACTIVE.
- GREEN same command: PASS, 3 tests / 17 assertions. Tests use real PHP file sessions and Framework LifecycleManager across successive requests with request-scoped SessionGuard.
- `composer test -- --do-not-cache-result`: PASS, 30 tests / 145 assertions.
- Fix: reset ID and data before every request; accept only validated PSR session cookies; disable native cookie emission/global cookie reads; strict session IDs; close/reset in finally around session start and handler.

## 2. Doctrine request state

Root cause: bootstrap pins one EntityManager instance and one user provider; default singleton repositories/consumers retain its UnitOfWork and an option-value cache. No end hook or closed-manager recovery exists.

- RED `php -d extension=pdo_sqlite vendor/bin/phpunit --do-not-cache-result tests/Runtime/DoctrineLifecycleTest.php`: 3 tests / 6 assertions / 3 failures (managed entity retained, stale option value, closed manager retained).
- GREEN same command with `--display-warnings`: PASS, 3 tests / 9 assertions, no warnings. Real Doctrine ORM + SQLite file database: identity map detachment, fresh read after direct SQL update, actual repository write after failed/closed-manager request. Initial fixture cleanup warnings were identified as an unused SQLite connection holding the file; fixture now closes that connection explicitly.
- Full `php -d extension=pdo_sqlite vendor/bin/phpunit --do-not-cache-result`: PASS, 33 tests / 154 assertions.
- Fix: request-scoped EntityManager decorator with Framework start/end hooks, explicit close/rollback/connection release; request-scoped repositories and transitive consumers; remove pinned user-provider/manager instances; registry's singleton health check obtains a separate lazy connection from the factory and closes it. Factory remains stateless singleton. Start hook handles a manager closed during bootstrap; next request resolves a fresh open manager.
- SQLite is available locally but disabled in the default CLI INI; focused/full ORM runs explicitly enable it. CI/development test prerequisites will declare it in finding 6.

## 3. Event listeners

Root cause: ListenerProvider defaults to an empty map; bootstrap never invokes Framework's EventCompiler discovery.

- RED `php vendor/bin/phpunit --do-not-cache-result tests/Architecture/EventDispatchTest.php`: 1 test / 1 assertion / 1 failure, no AuditLogger output.
- Fix: supply EventCompiler's discovered listener map to the Framework provider. AuditLogger uses the platform temporary directory instead of Linux-only `/tmp`, with locked append. Test dispatches real PostSavedEvent through the bootstrapped dispatcher and verifies the existing listener's output with a unique marker.

Finding 3 GREEN: `php -d extension=pdo_sqlite vendor/bin/phpunit --do-not-cache-result tests/Architecture/EventDispatchTest.php`: PASS 1 test / 2 assertions; full suite PASS 34 tests / 156 assertions.

## 4. Installer safety

Root cause: only GET checks installation status. POST always executes SchemaTool and writes settings/user; no coordination exists between workers. Settings were flushed independently, with siteurl written before administrator creation.

- Initial test harness error (wrong Request constructor) corrected before any production edit.
- RED `php -d extension=pdo_sqlite vendor/bin/phpunit --do-not-cache-result tests/Runtime/InstallationSafetyTest.php`: 2 tests / 3 assertions / 2 failures; both second POST and contending install returned 302 instead of 409.
- GREEN same command: PASS 3 tests / 15 assertions (including additional first-run validation). Installed POST changes no settings/user/schema; real held file lock blocks another SQLite installer before tables exist; first-run actually creates schema, settings, administrator/password, then rejects repeat.
- Full suite PASS 37 tests / 171 assertions.
- Fix: lock before installation check/schema mutation; fail closed on database errors except missing options table; MySQL DB-scoped GET_LOCK prevents concurrent installers across workers/hosts; SQLite application file lock for integration tests. MySQL DDL precedes a transaction; settings, administrator, and final siteurl completion marker commit atomically. Lock releases in finally.
- Narrowing GET's existing missing-database tolerance initially caused ApplicationBootstrapTest::test_existing_admin_install_route_is_registered to return 500 (36 tests / 164 assertions / 1 failure); restored GET behavior. POST retains the stricter fail-closed guard.
- MySQL lock/first-run behavior will be checked with container smoke; SQLite support is a test fixture, not a new advertised deployment target.

## 5. Repeated theme rendering

Root cause: header/footer markup uses require_once, whose included-file registry lasts the entire worker process.

- Harness setup errors: child PHP processes did not inherit `-d extension=pdo_sqlite`; supplied a task-local PHP_INI_SCAN_DIR (sqlite.ini). Post fixture also needed a positive PostId; corrected before production edits.
- RED `php vendor/bin/phpunit --do-not-cache-result tests/Runtime/ThemeRenderTest.php` with that INI scan directory: 4 tests / 20 assertions / 4 failures, each actual index/single/page/404 template missing header on render 2.
- GREEN same command: PASS 4 tests / 32 assertions, both renders include actual header/footer markup.
- `composer test -- --do-not-cache-result` with test INI: PASS 41 tests / 203 assertions.
- Fix: per-render require for header/footer in all four templates; functions.php retains its once-only declaration behavior.

## Smoke environment preflight

Git Bash/WSL/Docker checks inside sandbox failed (MSYS CreateFileMapping error 5; WSL E_ACCESSDENIED; Docker pipe denied). Approved outside-sandbox check confirms Git Bash 5.2.26 and Docker 29.8.1 are available; no smoke pass claimed from launch checks.

## 6. PHPStan investigation

- `php vendor/bin/phpstan analyse src --level=8 --error-format=json --no-progress`: after fixes 1–5, exit 1 / 113 file findings / 0 global errors, captured in `phpstan-wave-raw.json`.
- Exported clean source snapshots using `git archive` at merge-base `9cf4451e1f6279d8fbb52e8140dc632cd26f9a58` and review-base `23b31679b935c5f4df9b0c3c88558faac2117482`. Analysed each snapshot's src at level 8 using the same locked installed dependencies/PHPStan (source comparison, not a claim about historical dependency behavior).
- `php vendor/bin/phpstan analyse .superpowers/sdd/2026-10-12-tuskpress-roadrunner-alignment/merge-base/src --level=8 --error-format=json --no-progress`: exit 1 / 114 file findings / 0 global errors (`phpstan-merge-base.json`).
- Same command for `review-base/src`: exit 1 / 112 file findings / 0 global errors (`phpstan-review-base.json`).
- Normalized path + identifier + exact message comparison: alignment removes missing sanitize_title, unused EntityManagerFactory connection, and invalid DatabaseHealthCheck Service provides argument; adds a HealthController return.type finding. Thus **111/112 findings match merge-base; one is branch-added**, not all 112 pre-existing.
- Wave initially adds InstallationLock resource|false property assignment (113). Fixed by validating fopen result before assignment; the level-8 failure was observed first.
- Integration-critical findings reproduced before edits: FrameworkIntegrationTest initially 3 tests / 2 assertions / 2 failures + 1 expected runtime Error (protected DBAL connect causes false health; anonymous PSR API POST bypasses authorization; registered-user listener calls nonexistent getters).
- GREEN focused command with SQLite INI: 3 tests / 5 assertions PASS. Full `composer test -- --do-not-cache-result`: 44 tests / 208 assertions PASS.
- Raw analysis after these fixes: exit 1 / 103 file findings / 0 global errors (`phpstan-reviewed.json`). HealthController's new return mismatch also fixed. No baseline yet at this checkpoint.
- Additional response-integration regressions: FrameworkIntegrationTest 5 tests / 7 assertions / 2 failures RED (lost X-WP-Total headers and RSS content type from unsupported third response-factory argument); fixing through supported withHeader API.

Comparison and integration fixes completed before establishing the reviewed baseline below.

### Finding 6 resolution

- FrameworkIntegrationTest response fixes GREEN: `php vendor/bin/phpunit --do-not-cache-result tests/Runtime/FrameworkIntegrationTest.php` with task SQLite INI: PASS 5 tests / 9 assertions; full suite PASS 46 tests / 212 assertions.
- Final raw level-8 run before adding config: exit 1 / **97 file findings / 0 global errors**, captured in `phpstan-final-raw.json`. All 97 match merge-base by path + identifier + exact message multiset; 0 new messages. Comparison deliberately uses current locked dependencies for both source snapshots.
- Every remaining finding is listed with line, identifier, exact message, and review rationale in `docs/superpowers/phpstan-reviewed-baseline.md`. Some are known real legacy CRUD/input edge cases, not false positives; these remain unresolved outside this runtime wave. Inventory is per-finding, not an aggregate exemption.
- `php vendor/bin/phpstan analyse src --level=8 --generate-baseline=phpstan-baseline.neon --no-progress`: PASS, generated exactly 97 reviewed errors. Each entry has anchored exact message, identifier, file path, and count; no blanket rule. `phpstan.neon` enables unmatched-entry errors and pins PHP 8.2 analysis semantics. CI runs `composer analyse` in the PHP 8.2/8.5 matrix, with SQLite enabled for ORM tests.
- StaticAnalysisContractTest RED: `php vendor/bin/phpunit --do-not-cache-result tests/Architecture/StaticAnalysisContractTest.php`: 2 tests / 2 assertions / 2 failures (missing config and missing CI command). GREEN same command: PASS 2 tests / 8 assertions. It actually runs PHPStan: reviewed source passes; adding a unique undefined function exits 1 and reports the new finding.
- `composer analyse`: PASS, `[OK] No errors` **with the explicit baseline**, not zero raw findings. `composer validate --strict`: PASS, manifest valid.
- Manual stale-baseline probe: a partial-path run skipped entries for excluded paths (exit 0; not proof of stale-entry rejection). Corrected probe includes the actual source and adds a synthetic absent exact message for SessionMiddleware: `php vendor/bin/phpstan analyse --configuration=.superpowers/sdd/2026-10-12-tuskpress-roadrunner-alignment/phpstan-stale-probe.neon --error-format=json --no-progress`: expected exit 1 / 1 `ignore.unmatched` finding. Output saved in `phpstan-stale-probe.json`.

## 7. Smoke failure logs and final verification

Root cause: cleanup writes only an ephemeral TMPDIR file and prints its path, so CI deletes the only diagnostics after the runner finishes.

- SmokeLogRetentionTest harness initially lacked Windows PATH tools (exit 127; corrected fixture environment before production edits). RED `php vendor/bin/phpunit --do-not-cache-result tests/Architecture/SmokeLogRetentionTest.php` outside sandbox: 1 test / 3 assertions / 1 failure, intentional Docker fixture exit 42 but diagnostic marker absent from output.
- GREEN same command: PASS 1 test / 6 assertions. Real Bash smoke executes controlled fake Docker/curl: failure exit retained, diagnostic printed, artifact file retained, unique-project cleanup recorded. Synthetic test resources are removed; no real Docker is invoked by this regression.
- Fix: dedicated overridable log directory, print logs before cleanup, CI always uploads log artifacts (7 days); cleanup still validates unique project identity. Cookie jar removed at exit. Shell fixtures are executable; `.gitattributes` preserves LF on Windows.
- Updated original source contracts after a full run reported 49 tests / 216 assertions / 3 failures: ComposerContractTest extension count, DockerContractTest README prerequisites, DockerContractTest login-URL count (now distinguishes login POST from anonymous redirect expectation). Focused `php vendor/bin/phpunit --do-not-cache-result tests/Architecture/ComposerContractTest.php tests/Architecture/DockerContractTest.php`: PASS 12 tests / 92 assertions.
- First real Git Bash smoke: image builds, platform checks pass, services healthy; exit 1 because tests are correctly excluded from the image and the MySQL probe was requested by image path. Actual failure logs printed and retained under `smoke-logs/tuskpress-smoke-20261013162000-32947-21209.logs`; all unique resources removed. Corrected to feed host probe via stdin, without packaging test files in production image.
- Final command: `$env:TUSKPRESS_SMOKE_LOG_DIR=<task>/smoke-logs; & 'C:/Program Files/Git/bin/bash.exe' tests/Smoke/compose-smoke.sh` outside sandbox: **exit 0**. Project `tuskpress-smoke-20261013162231-32988-30053`. Output: `MySQL installation lock: competing connection rejected without schema changes.` and `Smoke passed: healthz, concurrent-install lock, first installation, repeat POST rejection, login, authenticated/anonymous isolation, and repeated theme renders.` MySQL 8.4 and production PHP 8.3 image; initial installation stays functional. The second unauthenticated POST is 409; the original site title/admin credentials still work. Cookie-less admin request redirects to login; both frontend responses include header/footer.
- Cleanup output confirms both containers, network, uploads/database volumes removed. Read-only `docker ps --filter label=com.docker.compose.project=tuskpress-smoke-20261013162231-32988-30053 --format '{{.Names}}'` and equivalent `docker volume ls ... --format '{{.Name}}'`: exit 0, empty output.
- Full final suite (twice, including after self-review formatting): `$env:PHP_INI_SCAN_DIR=<task>/php-ini; composer test -- --do-not-cache-result` outside sandbox: **PASS 49 tests / 228 assertions**, no warnings/failures; final duration 16.121s. SQLite INI is task-local so parent and isolated child tests inherit the driver.
- Final `composer analyse`: PASS `[OK] No errors` against 97 reviewed entries; final `composer validate --strict`: valid; `git diff --check` initially exposed one extra inventory EOF blank line, removed before commit.
- CI YAML parser unavailable in system Python/Node; sandbox pip attempt could not resolve PyYAML. Installed PyYAML only in ignored task scratch via approved package access. Task-local `yaml.safe_load` command: exit 0, `CI YAML parsed: test, smoke`; matrix/static-analysis/artifact contracts verified. Git Bash `-n tests/Smoke/compose-smoke.sh tests/Smoke/fixtures/bin/docker tests/Smoke/fixtures/bin/curl`: exit 0.

## Separate self-review

Reviewed staged runtime and CI/test diffs, new lifecycle/lock classes, baseline exact match/count rules, listener execution, scope alias reset/transitive consumers, installer DDL/transaction/lock ordering, exception cleanup, actual two-render tests, and unique cleanup/artifact paths against the plan/spec and seven findings. No remaining Critical/Important issue in this fix wave was identified. Author self-review only, as requested; no subagent review.

Scope decisions: CSRF remains explicitly deferred to issue #1. Full WordPress parity, third-party plugins, broad migrations, dependency vulnerability audit, and performance claims remain outside this wave. Historical PHPStan source comparison is now evidenced; historical dependency compatibility is not claimed. The 97 inventoried legacy findings remain isolated known debt. Hosted GitHub Actions execution was not observed (no push/PR requested); local suite/gate/YAML and actual container smoke passed.

## Local commits / handoff

- `d5dc5afff5fb0bb361195a0bd8166921ca86ba82`: `fix: isolate persistent CMS requests and guard installation` (findings 1–5 and integration-critical code from 6).
- `c0dea8905f6823e02bb849aa6ab2d60fe375f322`: `ci: gate reviewed analysis and retain smoke diagnostics` (findings 6–7, inventory, regression gate, artifact retention, updated contracts).
- Report committed separately. Only task files staged; `.phpunit.cache/` preserved. No push/PR/merge.

## Post-PR hosted CI follow-up

- First run `38432451466` failed before job creation (0 jobs and no logs). GitHub's official context availability table shows `runner` is unavailable in `jobs.<job_id>.env`; move `TUSKPRESS_SMOKE_LOG_DIR` to the smoke step's `env`. A contract test was added test-first (RED at job-level placement, GREEN after step-level placement). The fix is commit `d3c5861`.
- Second run exposed a pre-existing misspelling in both setup steps: `shivammathai/setup-php@v2` instead of the published [`shivammathur/setup-php`](https://github.com/shivammathur/setup-php). Added a contract asserting exactly two correct references; observed RED before replacing both references. Fix commit follows.
- After these workflow fixes: full PHPUnit PASS 49 tests / 230 assertions, `composer analyse` PASS against the reviewed baseline, `composer validate --strict` valid, and `git diff --check` clean. Hosted Actions rerun is pending after the next push; local checks are not a green PR check.
