# FreeHost Manager

Web hosting management platform — control panel for customers and admins. Phase 4 Database Hosting is implemented.

## Project Status
**Phase 7 — Usage, Backups and Monitoring ✅** (2026-09-04)
- Phase 1-6 preserved (145 → 159 tests)
- Usage: `UsageService` + `LocalMockUsageCollector` (storage/bandwidth/database/domain, `usage_records` daily snapshots, quota warnings `>90%`, ownership, IDOR)
- Backups: `BackupService` + `LocalMockBackupProvider` (metadata only `full/incremental`, `pending/running/completed/failed/expired`, retention 7d, no destructive deletion, `storage/backups/...` mock path)
- Monitoring: `MonitoringService` + `LocalMockMonitoringProvider` (`healthy/warning/critical/unknown` via failed jobs/backups, operational stats `users/hosting/databases/backups/audit`, `failed_jobs` reporting)
- Customer: `GET /hosting/{id}/usage` (quota bars, collect), `GET /hosting/{id}/backups` (create/delete, retention)
- Admin: `GET /admin/monitoring` (system health, provisioning health, stats, failed jobs, audit), `GET /admin/backups` + expire, `GET /admin/monitoring/usage`
- 159 automated tests, hardened docs + `docs/deployment.md` (usage/backups/monitoring)

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
- PHPUnit 10 (159 tests)

## Security (Phase 7)
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
