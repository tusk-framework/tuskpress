# Task 2 report: Framework application composition

## Status

Implemented and verified on base commit `9310a80`. The implementation is limited to Task 2; no Task 3 request-scope or runtime-ownership changes were made.

## TDD evidence

Added `tests/Architecture/ApplicationBootstrapTest.php` before production edits and ran the requested command:

```text
vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/ApplicationBootstrapTest.php
```

The first run failed before any production edits because the legacy bootstrap could not resolve `Doctrine\ORM\EntityManagerInterface` (the original `bootstrap/app.php` failed while resolving `MySqlUserRepository`). Running each case in an isolated process exposed the complete red state:

```text
vendor/bin/phpunit --configuration phpunit.xml --process-isolation tests/Architecture/ApplicationBootstrapTest.php
EEEEE
Tests: 5, Assertions: 0, Errors: 5.
```

Four app/route tests failed on the missing EntityManager binding; the factory test failed because `EntityManagerFactory` still required `Connection`. The isolated rerun was needed because the old bootstrap declared a global `RuntimeCompiledContainer` class and could not be included repeatedly in one process.

After implementation, the original exact command passes in the current suite setup. The explicit `--process-isolation` command also passed the five initial contracts during focused validation. Additional tests cover service registrations and route collisions at application creation.

## Implementation

- `bootstrap/app.php` loads `.env` before building the app and returns `Tusk\Foundation\Application` via `Application::configure()`, `withControllers()`, `withProviders()`, and `create()`.
- `bootstrap/providers.php` scans `src/` using Framework `ServiceScanner` metadata, registers services and required bindings, adds the DB health check to `HealthCheckRegistry`, configures middleware in legacy order, and retains best-effort plugin/theme loading when initial-install tables are absent.
- All existing route controllers and the new system health controller have Framework `#[Controller]` attributes. `/healthz` returns `ok` without database access, and install-check middleware permits it before schema initialization.
- `EntityManagerFactory` no longer depends on the eager PDO `Connection`; its Doctrine DBAL connection remains unconnected after factory invocation. Existing PDO-backed repositories still request `Connection` through their own constructors.
- Removed a redundant `/wp-admin/` route declaration that normalized to the existing `/wp-admin` route. Framework v1.0.0 rejects that collision during application creation, as required.
- Legacy compatibility constants are guarded so repeated bootstrap use does not emit PHP warnings.

## Verification

`composer test`:

```text
OK (8 tests, 23 assertions)
```

Also verified PHP syntax for bootstrap, provider, architecture tests, and controller files; `git diff --check` passed. Representative `GET /`, `GET /wp-admin`, and `GET /wp-json/wp/v2/posts` requests are routable (non-404); `/healthz` is 200 with body `ok`; the install route is available without starting a worker. Duplicate normalized routes fail during `ApplicationBuilder::create()`.

## Changed files

- `.superpowers/sdd/2026-10-12-tuskpress-roadrunner-alignment/task-2-report.md`
- `bootstrap/app.php`
- `bootstrap/providers.php`
- `src/Http/Controllers/Admin/AdminAuthController.php`
- `src/Http/Controllers/Admin/AdminCommentController.php`
- `src/Http/Controllers/Admin/AdminDashboardController.php`
- `src/Http/Controllers/Admin/AdminMediaController.php`
- `src/Http/Controllers/Admin/AdminPluginController.php`
- `src/Http/Controllers/Admin/AdminPostController.php`
- `src/Http/Controllers/Admin/AdminSettingsController.php`
- `src/Http/Controllers/Admin/AdminThemeController.php`
- `src/Http/Controllers/Admin/AdminUserController.php`
- `src/Http/Controllers/Admin/InstallController.php`
- `src/Http/Controllers/Api/ApiMediaController.php`
- `src/Http/Controllers/Api/ApiPostController.php`
- `src/Http/Controllers/Api/ApiTermController.php`
- `src/Http/Controllers/Api/ApiUserController.php`
- `src/Http/Controllers/Frontend/FeedController.php`
- `src/Http/Controllers/Frontend/FrontendController.php`
- `src/Http/Controllers/System/HealthController.php`
- `src/Http/Middleware/InstallCheckMiddleware.php`
- `src/Infrastructure/Database/EntityManagerFactory.php`
- `src/Infrastructure/Health/DatabaseHealthCheck.php`
- `tests/Architecture/ApplicationBootstrapTest.php`

