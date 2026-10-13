# TuskPress RoadRunner Alignment Design

## Purpose

TuskPress is the ecosystem's reference CMS application, not a fork containing
the WordPress codebase. It should demonstrate the current public contracts of
Tusk Framework and Tusk Engine, start through RoadRunner, and be reproducible
from its documented local/container workflow.

## Current mismatch

- `bootstrap/app.php` constructs a legacy compiled container and returns a
  `HttpKernel`, while the current Engine requires it to return a configured
  `Tusk\Foundation\Application`.
- `composer.json` still requires the legacy `tusk/framework:*` identity and
  `composer.lock` resolves Framework from mutable `dev-main`, rather than the
  published `tusk-framework/framework` package.
- TuskPress route classes declare `#[Route]` methods but lack the Framework's
  class-level `#[Controller]` attribute required for automatic route discovery.
- Root `worker.php` starts the retired native NDJSON runtime. The Engine now
  owns the generated `.tusk/runtime/worker.php` and RoadRunner lifecycle.
- The Dockerfile copies an untracked local Engine binary, ignores Composer
  platform requirements, and requests `tusk start worker.php`, none of which
  matches the current Engine contract.
- The Docker instructions imply automatic administrator credentials and
  database initialization that must be checked against the installer and SQL
  setup before being documented as supported behavior.
- The project has no tracked automated test suite or Docker smoke workflow.
- `CsrfMiddleware::process()` currently delegates every request unchanged, so
  the existing middleware wiring is not actual CSRF protection.
- The Framework `SecurityMiddleware` is a singleton by default but retains a
  request-scoped `GuardInterface`; TuskPress must avoid reusing its
  authentication state across requests.

## Target design

Keep TuskPress as a CMS example with its existing domain features, themes,
plugins, and admin UI. Make its application composition root return the
Framework `Application` built through the supported builder. The app must not
create a runtime loop or own a RoadRunner worker file. `tusk start` validates
the bootstrap, creates private generated worker state, and supervises
RoadRunner. RoadRunner remains the HTTP server, IPC boundary, pool manager, and
worker recycler.

Composer must use the published `tusk-framework/framework` package with a
stable compatible version constraint and lock the selected release. Remove
the project-specific VCS override and development stability requirement if no
other dependency needs them. The container must build reproducibly from
declared inputs: a versioned Tusk Engine image, a versioned official
RoadRunner binary, Composer-installed locked dependencies with real platform
requirements, PHP extensions required by `composer.json` and the database
driver, and an explicitly provisioned compatible RoadRunner executable. No
host-built binary is copied into the image, and no platform requirements are
ignored. Compose must wait for MySQL health before starting the app and must
use non-empty, configurable credentials rather than an empty root password.
Use MySQL 8.4 LTS for the Compose example; MySQL 8.0 reached end of life in
April 2026.

Documentation should state what TuskPress is and is not, explain why the
long-lived RoadRunner lifecycle matters, list prerequisites and exact setup
commands, describe first-install/admin credential behavior accurately, and
provide troubleshooting for PHP extensions, Composer, RoadRunner, and MySQL.
Claims about speed, completed features, or WordPress compatibility must be
limited to behavior that the code and smoke tests demonstrate.

## Implementation boundaries

1. Align bootstrap and app wiring with the current Framework `Application`
   contract while preserving TuskPress routes, middleware, dependency
   registrations, and installer behavior. If the supported Framework APIs
   cannot represent an existing capability without changing Framework
   contracts, stop and record that gap rather than adding a private runtime.
2. Remove the app-owned native runtime entrypoint and migrate its responsibilities
   to the Framework application bootstrap. The Engine-generated worker is
   generated state and must not be checked in.
3. Make Docker/Compose use the same `tusk start` path as local development,
   with explicit versions, actual Composer platform checks, database health
   gating, persistent database storage, and secrets supplied through an
   ignored environment file or deployment environment.
4. Add automated checks for the project contract and a container smoke test
   that installs the app, waits for readiness, makes an HTTP request, and
   shuts down cleanly. Tests must not require committed secrets or a locally
   built Engine binary.
5. Rewrite README setup/architecture/feature claims to match tested behavior.

## Acceptance criteria

- `bootstrap/app.php` returns `Tusk\Foundation\Application` and never starts
  RoadRunner or a request loop itself.
- The root application has no hand-authored `worker.php`; `tusk start` creates
  and cleans Engine-owned `.tusk/runtime/worker.php`.
- Existing CMS routes, authentication middleware, plugin/theme loading,
  database persistence, and initial installation remain functional. Do not
  describe the current CSRF no-op as protection; track real CSRF protection
  separately rather than representing the CMS as production-secure.
- `composer install --no-dev --optimize-autoloader` succeeds without
  `--ignore-platform-reqs` in the production image.
- Composer resolves `tusk-framework/framework` from its published stable
  release and the lockfile does not depend on `dev-main`.
- The container uses a RoadRunner binary compatible with the Framework SDK and
  the Engine's projected config; no untracked local binary is needed.
- Compose reports the app healthy only after RoadRunner and the application
  are ready, and MySQL credentials are configurable and non-empty by default.
- A clean Compose run can reach the installer, complete setup, and serve an
  authenticated admin request; documented credentials/steps match that flow.
- PHP tests/static analysis and the container smoke test pass in CI, or any
  known baseline failures are explicitly identified and isolated.

## Risks and decisions to validate during planning

- The current Framework builder may not expose every legacy service binding
  or middleware hook used by TuskPress. Prefer supported provider/route
  contracts; do not add a parallel container/runtime in TuskPress.
- The Engine image currently provides PHP and the Tusk binary but not
  Composer, a database-specific PHP extension, or RoadRunner. Image
  composition must pin compatible versions and use RoadRunner's official
  versioned image/binary rather than a mutable `latest` download.
- Repository source is GPL-2.0-or-later. Describe TuskPress as inspired by
  WordPress, not as redistribution of WordPress, unless provenance review
  proves otherwise.
- A full application migration may exceed one reviewable PR. Keep each PR
  vertically testable and open follow-up issues for separately scoped CMS
  features rather than weakening the runtime boundary.

## Out of scope

- Reimplementing WordPress compatibility or importing WordPress source.
- Adding an alternate/native or FrankenPHP runtime.
- Duplicating worker pools, HTTP serving, IPC, health supervision, or process
  lifecycle already owned by RoadRunner and the Engine.
- Broad CMS feature expansion unrelated to making the reference app accurate,
  runnable, and representative of supported Tusk APIs.
