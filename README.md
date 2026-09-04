# FreeHost Manager

Web hosting management platform — control panel for customers and admins. Phase 4 Database Hosting is implemented.

## Project Status
**Phase 4 — Database Hosting ✅** (2026-09-04)
- Phase 1-3 preserved (93 → 112 tests)
- Database hosting: customer `fh_{accountId}_{name}` + `fh_{accountId}_u_{user}` naming, strict regex, reserved block, SQL injection guard
- Mock provisioning via `LocalMockProvisioner` (no root, no exec) — logs only, least-privilege
- Credentials: `random_bytes` 16 chars, encrypted with `APP_KEY` (`sodium`/`openssl` AES-GCM), shown once, never logged, decrypt via `DatabaseService::decryptPassword`
- Quota: `plan.databaseLimit` enforced server-side (FREE 2, BASIC 5, PREMIUM 20)
- Suspended/terminated accounts blocked
- Customer UI: list/create/delete databases, create/delete users, show once password, connection info
- Admin UI: view all databases/users, search, owner/hosting status
- Audit: `database.create/delete`, `database.user_create/delete` with safe metadata, no password

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
- PHPUnit 10 (112 tests)

## Security (Phase 4)
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
