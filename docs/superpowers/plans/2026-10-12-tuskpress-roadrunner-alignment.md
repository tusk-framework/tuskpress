# TuskPress RoadRunner Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make TuskPress a reproducible reference application that boots through the published Tusk Framework application contract and runs under Tusk Engine-managed RoadRunner.

**Architecture:** `bootstrap/app.php` composes a Framework `Application`; application providers register TuskPress services and middleware; the Engine generates the private RoadRunner worker; RoadRunner owns serving and worker lifecycle. Docker copies versioned Engine and RoadRunner binaries into a PHP CLI image, while Compose supplies MySQL and persistent data.

**Tech Stack:** PHP 8.2+, Composer, PHPUnit, Tusk Framework v1.0.0, Tusk Engine v0.3.4, RoadRunner v2025.1.15, Docker Compose, MySQL 8.4 LTS.

**Spec:** `docs/superpowers/specs/2026-10-12-tuskpress-roadrunner-alignment-design.md`

## Global Constraints

- `bootstrap/app.php` returns `Tusk\Foundation\Application` and does not start a server or request loop.
- The application contains no hand-authored root `worker.php`; `.tusk/runtime/worker.php` is private Engine-generated state.
- RoadRunner owns HTTP serving, Goridge, worker pools, recycling, and process shutdown; Tusk Engine owns validation and process lifecycle.
- Preserve CMS routes, authentication middleware, plugin/theme loading, persistence, and first-run installation. Do not describe the current CSRF no-op as protection; track real CSRF protection separately.
- Composer uses published `tusk-framework/framework` with a stable compatible constraint and a committed lockfile; do not use `--ignore-platform-reqs`.
- Container inputs use Tusk Engine v0.3.4 and the official RoadRunner v2025.1.15 image; do not use host-built binaries or mutable `latest` tags.
- Compose uses MySQL 8.4 LTS; do not use the end-of-life MySQL 8.0 series.
- Compose credentials are configurable, non-empty, and stored outside tracked files; MySQL data and uploaded media persist in named volumes.
- Long-lived request state is cleared between requests; stateful TuskPress services use the Framework request scope.
- Do not claim WordPress source-code compatibility, automatic credentials, or performance figures not demonstrated by tests.

## Review Focus

- Composer package identity/lock mismatch: test that the manifest and lock resolve `tusk-framework/framework` at a stable release, not `tusk/framework` or `dev-main` (Task 1).
- Legacy service factories and route discovery: test app composition resolves required interfaces and discovers representative frontend, admin, and API routes with class-level `#[Controller]` attributes (Task 2).
- CSRF middleware is currently a no-op: documentation must not present it as protection, and a separate security issue must be tracked (Task 5).
- Persistent identity leakage across requests: test that a request-scoped session guard is recreated after Framework scope reset and controllers do not retain it as singletons (Task 3).
- Container masking and fresh install: verify real PHP platform requirements, absence of a bind mount over `/app/vendor`, database readiness, installation, login, and an authenticated admin page in one Compose smoke test (Task 4).

---

### Task 1: Pin the published Framework package and establish tests

**Files:**
- Modify: `composer.json` — correct the package identity and stable version constraint.
- Modify: `composer.lock` — resolve the published package release.
- Create: `phpunit.xml` — configure the app test suite.
- Create: `tests/Architecture/ComposerContractTest.php` — pin the package identity/stability contract.
- Create: `.github/workflows/ci.yml` — add PHP compatibility tests for PHP 8.2 and 8.5.

**Interfaces:**
- Consumes: Packagist package `tusk-framework/framework` and the existing Tusk Framework v1.0.0 public API.
- Produces: a locked, reproducible Composer installation, `composer test`, and an initial CI workflow.

- [ ] **Step 1: Write the failing manifest contract test**

  Add `test_project_requires_the_published_stable_framework_package` to parse
  `composer.json` and `composer.lock`; assert the requirement key is
  `tusk-framework/framework`, the constraint is `^1.0`, the lock package has
  the same name and a stable 1.x release, and no project VCS repository or
  `minimum-stability: dev` remains.

