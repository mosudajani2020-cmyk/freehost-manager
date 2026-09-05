# Architecture — FreeHost Manager (Phase 7)

## Overview
Modular monolith, PHP 8.3+, no framework. Separation:
- Presentation → Controllers (thin) → Services (business) → Repositories (PDO) → MySQL
- Cross-cutting: Middleware, Validators, Helpers, Security.

## Request Lifecycle
```
HTTP → public/index.php (version guard, bootstrap, session, security headers)
  → Router (routes/web.php)
    → Middleware pipeline (Csrf, Auth, Rbac, RateLimit)
      → Controller::method(params)
        → Validator → Service → Repository (prepared statements)
        → View (escaped output)
```

## Directory Map
- `public/` — only web-accessible. Contains `index.php`, `.htaccess`, `assets/`. DocumentRoot must be here.
- `app/Controllers/` — Auth, Dashboard, PasswordReset, Verification, HostingController, PlanController, AdminHostingController, FileController, DatabaseController, AdminDatabaseController, AdminNodeController, AdminProvisioningController.
- `app/Services/` — AuthService, AuditService, PasswordResetService, EmailVerificationService, HostingService, FileService, DatabaseService, ProvisioningService, Provisioning (HostingProvisionerInterface, LocalMockProvisioner).
- `app/Repositories/` — UserRepository, HostingPlanRepository, HostingAccountRepository, SubdomainRepository, HostingNodeRepository, ProvisioningJobRepository.
- `app/Validators/` — Registration, Login, HostingPlanValidator, HostingAccountValidator (DB name/user regex in DatabaseService).
- `app/Security/` — Csrf, RateLimiter, PathGuard, UploadGuard, Logger.
- `app/Helpers/` — Database (PDO singleton), Router, View, helpers.php (e()).
- `app/Models/` — User, HostingPlan, HostingAccount, Subdomain, HostingNode, ProvisioningJob.
- `app/Middleware/` — Auth, Rbac, Csrf.
- `app/Views/` — PHP templates (auth, hosting, admin/plans, admin/hosting, admin/nodes, admin/provisioning, dashboard, files, databases, admin/databases) with Bootstrap 5 CDN, escaped via `e()`.
- `config/` — app.php, database.php, session.php, bootstrap.php (env load, error handling).
- `database/migrations/` — 001..004 (004 adds `hosting_nodes`, `provisioning_jobs`), runner `scripts/migrate.php`.
- `storage/` — logs, cache, sessions, uploads, hosting (mock jail: storage/hosting/{username}_{rand}/public_html + subdomains), all `Require all denied`; file manager jailed via PathGuard.
- `routes/web.php` — central route table (GET/POST) with Auth/RBAC/CSRF groups for hosting, admin, files (`/hosting/{id}/files`), databases (`/hosting/{id}/databases`), provisioning (`/admin/nodes`, `/admin/provisioning`).
- `scripts/` — migrate, create-admin, generate-key.

