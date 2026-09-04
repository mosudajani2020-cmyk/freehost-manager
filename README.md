# FreeHost Manager

Web hosting management platform — control panel for customers and admins. Phase 4 Database Hosting is implemented.

## Project Status
**Phase 6 — DNS and SSL Management ✅** (2026-09-04)
- Phase 1-5 preserved (127 → 145 tests)
- DNS: `dns_records` + `DnsService` + `LocalMockDnsProvider` (strict hostname validation, duplicate takeover prevention, `pending/active/failed/suspended/removed`, `subdomains.dns_status` sync, no real DNS API)
- SSL: `ssl_certificates` + `SslService` + `LocalMockCertificateProvider` (lifecycle `pending/issuing/active/renewing/expired/failed/revoked`, 90d mock, `subdomains.ssl_status` sync, no Let's Encrypt)
- Providers: `Domain/Dns/CertificateProviderInterface` + `LocalMock*` (no real API keys, no `exec`)
- Subdomains auto-create DNS `A` + SSL `active` via `HostingService` (mock)
- Customer: view assigned domain/subdomains, DNS/SSL status, create/remove subdomains, request/renew/revoke SSL (mock) with ownership/IDOR, CSRF, audit
- Admin: domain/DNS/SSL/provisioning status, `suspended`/`revoked` updates
- 145 automated tests, hardened docs + `docs/deployment.md` (DNS/SSL)

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
- PHPUnit 10 (145 tests)

## Security (Phase 6)
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