- [ ] **Step 2: Install the currently locked test dependencies**

  Run: `composer install --no-interaction`
  Expected: the existing lockfile installs PHPUnit and the current package set
  into ignored `vendor/`, so the new test can execute.

- [ ] **Step 3: Run the test and verify it fails on the current manifest**

  Run: `vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/ComposerContractTest.php`

  Expected: FAIL because the project currently requires `tusk/framework:*`
  and the lockfile contains `tusk/framework` at `dev-main`.

- [ ] **Step 4: Correct the Composer package declaration and lockfile**

  Require `tusk-framework/framework:^1.0`, remove the repository override and
  dev stability flags if no remaining dependency needs them, then update the
  lockfile against Packagist. Keep the application package name and GPL license
  unchanged.

- [ ] **Step 5: Add PHPUnit configuration and implement the manifest test**

  Configure `tests/` as the suite, include Composer autoloading, and make
  `composer test` run PHPUnit with that configuration.

- [ ] **Step 6: Verify Composer and tests**

  Run: `composer validate --strict`
  Expected: valid manifest with no warnings.

  Run: `composer install --no-interaction`
  Expected: success without ignored platform requirements.

  Run: `composer test`
  Expected: PASS, including the package identity contract.

- [ ] **Step 7: Add CI matrix and commit**

  Run Composer validation/install/tests on PHP 8.2 and 8.5 with the required
  extensions (`fileinfo`, `mbstring`, `pdo_mysql`). Verify the workflow YAML
  parses and the workflow is limited to pushes and pull requests for this repo.

  Commit: `build: use the published Tusk Framework package`

### Task 2: Compose the application through Framework providers

**Files:**
- Modify: `bootstrap/app.php` — return the configured Framework Application.
- Create: `bootstrap/providers.php` — register application services, event bindings, and middleware.
- Create: `src/Http/Controllers/System/HealthController.php` — provide a dependency-free readiness route.
- Modify: `src/Http/Controllers/**/*.php` — mark route classes with the Framework's `#[Controller]` discovery attribute.
- Modify: `src/Http/Middleware/InstallCheckMiddleware.php` — allow the health route before installation.
- Modify: `src/Infrastructure/Database/EntityManagerFactory.php` — create Doctrine's lazy DBAL connection without eagerly constructing the legacy PDO wrapper.
- Create: `tests/Architecture/ApplicationBootstrapTest.php` — verify return type, route wiring, and middleware/service registration.

**Interfaces:**
- Consumes: `Application::configure(string): ApplicationBuilder`,
  `withControllers(string ...$directories)`,
  `withProviders(array $paths)`, `ApplicationBuilder::create()`, and provider
  closures receiving `Tusk\Core\Container\Container`.
- Produces: a bootstrap returning `Tusk\Foundation\Application`, registered
  HTTP controllers, Framework-managed request handling, and `/healthz`.

- [ ] **Step 1: Write failing application contract tests**

  Add tests named `test_bootstrap_returns_a_framework_application`,
  `test_health_route_is_available_without_database_tables`, and
  `test_existing_admin_install_route_is_registered`, and
  `test_entity_manager_factory_does_not_connect_during_bootstrap`. Assert the
  first returns `Tusk\Foundation\Application`; the health route returns
  status 200 and body `ok`; the install route is routable without starting a
  worker; and the EntityManager's DBAL connection remains disconnected after
  factory invocation.

- [ ] **Step 2: Run the focused tests and verify the expected failures**

  Run: `vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/ApplicationBootstrapTest.php`

  Expected: current bootstrap returns `HttpKernel`, and no app route contract
  or PHPUnit fixture for the Framework Application exists.

