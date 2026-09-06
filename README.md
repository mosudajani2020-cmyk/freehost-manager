# FreeHost Manager

Web hosting management platform — control panel for customers and admins. Phase 4 Database Hosting is implemented.

## Project Status
**Phase 9 — Production Hardening and Security Review ✅** (2026-09-04)
- Phase 1-8 preserved (159 → 179 tests, 1594 assertions)
- Hardening: `APP_DEBUG=false`, secure `HttpOnly` `SameSite=Lax` cookies, HSTS, CSP, `X-Frame-Options`, `Permissions-Policy`, no `.env` exposure, no stack traces, no dev server in prod
- DB: least-privilege `freehost_app`, indexes/FKs, safe migrations, `price_cents` fix for `0` (validator `FILTER_VALIDATE_INT === false`), transactions for quota
- Filesystem: `PathGuard` symlink block, `quota` via `RecursiveDirectoryIterator`, `UploadGuard`, safe `rename/delete` with `isSafeFilename`, `rrmdir` child-first
- Provisioning: `idempotency_key` UNIQUE, retry max 3, `last_error`, audit trail, no `exec`, `LocalMock*` only
- Dependencies: `composer audit` clean, `vlucas/phpdotenv` + `phpunit` only, PHP `^8.3` required
- 179 automated tests, 20 new hardening regression tests, hardened `docs/security.md` + `docs/deployment.md`

**P1 — Provisioning Infrastructure Foundation ✅** (2026-09-06)
- Queue claim foundation in `provisioning_jobs` via `SELECT … FOR UPDATE SKIP LOCKED` (short lock, lease + heartbeat + stale recovery)
- Restricted CLI worker: `php scripts/worker.php [--once] [--poll N] [--lease N]` — see `docs/worker.md`
- Env/config-driven provider selection (`ProviderFactory`, kinds default to `local_mock`); hard-coded LocalMock selections removed from services/controllers
- Worker/control-panel secret separation (`.env` + optional `.env.worker` overlay; web app never loads it)
- Secret-safe structured logging (`WorkerLogger`, JSON lines in `storage/logs/worker.log`)
- 218 automated tests passing

## Requirements
- **PHP 8.3+** (fails safe on <8.3)
- MySQL 8.0+ / MariaDB
- Apache 2.4 (`mod_rewrite`, `AllowOverride All`, DocumentRoot `public/`)
- Composer 2.x

> AppServ ships PHP 7.3 (EOL). Use a parallel PHP 8.3 binary for CLI (`C:\php83\php.exe`).

## Quick Start
```powershell
git clone https://github.com/mosudajani2020-cmyk/freehost-manager.git
cd freehost-manager
C:\php83\php.exe composer.phar install
copy .env.example .env
# edit .env: DB_PASSWORD, APP_KEY (generate: C:\php83\php.exe scripts/generate-key.php)
C:\php83\php.exe scripts/migrate.php
C:\php83\php.exe scripts/create-admin.php
# browse http://localhost/freehost-manager/public/login
```

## Technology
- HTML5, CSS3, Bootstrap 5, JavaScript
- PHP 8.3, PDO, `vlucas/phpdotenv`
- MySQL/MariaDB, Apache
- PHPUnit 10 (218 tests)

## Security (Phase 9)
Implemented: prepared statements, `e()` escaping, CSP, CSRF synchronizer, RBAC server-side, PathGuard, UploadGuard, session fixation protection, rate limiting, audit logs. See `docs/security.md`.

## Documentation
- `docs/installation.md` — setup, env blocker, troubleshooting
- `docs/architecture.md` — layers, request lifecycle
- `docs/database.md` — schema, seeds, migrations
- `docs/security.md` — threat matrix, headers
- `docs/development.md` — conventions, workflow
- `docs/implementation-plan.md` — Phase 0 plan
- `docs/worker.md` — P1 provisioning worker: run, configure, queue mechanics

## License
To be determined.