## Self-review and concerns

- Middleware registration order is preserved: exception, install check, session, CSRF, security, API auth.
- Bootstrap provider resolves the EntityManager object, but Doctrine DBAL does not connect until an operation explicitly connects; this is asserted by the factory test.
- Existing `MySqlMediaRepository`, `MySqlTermRepository`, and `MySqlCommentRepository` still depend on the legacy PDO `Connection`, which remains registered from service metadata and is resolved only when those repositories are requested.
- Route discovery made a preexisting `/wp-admin` and `/wp-admin/` collision visible; removing the trailing-slash duplicate preserves the same normalized route behavior.
- Git Bash startup was unavailable in this environment; all repository commands and editing used PowerShell equivalents. No push or PR was created.

## Commit

Required subject: `refactor: compose TuskPress with the Framework application`.

## Round 1 review fixes (2026-10-13)

### TDD evidence

Updated the regression tests first, then ran:

```text
vendor/bin/phpunit --configuration phpunit.xml --process-isolation tests/Architecture/ApplicationBootstrapTest.php
```

Red output against the reviewed production code:

```text
...F....
Tests: 8, Assertions: 23, Failures: 1.
```

The failure was the exact legacy admin path assertion: expected `Location: /wp-admin`, received `Location: /wp-admin/install.php`. The other new assertions passed, including the install route match and schema-less install form rendering.

After production fixes, the focused command passed:

```text
OK (8 tests, 23 assertions)
```

Then `composer test` passed:

```text
OK (9 tests, 29 assertions)
```

### Changes and review notes

- GET `/wp-admin/` now redirects to `/wp-admin` before install-state checks; the test asserts both status `302` and exact `Location`.
- Route existence tests now use the registered `RouterInterface::match()` for frontend, admin, API, and install routes. The installer request also proves the schema-less form renders with status `200` and `TuskPress Installation` in its body.
- Collision tests use a random per-test fixture directory, assert directory creation and complete source write, and only remove their own file/directory. Cleanup is protected around setup and app creation; only the expected duplicate-route `RuntimeException` is treated as success.
- Provider DB tolerance is limited to the two `MySqlOptionRepository::get()` calls for active plugins and active theme. It catches Doctrine DBAL exceptions and raw `PDOException` from metadata/driver lookup, falls back to empty plugins and the default theme, then runs plugin loading and theme file loading outside the catches so those failures are not swallowed. `ThemeLoader::loadTheme()` separates DB lookup from file initialization.
- The no-initialized-schema behavior is tested through application construction and by requesting the installation form; the form’s controller intentionally handles schema lookup failure and still renders.
- Authentication trace: Framework `HttpKernel::handle()` matches the route and attaches `_controller` and `_action` before building/running middleware. Framework `SecurityMiddleware::process()` reads these attributes and calls its attribute check, enforcing controller/method `#[Authenticated]`. No extra auth implementation was needed.
- Review changes did not add request scopes, worker removal, front-controller changes, or runtime ownership behavior.

### Round 1 files

- `bootstrap/providers.php`
- `src/Http/Middleware/InstallCheckMiddleware.php`
- `src/Theme/ThemeLoader.php`
- `tests/Architecture/ApplicationBootstrapTest.php`
- `.superpowers/sdd/2026-10-12-tuskpress-roadrunner-alignment/task-2-report.md`

### Environment

Git Bash startup remains unavailable; commands were run with PowerShell. No agents were dispatched and nothing was pushed.