- [ ] **Step 3: Register TuskPress services and Framework bridges**

  In `bootstrap/providers.php`, return a callable taking the Framework
  `Container`. Register service classes discovered under `src/` using the
  existing `ServiceScanner` metadata and `Container::register`; bind the
  Doctrine `EntityManagerInterface`, PSR event dispatcher/listener provider,
  user-provider interface, database health check, and plugin/theme services.
  Add the existing middleware to `HttpKernel` in the same order as the legacy
  bootstrap. Register the database health check in the Framework's existing
  `HealthCheckRegistry`. Preserve the current behavior of tolerating missing
  application tables during initial installation.

- [ ] **Step 4: Convert the bootstrap and register the health route**

  Load `.env` before application construction. Configure
  `Application::configure(dirname(__DIR__))` with
  `withControllers('src/Http/Controllers')` and
  `withProviders(['bootstrap/providers.php'])`; return `create()`. Add a
  class-level `#[Controller]` attribute to each route controller (their
  existing `#[Route]` paths are absolute). Add a `HealthController` with
  `#[Controller]`/`#[Route('/healthz')]` that returns `Response::html('ok')`
  without database access. Update `InstallCheckMiddleware` to allow `/healthz`
  without an initialized schema.

  Change `EntityManagerFactory` to have no `Connection` constructor dependency;
  keep Doctrine's DBAL connection lazy so application composition succeeds
  before the database schema exists. Legacy services that explicitly need the
  PDO wrapper continue resolving `Connection` on demand.

- [ ] **Step 5: Run focused tests and validate existing routes**

  Run: `composer test`
  Expected: PASS for bootstrap type, health response, and install route.

  Add assertions for representative frontend, admin, and API route matches;
  verify route collisions fail during application creation rather than at
  request time.

- [ ] **Step 6: Commit**

  Commit: `refactor: compose TuskPress with the Framework application`

### Task 3: Remove the app-owned runtime and isolate request state

**Files:**
- Modify: `public/index.php` — delegate to the Application and emit PSR-7 responses.
- Modify: `src/Security/SessionGuard.php` — use Framework request scope.
- Create: `src/Http/Middleware/RequestScopedSecurityMiddleware.php` — scope the Framework security middleware and its guard per request.
- Modify: `bootstrap/providers.php` — register `Tusk\Security\Authorization\Gate` in request scope and use the app adapter.
- Modify: `src/Http/Middleware/ApiAuthMiddleware.php` — request scope if it retains auth state.
- Modify: `src/Http/Controllers/**/*.php` — mark controllers holding request-scoped services as request-scoped.
- Modify: `.gitignore` — exclude `.tusk/` runtime state.
- Delete: `worker.php` — remove the obsolete native NDJSON entrypoint.
- Create: `tests/Runtime/RequestIsolationTest.php` — pin per-request session guard isolation.

**Interfaces:**
- Consumes: Framework Application `handle(ServerRequestInterface)` and the
  runtime lifecycle's request-scope reset.
- Produces: no user-owned worker loop; a conventional front controller and
  stateful request services that are not retained across RoadRunner requests.

- [ ] **Step 1: Write the failing scope regression test**

  Add `test_session_guard_instance_is_recreated_after_request_scope_reset` and
  `test_security_middleware_does_not_retain_a_guard_between_requests`.
  Register a fake `UserProviderInterface` and `SessionGuard`, set a user on the
  first guard, call `Container::resetScope('request')`, then assert the second
  guard is a distinct instance and is unauthenticated. Resolve the configured
  security middleware before and after reset and assert its request scope is
  also recreated.

- [ ] **Step 2: Run the test and verify it exposes the singleton scope**

  Run: `vendor/bin/phpunit --configuration phpunit.xml tests/Runtime/RequestIsolationTest.php`

  Expected: FAIL because `SessionGuard` currently uses the default singleton
  scope.

- [ ] **Step 3: Mark stateful services and controllers request-scoped**

  Change `SessionGuard` to `#[Service(scope: 'request')]`. Add
  `RequestScopedSecurityMiddleware extends Tusk\Security\Middleware\SecurityMiddleware`
  with `#[Service(scope: 'request')]`, and register this adapter instead of the
  Framework singleton. Register `Tusk\Security\Authorization\Gate` with
  request scope because it also retains the guard. Mark all route controllers with injected
  request-scoped guards as `request` scope. Keep stateless repositories and
  middleware at their existing scope.

