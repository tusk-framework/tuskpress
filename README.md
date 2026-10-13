# TuskPress

![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
[![Tusk Framework](https://img.shields.io/badge/Tusk%20Framework-1.x-4B6)](https://github.com/tusk-framework/tusk-framework)
[![Tusk Engine](https://img.shields.io/badge/Tusk%20Engine-v0.3.4-4B6)](https://github.com/tusk-framework/tusk-engine)
[![RoadRunner](https://img.shields.io/badge/RoadRunner-2025.1.15-4B6)](https://roadrunner.dev/)

TuskPress is an example and reference CMS inspired by WordPress. It demonstrates an application built with Tusk Framework and the Tusk Engine, using familiar publishing concepts and a deliberately limited set of CMS features. It is not derived from WordPress source code and does not claim full WordPress compatibility.

The repository includes a first-run installer, administrator login and dashboard, post and page presentation, themes, plugin discovery and several admin/API surfaces. Some screens and operations are incomplete examples; the presence of a route or interface does not imply complete production-ready CMS behavior. In particular, CSRF protection is not implemented yet. Track that work in [issue #1](https://github.com/tusk-framework/tuskpress/issues/1); the current middleware does not protect forms.

## Run with Docker Compose

Docker Desktop (or Docker Engine with the Compose plugin) is required and should be running. PHP and Composer are not needed on the host for this workflow; the image builds the PHP application and installs its dependencies.

From the repository root, copy the example environment file to the ignored local `.env`, then build and start the services:

```bash
cp .env.example .env
docker compose up --build -d
```

On PowerShell, the copy command is `Copy-Item .env.example .env`. The example credentials are for local development; change them before exposing the stack beyond your machine.

Open the installer at <http://localhost:8080/wp-admin/install.php>. Choose a site title, administrator username, password, and email address in the installer. These are user-selected credentials; none are generated automatically. After installation, sign in at <http://localhost:8080/wp-login>.

The Compose stack provides MySQL and the TuskPress web service. MySQL data is stored in the `tuskpress_db_data` named volume, and uploaded files are stored in `tuskpress_uploads`. `docker compose down` stops and removes the containers while preserving both volumes. To also permanently delete the database and uploads, run `docker compose down --volumes`.

Check service state and health with:

```bash
docker compose ps
docker compose logs --follow web mysql
```

The web health check is available at <http://localhost:8080/healthz>. A healthy response is `ok`.

### Troubleshooting

- If Docker commands cannot connect to the daemon, start Docker Desktop or the Docker Engine service and retry.
- If port 8080 is already in use, stop the other service using it before starting this stack.
- If the image build reports missing PHP extensions, use the supplied Dockerfile: it installs `mbstring`, `pdo_mysql`, and `sockets`, and checks Composer platform requirements. Avoid bypassing that check.
- If Composer cannot resolve or install packages during the image build, check network access and the Composer output, then retry the build with `docker compose up --build -d`.
- If RoadRunner or the web service fails its health check, inspect `docker compose logs web`; check MySQL readiness and credentials with `docker compose logs mysql` and confirm `.env` has all four `MYSQL_*` values.

## Runtime lifecycle

Tusk Engine validates the application setup and manages the RoadRunner process. RoadRunner owns HTTP serving, its worker pool, and worker lifecycle. The application is loaded into a long-lived PHP worker and remains loaded across requests. Request-scoped services and process state therefore need correct per-request handling and reset; they must not leak one request's state into another.

The application uses Tusk Framework controllers, routing, middleware, and dependency injection. Docker pins Tusk Engine `v0.3.4` and RoadRunner `2025.1.15` in the Dockerfile. There is no hand-authored root `worker.php` or project-owned NDJSON request loop.

## Local development

For local tests and static analysis, install PHP 8.2 or newer, Composer 2, and the PHP extensions required by the project: `fileinfo`, `json`, `mbstring`, `sockets`, and PDO (with the MySQL PDO driver for database-backed workflows). Then install development dependencies and run the checks:

```bash
composer install
composer test
composer analyse
```

The Docker image is based on PHP 8.3 and installs `mbstring`, `pdo_mysql`, and `sockets`. The local unit and architecture test suite does not require a host database. To run the full Compose smoke check locally, use Bash, a working Docker daemon with the Compose plugin, curl, PHP CLI, and the standard shell utilities `seq` and `sleep`; run `composer test:smoke`. Normal Docker app startup above does not require PHP or Composer on the host.

## License

TuskPress is licensed under [GPL-2.0-or-later](LICENSE).
