# FreeHost Manager

Web hosting management platform — control panel for customers and admins. Phase 4 Database Hosting is implemented.

## Project Status
**Phase 5 — Provisioning Architecture ✅** (2026-09-04)
- Phase 1-4 preserved (112 → 127 tests)
- Provisioning: `hosting_nodes` + `provisioning_jobs` (`pending→queued→provisioning→active/failed/retrying/suspended/terminated`), idempotency `idempotency_key` UNIQUE, retry max 3, `LocalMockProvisioner` mock logs, `HostingService` records jobs
- Nodes: admin CRUD, API key `fhm_*` hash preview shown once, least-loaded active selection, `max_accounts/current_accounts`
- Jobs: dispatch with idempotency, `processJob` synchronous for mock (future async worker), `retryJob`/`failJob`, audit `provisioning.*`
- Security: HMAC-SHA256 `X-Signature` over `method|path|bodyHash|timestamp|nonce` with 5-min TTL + nonce replay via `rate_limits`, no `exec`, no SSH passwords, no private keys in Git
- Admin UI: `/admin/nodes`, `/admin/provisioning` with filter, retry, failure handling
- 127 automated tests, hardened docs + `docs/deployment.md`

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
- PHPUnit 10 (127 tests)

## Security (Phase 5)
Implemented: prepared statements, `e()` escaping, CSP, CSRF synchronizer, RBAC server-side, PathGuard, UploadGuard, session fixation protection, rate limiting, audit logs. See `docs/security.md`.

## Documentation
- `docs/installation.md` — setup, env blocker, troubleshooting
- `docs/architecture.md` — layers, request lifecycle
- `docs/database.md` — schema, seeds, migrations
- `docs/security.md` — threat matrix, headers
- `docs/development.md` — conventions, workflow
- `docs/implementation-plan.md` — Phase 0 plan

## License
To be determined.