- [ ] **Step 4: Adapt the conventional front controller**

  Require the Composer autoloader, load `bootstrap/app.php`, create the request
  with `Request::fromGlobals()`, call `Application::handle()`, emit status and
  headers, and stream the PSR-7 response body. Preserve static-file passthrough
  for PHP's built-in server; do not start RoadRunner here.

- [ ] **Step 5: Remove the native worker and ignore generated state**

  Delete the root `worker.php`; add `.tusk/` to `.gitignore`. Add an architecture
  test `test_project_does_not_own_a_runtime_worker` that asserts the root file
  is absent; README ownership documentation is verified in Task 5.

- [ ] **Step 6: Run tests and commit**

  Run: `composer test`
  Expected: PASS, including request-scope reset and front-controller contract.

  Commit: `refactor: delegate TuskPress runtime to RoadRunner`

### Task 4: Make Docker Compose reproducible and smoke-test installation

**Files:**
- Modify: `Dockerfile` — copy pinned Engine/RoadRunner binaries into a PHP CLI image and verify platform requirements.
- Modify: `docker-compose.yml` — configure MySQL readiness, credentials, and persistent volumes without masking `vendor/`.
- Delete: `init.sql` — remove the stale pre-seeded schema that conflicts with the app's Doctrine installer.
- Create: `.env.example` — safe local-development configuration with non-empty credentials.
- Create: `tests/Smoke/compose-smoke.sh` — exercise readiness, installer, and admin login in an isolated Compose project.
- Create: `.dockerignore` — exclude host dependencies, local environment files, and generated runtime state from build context.

**Interfaces:**
- Consumes: the application bootstrap and `/healthz` endpoint from Task 2,
  `tusk start` from Engine v0.3.4, RoadRunner v2025.1.15, and locked Composer
  dependencies from Task 1.
- Produces: a production-like local Compose deployment using `tusk start`,
  with a stable health signal and disposable smoke-test resources.

- [ ] **Step 1: Write the failing Docker contract checks**

  Add architecture checks named `test_dockerfile_does_not_copy_local_engine_binary`,
  `test_dockerfile_does_not_ignore_composer_platform_requirements`, and
  `test_compose_does_not_bind_mount_over_vendor`. Assert pinned image tags,
  `tusk start` without a worker argument, and explicit platform validation.

- [ ] **Step 2: Run the checks and verify they fail on current files**

  Run: `vendor/bin/phpunit --configuration phpunit.xml tests/Architecture/DockerContractTest.php`

  Expected: FAIL on local binary copy, ignored platform requirements, legacy
  worker command, and bind mount masking the image's `/app/vendor`.

- [ ] **Step 3: Build a pinned RoadRunner runtime image**

  Use `php:8.3-cli-bookworm` as the runtime base; copy `/usr/local/bin/tusk`
  from `ghcr.io/tusk-framework/tusk-engine:v0.3.4`, `/usr/bin/rr` from
  `ghcr.io/roadrunner-server/roadrunner:v2025.1.15`, and Composer 2 from its
  official image. Install `mbstring` and `pdo_mysql`; install locked production
  dependencies with optimized autoloading; run
  `composer check-platform-reqs --no-dev`;
  expose 8080 and start with `CMD ["tusk", "start"]`.

- [ ] **Step 4: Configure Compose and secrets/data boundaries**

  Remove the whole-project bind mount, fixed container names, and SQL seed
  mount. The application installer is the sole schema-creation path. Read
  MySQL 8.4 LTS database/user/password/root-password from environment
  variables, require non-empty passwords, wait for the MySQL healthcheck, and
  retain database files in a named volume. Mount a separate named uploads
  volume. Add `.env`
  copy/setup guidance to README and keep `.env` excluded from source control.