## Key Design Decisions
- **No framework** per spec — Composer only for `vlucas/phpdotenv` + `phpunit`.
- **PDO prepared statements everywhere** — no string interpolation.
- **Password hashing** — `PASSWORD_ARGON2ID` if available else `PASSWORD_BCRYPT` cost 12, rehash on login.
- **Customer DB passwords** — per correction, not stored as hash. Phase 1 schema uses `encrypted_password TEXT NULL` (future: authenticated encryption outside DB). Shown once on creation, reset via regeneration.
- **Separation control-panel vs customer code** — `storage/hosting/{account}/public_html` is not under `public/` origin; `.htaccess php_flag engine off` in storage; future Linux will use PHP-FPM pools.
- **Provisioning abstraction** — `HostingProvisionerInterface` + `ProvisioningService` (Phase 5) with `LocalMockProvisioner` mock logs, `HostingNode` (least-loaded active) + `ProvisioningJob` (`pending→queued→provisioning→active/failed/retrying/suspended/terminated`, idempotency `idempotency_key` UNIQUE, retry max 3, HMAC `X-Signature` with 5-min TTL + nonce replay via `rate_limits`), no shell, future `LinuxProvisioner` will be restricted worker/API.
- **Hosting lifecycle** — `HostingService` enforces pending→active→suspended→terminated, ownership, quotas, now also records `provisioning_jobs` via `recordJob` (idempotent) and uses `ProvisioningService` for node selection.
- **Subdomains** — `subdomain.freehost.example` where main domain from `system_settings.main_domain` or `APP_DOMAIN`; strict validation, quota, duplicate guard, mock provisioner; auto-creates `dns_records` + `ssl_certificates` entries via `HostingService` (Phase 6).
- **File Manager** — `FileService` (Phase 3) enforces isolation via `PathGuard::resolve(root, relative)` for every op; `UploadGuard` for extension/MIME/size; quota via `calcUsage` vs `plan.storageLimitMb`; atomic writes; text edit allow-list 512KB; audit for every op; no customer PHP execution via control panel.
- **Database Hosting** — `DatabaseService` (Phase 4) generates safe names `fh_{accountId}_{part}` (`^[a-z][a-z0-9_]{2,29}$`, reserved block, no spaces/quotes), quota `plan.databaseLimit`, mock provisioner (no `CREATE DATABASE` as root), password `random_bytes` 16 chars + encrypted with `APP_KEY` (`sodium`/`AES-GCM`), shown once, never logged, audit safe.
- **DNS/SSL (Phase 6)** — `DnsService` + `SslService` with `DnsProviderInterface`/`CertificateProviderInterface` + `DomainProviderInterface` + `LocalMock*` (no real DNS/LETS Encrypt), strict hostname validation, duplicate `hostname` UNIQUE, takeover prevention (must be subdomain of `APP_DOMAIN`), lifecycle `dns: pending/active/failed/suspended/removed` + `ssl: pending/issuing/active/renewing/expired/failed/revoked` synced to `subdomains.dns_status/ssl_status`, audit, no secrets.
- **Usage/Backups/Monitoring (Phase 7)** — `UsageService` + `LocalMockUsageCollector` (storage via `RecursiveDirectoryIterator`, bandwidth mock), `BackupService` + `LocalMockBackupProvider` (metadata only `full/incremental` 7d default, `pending/running/completed/failed/expired`), `MonitoringService` + `LocalMockMonitoringProvider` (`healthy/warning/critical/unknown` via failed jobs/backups, operational stats). `usage_records` daily snapshots, `backups` retention, no destructive deletion.

## Security Headers (public/index.php)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `CSP: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn...`

## Future Provisioning Split (local mock vs production)
```
Control Panel (this app) → HTTPS + HMAC (X-Timestamp/X-Nonce/X-Signature) → Provisioning Service/API (restricted worker)
  → Hosting Node (Linux, Nginx, PHP-FPM per customer, chroot/jail, cgroup)
  → Database Service (MySQL least-privilege)
  → DNS Provider (Route53/Cloudflare API, mock LocalMockDnsProvider)
  → Certificate Provider (ACME/Let's Encrypt, mock LocalMockCertificateProvider)
```
**Phase 5 local mock:** `storage/hosting` isolated via `PathGuard`; `UploadGuard`; no shell; `php_flag engine off` + `LocalMockProvisioner` logs + `provisioning_jobs` + `hosting_nodes` (local-mock-1). **Production** will use Linux jails, PHP-FPM per account, `hosting_nodes` with `api_key_hash`, HMAC signing, async worker polling `queued` jobs, idempotency, replay protection.

**File Manager isolation:** `authenticated user → owned active hosting → PathGuard::resolve(base, relative) → absolute` — browser path never trusted; relative paths displayed as `/`, `/public_html`, absolute never leaked.

**Provisioning isolation:** Web request creates `provisioning_jobs` (queued) with `idempotency_key`; worker (future) authenticates via `X-Api-Key` + `HMAC`, verifies `timestamp` + `nonce` (rate_limits), executes allow-list `HostingProvisionerInterface` only, updates `status`→`active/failed`, audit logs, no `exec`.

**DNS/SSL isolation (Phase 6):** `DnsService`/`SslService` validate `hostname` strictly, check `hosting_account.user_id`, ensure `hostname` is subdomain of `APP_DOMAIN` (takeover prevention), use `LocalMock*` providers (no real API keys), sync to `subdomains.dns_status/ssl_status` + `dns_records`/`ssl_certificates` with lifecycles.

## Config
All secrets via `.env` → `vlucas/phpdotenv` → `config/*.php`. `.env` never committed. `APP_KEY` generated via `scripts/generate-key.php`.
