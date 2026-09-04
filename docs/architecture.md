# Architecture — FreeHost Manager (Phase 1)

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
- `app/Controllers/` — Auth, Dashboard, PasswordReset, Verification.
- `app/Services/` — AuthService, AuditService, PasswordResetService, EmailVerificationService.
- `app/Repositories/` — UserRepository (PDO).
- `app/Validators/` — Registration, Login.
- `app/Security/` — Csrf, RateLimiter, PathGuard, UploadGuard, Logger.
- `app/Helpers/` — Database (PDO singleton), Router, View, helpers.php (e()).
- `app/Models/` — User DTO.
- `app/Middleware/` — Auth, Rbac, Csrf.
- `app/Views/` — PHP templates with Bootstrap 5 CDN, escaped via `e()`.
- `config/` — app.php, database.php, session.php, bootstrap.php (env load, error handling).
- `database/migrations/` — 001..003, runner `scripts/migrate.php`.
- `storage/` — logs, cache, sessions, uploads, hosting (future jail), all `Require all denied`.
- `routes/web.php` — central route table (GET/POST).
- `scripts/` — migrate, create-admin, generate-key.

## Key Design Decisions
- **No framework** per spec — Composer only for `vlucas/phpdotenv` + `phpunit`.
- **PDO prepared statements everywhere** — no string interpolation.
- **Password hashing** — `PASSWORD_ARGON2ID` if available else `PASSWORD_BCRYPT` cost 12, rehash on login.
- **Customer DB passwords** — per correction, not stored as hash. Phase 1 schema uses `encrypted_password TEXT NULL` (future: authenticated encryption outside DB). Shown once on creation, reset via regeneration.
- **Separation control-panel vs customer code** — `storage/hosting/{account}/public_html` is not under `public/` origin; `.htaccess php_flag engine off` in storage; future Linux will use PHP-FPM pools.
- **Provisioning abstraction** — not yet implemented (Phase 7), but structure reserved under `app/Services/Provisioning/`.
- **Phase 1 boundaries** — no file manager, no real provisioning, no DNS/SSL/billing.

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