- [ ] **Step 5: Implement the isolated Compose smoke script**

  Start Compose under a unique project name, wait for both health states,
  assert `/healthz`, GET `/wp-admin/install.php`, submit an installer form,
  authenticate with the credentials selected in the test, and assert an admin
  page response. Use a cleanup trap scoped only to that unique project and its
  named volumes; never remove a developer's default Compose volumes.

- [ ] **Step 6: Run Docker checks and commit**

  Run: `docker compose build`
  Expected: successful build with no ignored platform requirements.

  Run: `bash tests/Smoke/compose-smoke.sh`
  Expected: the installer completes and an authenticated admin request
  succeeds; the isolated smoke project and volumes are removed afterward.

  Commit: `build: run TuskPress on pinned RoadRunner images`

### Task 5: Document the real setup and enforce it in CI

**Files:**
- Modify: `README.md` — accurate project purpose, architecture, features, setup, and troubleshooting.
- Modify: `.github/workflows/ci.yml` — run the Compose smoke test after PHP checks.
- Modify: `composer.json` — expose the smoke command if useful to contributors.
- Test: CI workflow and the complete documented fresh-install path.

**Interfaces:**
- Consumes: verified Composer install, app endpoints, Docker image, Compose
  health behavior, and smoke script from Tasks 1–4.
- Produces: contributor instructions that a clean checkout can follow and CI
  that gates pull requests on the same startup/install path.

- [ ] **Step 1: Write documentation contract checks**

  Add `test_readme_documents_real_first_run_credentials_and_commands` to
  assert the README includes `.env.example` setup, `docker compose up`, the
  installer URL, user-selected credentials, RoadRunner ownership, and GPL
  license, and excludes claims that credentials are auto-generated or that
  TuskPress is WordPress source.

- [ ] **Step 2: Track the CSRF protection gap**

  Confirm no existing open issue covers the no-op `CsrfMiddleware`. Create a
  GitHub issue titled `security: implement CSRF protection for TuskPress` with
  scope for session-bound tokens, verification on unsafe requests, tokens in
  all state-changing forms (including installation), and tests for missing,
  invalid, and valid tokens. Do not claim the current middleware provides
  CSRF protection.

- [ ] **Step 3: Rewrite the README to match the tested app**

  Keep the tone welcoming and explanatory. Add project badges for license,
  PHP, Framework, Engine, and RoadRunner versions. Explain why long-lived
  workers reduce repeated bootstrap work and why request-scoped state must be
  reset; state explicitly that CSRF protection is not yet implemented and
  link the issue created in Step 2. Describe TuskPress as a CMS application
  inspired by WordPress, not a WordPress code fork. List actual features only;
  provide exact Compose steps, installer flow, persistence/reset guidance,
  troubleshooting, ecosystem links, and the GPL-2.0-or-later license.

- [ ] **Step 4: Add smoke test to CI and verify workflow syntax**

  Run the PHP matrix from Task 1 and the Compose smoke script on Ubuntu with
  the CI-only `.env` values. Ensure failures preserve logs and the cleanup
  trap removes only the unique smoke project. Keep GitHub Actions permissions
  read-only.

- [ ] **Step 5: Run the full local validation set**

  Run: `composer validate --strict`
  Run: `composer test`
  Run: `composer analyse`
  Run: `bash tests/Smoke/compose-smoke.sh`

  Expected: all pass. If PHPStan reports pre-existing app findings, list them
  explicitly and do not suppress new findings with an unreviewed baseline.

- [ ] **Step 6: Commit**

  Commit: `docs: explain the supported TuskPress runtime and setup`

## Completion Criteria

- All five tasks are committed independently and their task-scoped tests pass.
- The CI PHP matrix and isolated Compose smoke test pass on the branch.
- No app-owned native runtime, local binary dependency, mutable runtime image
  tag, or `--ignore-platform-reqs` remains.
- README commands and feature claims match the passing smoke test and current
  Framework/Engine ownership boundaries.
