# FreeHost Manager

Web hosting management platform — control panel for customers and admins. Phase 1 Foundation is implemented.

## Project Status
**Phase 1 — Foundation ✅** (2026-09-04)
- Secure auth (register/login/logout), Argon2id/Bcrypt, session hardening, lockout
- RBAC (admin/customer), 16 granular permissions
- CSRF, XSS, SQLi, IDOR, path traversal protections
- Password reset + email verification (hashed tokens, expiry, log mail driver)
- Customer & admin dashboards (Bootstrap 5, responsive)
- Migrations, audit logging, rate limiting, PathGuard/UploadGuard
- 36 automated tests, docs

Next: Phase 2 Hosting (plans, accounts, quotas) — awaiting authorization.

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
- PHPUnit 10 (36 tests)

## Security (Phase 1)
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
