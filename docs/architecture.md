# Architecture — FreeHost Manager (Phase 2)

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
- `app/Controllers/` — Auth, Dashboard, PasswordReset, Verification, HostingController, PlanController, AdminHostingController.
- `app/Services/` — AuthService, AuditService, PasswordResetService, EmailVerificationService, HostingService, Provisioning (HostingProvisionerInterface, LocalMockProvisioner).
- `app/Repositories/` — UserRepository, HostingPlanRepository, HostingAccountRepository, SubdomainRepository.
- `app/Validators/` — Registration, Login, HostingPlanValidator, HostingAccountValidator.
- `app/Security/` — Csrf, RateLimiter, PathGuard, UploadGuard, Logger.
- `app/Helpers/` — Database (PDO singleton), Router, View, helpers.php (e()).
- `app/Models/` — User, HostingPlan, HostingAccount, Subdomain.
- `app/Middleware/` — Auth, Rbac, Csrf.
- `app/Views/` — PHP templates (auth, hosting, admin/plans, admin/hosting, dashboard) with Bootstrap 5 CDN, escaped via `e()`.
- `config/` — app.php, database.php, session.php, bootstrap.php (env load, error handling).
- `database/migrations/` — 001..003 (no new Phase 2 migration — schema already covered), runner `scripts/migrate.php`.
- `storage/` — logs, cache, sessions, uploads, hosting (mock jail: storage/hosting/{username}_{rand}/public_html), all `Require all denied`.
- `routes/web.php` — central route table (GET/POST) with Auth/RBAC/CSRF middleware groups for hosting & admin.
- `scripts/` — migrate, create-admin, generate-key.

## Key Design Decisions
- **No framework** per spec — Composer only for `vlucas/phpdotenv` + `phpunit`.
- **PDO prepared statements everywhere** — no string interpolation.
- **Password hashing** — `PASSWORD_ARGON2ID` if available else `PASSWORD_BCRYPT` cost 12, rehash on login.
- **Customer DB passwords** — per correction, not stored as hash. Phase 1 schema uses `encrypted_password TEXT NULL` (future: authenticated encryption outside DB). Shown once on creation, reset via regeneration.
- **Separation control-panel vs customer code** — `storage/hosting/{account}/public_html` is not under `public/` origin; `.htaccess php_flag engine off` in storage; future Linux will use PHP-FPM pools.
- **Provisioning abstraction** — `HostingProvisionerInterface` with `LocalMockProvisioner` (Phase 2). Creates safe `storage/hosting/.../public_html` + `.htaccess` + `.suspended/.terminated` flags; no shell. Future `LinuxProvisioner` will be Phase 7.
- **Hosting lifecycle** — `HostingService` enforces pending→active→suspended→terminated, ownership, quotas (subdomain/database/domain) in service layer, not UI.
- **Subdomains** — `subdomain.freehost.example` where main domain from `system_settings.main_domain` or `APP_DOMAIN`; strict validation, quota, duplicate guard, mock provisioner.
- **Phase 2 boundaries** — file manager (Phase 3), databases (Phase 4), real DNS/SSL/billing still deferred.

## Security Headers (public/index.php)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `CSP: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn...`

## Future Provisioning Split (not in Phase 1)
```
Control Panel (this app) → Job queue → Provisioning Worker (restricted Linux user)
  → filesystem jail, PHP-FPM pools, MySQL CREATE DATABASE, DNS API, ACME TLS
```
Phase 1 mock: no shell exec; audit logs only.

## Config
All secrets via `.env` → `vlucas/phpdotenv` → `config/*.php`. `.env` never committed. `APP_KEY` generated via `scripts/generate-key.php`.
