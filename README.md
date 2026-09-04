# FreeHost Manager

Web hosting management platform — control panel for customers and admins. Phase 1 Foundation is implemented.

## Project Status
**Phase 2 — Hosting ✅** (2026-09-04)
- Phase 1 foundation (auth, RBAC, dashboards, migrations, 36 tests) preserved
- Hosting plans: admin CRUD, validated quotas (FREE/BASIC/PREMIUM), RBAC `plans.manage`
- Hosting accounts: customer creation, ownership checks, lifecycle pending→active→suspended→terminated via `HostingService` + `LocalMockProvisioner` (safe `storage/hosting` mock)
- Subdomains: `sub.freehost.example` from `system_settings.main_domain`, strict validation, duplicate & quota guards
- Quotas: storage/bandwidth/database/domain/subdomain enforced server-side
- Admin hosting: view/search, details, activate/suspend/terminate with audit
- Customer dashboard hosting section (plan, limits, usage, subdomains)
- 56 automated tests, hardened docs

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
- PHPUnit 10 (56 tests)

## Security (Phase 2)
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
