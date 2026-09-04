# FreeHost Manager — Implementation Plan

**Version:** 1.0 — Phase 0 Inspection  
**Date:** 2026-09-03  
**Author:** Senior Architect (Inspection Phase)  
**Repository:** https://github.com/mosudajani2020-cmyk/freehost-manager.git  
**Local Path:** `C:\AppServ\www\freehost-manager`  
**Status:** ⏸ AWAITING APPROVAL — Do not proceed to Phase 1 without review

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current Project Status](#2-current-project-status)
3. [Environment Inspection](#3-environment-inspection)
4. [Architecture Design](#4-architecture-design)
5. [Project Structure](#5-project-structure)
6. [Database Architecture](#6-database-architecture)
7. [Security Plan](#7-security-plan)
8. [Phased Development Plan](#8-phased-development-plan)
9. [Configuration & Environment](#9-configuration--environment)
10. [Provisioning Abstraction](#10-provisioning-abstraction)
11. [Testing Strategy](#11-testing-strategy)
12. [Deployment Plan](#12-deployment-plan)
13. [Documentation Plan](#13-documentation-plan)
14. [Risks & Limitations](#14-risks--limitations)
15. [Definition of Done (per phase)](#15-definition-of-done-per-phase)
16. [Next Steps](#16-next-steps)

---

## 1. Executive Summary

FreeHost Manager is a greenfield project. Inspection on 2026-09-03 confirms **no application code exists yet** — only `README.md` and `.gitignore` (2 files, 1 commit). The local AppServ stack is **Apache 2.4.41 + PHP 7.3.10 + MySQL 8.0.17** on Windows 11, which diverges from the spec requirement of PHP 8+. A full modular monolith must be built incrementally across 9 phases, with security as priority #1. The Windows/AppServ host is declared **development-only** — it cannot provide production-grade multi-tenant isolation; a Linux-based provisioning layer will be abstracted from day one.

This plan satisfies the MASTER ENGINEERING PROMPT §51 Phase 0 deliverable. **No Phase 1 code will be written until this plan is approved.**

---

## 2. Current Project Status

### 2.1 Filesystem

```
freehost-manager/
├── .git/               # initialized, 1 commit (58d58e1)
├── .gitignore          # 44 lines — covers .env, logs, tmp, uploads, OS, IDE, node, vendor
└── README.md           # 46 lines — placeholder, accurate tech list, no implementation docs
```

- **No** `app/`, `config/`, `public/`, `database/`, `routes/`, `storage/`, `tests/`, `docs/` (until this file), `admin/`, `user/`
- **No** `composer.json`, `package.json`, `.env`, `.env.example`, `index.php`
- **No** migrations, seeders, controllers, models, services

### 2.2 Git

| Property | Value |
|---|---|
| `git` binary | `C:\Program Files\Git\cmd\git.exe` (not in default PATH, requires full path) |
| Branch | `main` (tracks `origin/main`) |
| Remote | `https://github.com/mosudajani2020-cmyk/freehost-manager.git` |
| Last commit | `58d58e1 Initial project setup` — 2026-09-03 21:20 +0100 by IMYAA <mosudajani2020@gmail.com> |
| Working tree | `clean` — nothing to commit |
| Auth | `credential.helper=manager` (Windows Credential Manager) |

No secrets in history. `.gitignore` correctly excludes `.env`.

### 2.3 What Already Exists (Useful — Preserve)

- `.gitignore` — adequate, but needs additions for `storage/logs/*`, `storage/cache/*`, `vendor/` already covered.
- `README.md` — keep as seed, expand in Phase 9.

### 2.4 Missing Components (Everything Else)

All 15 core entity groups, auth, RBAC, dashboards, file manager, DB manager, domains, quotas, admin, audit, notifications, provisioning — **none implemented**. This is expected at Phase 0.

---

## 3. Environment Inspection

### 3.1 Stack Versions (verified 2026-09-03)

| Component | Expected | Actual | Risk |
|---|---|---|---|
| OS | Windows 11 Pro | Windows 11 Pro (DESKTOP-U6840BG, 4 cores) | OK |
| Web Server | Apache | **Apache/2.4.41 (Win64) 2019-08-10** on `:80` PID 19032 | 7 years old — update recommended before prod |
| PHP | **PHP 8+** | **PHP 7.3.10 ZTS MSVC15 x64 (2019-09-24)** via `php7apache2_4.dll` | **HIGH — EOL since 2021-12-06, no security fixes** |
| MySQL | MySQL/MariaDB | **MySQL 8.0.17 (Win64)** on `:3306` PID 10732 | OK but 2019 — patch |
| phpMyAdmin | — | Present at `C:\AppServ\www\phpMyAdmin` | OK |
| Node/npm | Node.js | **Not in PATH** | Low — install via nvm or official installer if needed |
| Git | Git | Via `C:\Program Files\Git\cmd\git.exe` | OK |

**Critical PHP gap:** Spec demands PHP 8+ (for typed properties, attributes, `str_contains`, `password_algos`, stricter PDO). Code must target **PHP 8.1+ syntax** but CI must verify compatibility shim for PHP 7.3 during dev, or better — upgrade AppServ PHP to 8.2/8.3 immediately (replace `php7` folder, update `httpd.conf` `LoadModule`/`PHPIniDir`). This plan assumes **upgrade to PHP 8.2** in Phase 1 prerequisite.

### 3.2 PHP Configuration (`C:\AppServ\php7\php.ini`) — Findings

| Directive | Current | Required | Action |
|---|---|---|---|
| `expose_php` | `On` | `Off` | Leaks version header |
| `display_errors` | `Off` | `Off` (prod) / `Off` + log (dev) | OK but need `display_errors=Off` + `log_errors=On` |
| `log_errors` | `On` | `On` | OK — point `error_log` to `storage/logs/php.log` |
| `error_reporting` | `E_ALL & ~E_DEPRECATED & ~E_STRICT` | `E_ALL` | Raise |
| `session.use_strict_mode` | `0` | `1` | **Fix — session fixation** |
| `session.cookie_httponly` | `` (empty) | `1` | **Fix — XSS** |
| `session.cookie_secure` | `` | `1` when HTTPS | Add |
| `session.cookie_samesite` | `` | `Lax` or `Strict` | **Fix — CSRF** |
| `session.use_only_cookies` | `1` | `1` | OK |
| `session.gc_maxlifetime` | `1440` | `1800` + inactivity check | Tune |
| `session.save_path` | `C:/Users/IMYAA/AppData/Local/Temp` | `storage/sessions` or `;` | Move outside Temp, restrict perms |
| `upload_max_filesize` | `2M` | `20M` (configurable via plan) | Raise + enforce per-plan |
| `post_max_size` | `8M` | `22M` | Raise |
| `memory_limit` | `128M` | `256M` | Raise |
| `disable_functions` | `` | `exec,passthru,shell_exec,system,proc_open,popen` | **Harden** (provisioning worker only) |
| `open_basedir` | `` | Set per vhost in prod; dev leave empty but validate in app | App-level path jail instead |
| `allow_url_fopen` / `allow_url_include` | defaults | `allow_url_include Off` | Verify |

### 3.3 MySQL

- Root access without password failed; with `root:root` failed (`ERROR 1045`). Credentials unknown — must recover via `C:\AppServ\MySQL\my.ini` or reset. Required before migrations.
- `port=3306`, `CLIENT` and `mysqld` both on 3306, listening on `0.0.0.0` + `::`. No `sql_mode` override visible.
- Need to create dedicated app DB user: `freehost_app` with least privilege — never use root for app.

### 3.4 Apache

- `DocumentRoot "C:/AppServ/www"` (shared). Must add VirtualHost or `Alias` for `freehost-manager/public` as docroot, deny access to `app/`, `config/`, `storage/`.
- `ServerName localhost:80`, `Listen 80`.

### 3.5 Other Observations

- `C:\AppServ\www` contains many unrelated projects (`As-sahad`, `Inventory system`, `online-examination-system`, etc.) sharing the same DocumentRoot — isolation risk. FreeHost Manager must have its own vhost/docroot.
- `.env` exposure risk: if placed in `C:\AppServ\www\freehost-manager` it is web-accessible under current DocumentRoot. Must move docroot to `public/` and block `.env` via `.htaccess`.

---

## 4. Architecture Design

### 4.1 Principles (per §4)

Clean architecture, separation of concerns, DRY, SOLID (where justified), secure-by-design, least privilege, defense in depth. **No framework** (no Laravel/Symfony) — plain PHP 8+ with PDO, Composer for limited deps only (e.g. `vlucas/phpdotenv` is justified for `.env`).

### 4.2 Layering (Modular Monolith)

```
HTTP Request
  → public/index.php (Front Controller)
    → routes/ (route table)
      → Middleware pipeline (Session → CSRF → Auth → RBAC → RateLimit)
        → Controllers (thin, HTTP only)
          → Services (business logic, quota, provisioning interface)
            → Repositories (PDO prepared statements)
              → MySQL
    → Views (PHP templates, escaped output)
```

- **Presentation:** `public/` docroot, `app/Views/`, `public/assets/` (Bootstrap 5 CDN + local fallback)
- **Application:** `app/Controllers/`, `app/Services/`, `app/Validators/`
- **Domain:** `app/Models/` (entities, not ActiveRecord), `app/Repositories/`
- **Infrastructure:** `app/Services/Provisioning/`, `config/`, `storage/`
- **Cross-cutting:** `app/Middleware/`, `app/Helpers/`, `app/Security/` (CSRF, RateLimit, PathGuard)

### 4.3 Request Lifecycle

1. `public/index.php` loads `config/bootstrap.php` → dotenv → error handler → session bootstrap.
2. Router matches `METHOD + URI` → middleware stack → controller method.
3. Controller validates input via Validators, calls Services.
4. Services enforce authZ, quotas, path jailing, then Repositories.
5. Response rendered via View with auto-escaping helper `e()`.

### 4.4 Future Provisioning Split

```
Control Panel (this app)  ──HTTP/Jobs──>  Provisioning Worker (restricted user, Linux)
                                              ├── filesystem (per-customer chroot/jail)
                                              ├── PHP-FPM pools
                                              ├── MySQL CREATE DATABASE/USER
                                              ├── DNS API (Cloudflare/Route53)
                                              └── TLS (ACME/Let's Encrypt)
```
Local dev uses `LocalMockProvisioner` that creates directories under `storage/hosting/{account_id}/public_html/` and logs actions — no shell.

---

## 5. Project Structure

Approved structure (spec §7, confirmed):

```
freehost-manager/
├── app/
│   ├── Controllers/          # Auth, Dashboard, Hosting, Files, Database, Admin, etc.
│   ├── Models/               # User, HostingAccount, HostingPlan, Domain, Database, etc.
│   ├── Services/             # AuthService, HostingService, FileManagerService, DbService
│   │   └── Provisioning/     # HostingProvisionerInterface + LocalMockProvisioner
│   ├── Repositories/         # UserRepository, HostingRepository, etc. (PDO)
│   ├── Middleware/           # AuthMiddleware, RbcsMiddleware, CsrfMiddleware, RateLimit
│   ├── Validators/           # RegistrationValidator, PasswordValidator, PathValidator
│   ├── Helpers/              # Str, Csrf, Crypto, Url, View helpers
│   └── Security/             # PathGuard, UploadGuard, RateLimiter
├── config/
│   ├── app.php
│   ├── database.php
│   ├── session.php
│   └── bootstrap.php
├── database/
│   ├── migrations/           # 001.. timestamped SQL/PHP migrations
│   ├── seeders/              # Plans, permissions, dev admin
│   └── schema.sql            # consolidated for review
├── public/
│   ├── index.php             # front controller
│   ├── .htaccess             # deny .env, rewrite to index.php
│   └── assets/
│       ├── css/  js/  images/
├── routes/
│   └── web.php
├── storage/
│   ├── logs/  cache/  sessions/  temp/
│   ├── hosting/              # local mock customer roots (gitignored)
│   └── uploads/              # quarantine
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Security/
├── docs/
│   ├── implementation-plan.md  # this file
│   ├── installation.md
│   ├── architecture.md
│   ├── database.md
│   ├── security.md
│   └── ...
├── admin/   # (avoid duplicate — admin is route group, not physical dir; keep if needed for legacy compat symlink)
├── .env.example
├── .gitignore
├── composer.json
├── README.md
└── scripts/
    └── create-admin.php      # CLI admin creation — no hardcoded password
```

- `public/` is the **only** web-accessible directory.
- `.htaccess` denies `*.env`, `*.log`, `*.sql`, `storage/`, `config/`.
- `storage/hosting/` is gitignored; each account gets `storage/hosting/{id}/public_html/`.

---

## 6. Database Architecture

### 6.1 ER Overview

```
users 1──∞ user_roles ∞──1 roles 1──∞ role_permissions ∞──1 permissions
users 1──∞ hosting_accounts ∞──1 hosting_plans
hosting_accounts 1──∞ domains
hosting_accounts 1──∞ subdomains
hosting_accounts 1──∞ customer_databases
customer_databases 1──∞ database_users (via db_user_map or direct FK)
users 1──∞ notifications
users 1──∞ audit_logs
users 1──∞ sessions (optional, if DB sessions)
users 1──∞ password_resets / email_verifications
hosting_accounts 1──∞ usage_records
```

### 6.2 Table Specifications

All tables: `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, `ENGINE=InnoDB`, `CHARSET=utf8mb4`, `COLLATE=utf8mb4_unicode_ci`. FKs with `ON DELETE CASCADE` or `RESTRICT` as noted.

#### `users`
| Column | Type | Constraints |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| full_name | VARCHAR(120) | NOT NULL |
| username | VARCHAR(50) | UNIQUE, NOT NULL, indexed, `^[a-z0-9_\.]{3,50}$` |
| email | VARCHAR(190) | UNIQUE, NOT NULL |
| password_hash | VARCHAR(255) | NOT NULL (`password_hash` Argon2id/Bcrypt) |
| status | ENUM('pending','active','suspended','banned') | DEFAULT pending |
| email_verified_at | TIMESTAMP NULL | |
| last_login_at | TIMESTAMP NULL | |
| failed_login_count | SMALLINT UNSIGNED | DEFAULT 0 |
| lockout_until | TIMESTAMP NULL | |
| created_at/updated_at | TIMESTAMP | |

#### `roles`
`id, name VARCHAR(50) UNIQUE (admin,customer), display_name, description, created_at`

#### `permissions`
`id, name VARCHAR(100) UNIQUE (users.view, ...), display_name, description`

#### `role_permissions`
`role_id FK→roles, permission_id FK→permissions, PRIMARY KEY(role_id,permission_id)`

#### `user_roles`
`user_id FK→users CASCADE, role_id FK→roles CASCADE, PRIMARY KEY(user_id,role_id)`

#### `hosting_plans`
| Column | Type |
|---|---|
| id | BIGINT UNSIGNED PK |
| name | VARCHAR(50) UNIQUE (FREE,BASIC,PREMIUM) |
| slug | VARCHAR(50) UNIQUE |
| description | TEXT NULL |
| storage_limit_mb | INT UNSIGNED (500, 2048, 10240) |
| bandwidth_limit_mb | INT UNSIGNED |
| database_limit | SMALLINT UNSIGNED |
| domain_limit | SMALLINT UNSIGNED |
| subdomain_limit | SMALLINT UNSIGNED |
| status | ENUM('active','inactive') DEFAULT active |
| is_default | TINYINT(1) DEFAULT 0 |
| created_at/updated_at | TIMESTAMP |

#### `hosting_accounts`
| Column | Type |
|---|---|
| id | BIGINT UNSIGNED PK |
| user_id | BIGINT UNSIGNED FK→users |
| plan_id | BIGINT UNSIGNED FK→hosting_plans RESTRICT |
| username | VARCHAR(32) UNIQUE (system username, `fh_` prefix) |
| domain | VARCHAR(190) NULL |
| status | ENUM('pending','active','suspended','terminated') DEFAULT pending |
| storage_used_mb | DECIMAL(10,2) DEFAULT 0 |
| bandwidth_used_mb | DECIMAL(10,2) DEFAULT 0 |
| root_path | VARCHAR(255) NOT NULL (absolute, not user-controlled) |
| suspended_at | TIMESTAMP NULL |
| terminated_at | TIMESTAMP NULL |
| created_at/updated_at | TIMESTAMP |
| INDEX(user_id), INDEX(status), INDEX(plan_id) |

#### `domains`
`id, hosting_account_id FK CASCADE, domain VARCHAR(190) UNIQUE, status ENUM('pending','active','suspended'), is_primary TINYINT, created_at/updated_at`

#### `subdomains`
`id, hosting_account_id FK CASCADE, domain_id FK NULL, subdomain VARCHAR(63), full_domain VARCHAR(190) UNIQUE, status ENUM(...), created_at/updated_at`  
Example: `customer` + `freehost.example` → `customer.freehost.example`. Unique index on `full_domain`.

#### `customer_databases`
`id, hosting_account_id FK CASCADE, name VARCHAR(64) UNIQUE (prefix `fh_{account_id}_`), charset VARCHAR(20) DEFAULT utf8mb4, status ENUM('active','deleted'), created_at/updated_at` — actual MySQL DB creation via provisioner.

#### `database_users`
`id, customer_database_id FK CASCADE, username VARCHAR(32) UNIQUE, password_hash VARCHAR(255) (for app tracking, not MySQL auth — store encrypted or reset-only), host VARCHAR(60) DEFAULT localhost, privileges VARCHAR(100), created_at/updated_at`

#### `usage_records`
`id, hosting_account_id FK CASCADE, type ENUM('storage','bandwidth','database','domain','subdomain'), used INT, limit_val INT, recorded_at TIMESTAMP`

#### `sessions` (if DB-backed)
`id VARCHAR(128) PK, user_id FK NULL, ip_address VARCHAR(45), user_agent TEXT, payload TEXT, last_activity INT UNSIGNED, INDEX(user_id)`

#### `password_resets`
`id, user_id FK CASCADE, token_hash VARCHAR(255) (SHA256 of random token), expires_at TIMESTAMP, used_at TIMESTAMP NULL, created_at, INDEX(token_hash), INDEX(expires_at)`

#### `email_verifications`
`id, user_id FK CASCADE, token_hash VARCHAR(255), expires_at TIMESTAMP, verified_at TIMESTAMP NULL, created_at`

#### `notifications`
`id, user_id FK CASCADE, title VARCHAR(190), body TEXT, type VARCHAR(50), is_read TINYINT DEFAULT 0, read_at TIMESTAMP NULL, created_at, INDEX(user_id,is_read)`

#### `audit_logs`
`id, user_id FK NULL (system actions), action VARCHAR(100), resource_type VARCHAR(50), resource_id VARCHAR(50) NULL, ip_address VARCHAR(45), user_agent VARCHAR(255) NULL, result ENUM('success','failure'), metadata JSON NULL, created_at, INDEX(user_id), INDEX(action), INDEX(created_at)`

#### `system_settings`
`id, key VARCHAR(100) UNIQUE, value TEXT, type VARCHAR(20) (string,int,bool,json), is_secret TINYINT DEFAULT 0, updated_by FK NULL, created_at/updated_at`

### 6.3 Indexes & Constraints

- All FKs indexed; unique constraints on `users.email`, `users.username`, `domains.domain`, `subdomains.full_domain`, `customer_databases.name`.
- Composite: `hosting_accounts(user_id,status)`, `audit_logs(created_at,action)`.

### 6.4 Migrations Strategy

- `database/migrations/001_create_users_and_rbac.php` (or `.sql`) — users, roles, permissions, pivots + seed default roles/permissions.
- `002_create_hosting_plans.php`
- `003_create_hosting_accounts.php`
- `004_create_domains_subdomains.php`
- `005_create_databases.php`
- `006_create_support_tables.php` (sessions, resets, verifications, notifications, audit, settings, usage)
- Runner: `php scripts/migrate.php` (PDO, transactional, migration table `migrations` tracks applied). No framework needed.

---

## 7. Security Plan

### 7.1 Threat Matrix & Mitigations

| Threat (§38) | Severity | Mitigation | Verification |
|---|---|---|---|
| **SQL Injection** | Critical | PDO prepared statements everywhere; no string interpolation; Validators use allow-lists | Unit + security tests: inject `"' OR 1=1 --"` |
| **XSS** | Critical | `e()` helper (`htmlspecialchars ENT_QUOTES | ENT_HTML5`); CSP header; no `innerHTML` with user data; file preview sandboxed | Stored/reflected XSS tests |
| **CSRF** | High | Per-session token, double-submit not enough — synchronizer token in every POST/PUT/DELETE; `SameSite=Lax` + token | CSRF token missing/invalid → 419 |
| **IDOR** | Critical | Ownership checks in every Service (`$account->user_id === $authUser->id || hasPermission`); never trust URL id | Horizontal privilege tests |
| **Broken Access Control** | Critical | RBAC middleware on every route group; controller re-checks; no UI-only hiding | Force-browse tests as customer on `/admin/*` |
| **Path Traversal** | Critical | `PathGuard::resolve($base, $userPath)` → `realpath` + prefix check; block `..`, `//`, `\`, `:` (Windows), null byte `%00`, URL-encoded `%2e%2f`; jail to `storage/hosting/{id}/` | Fuzz: `../../../etc/passwd`, `..%2f..%2f` |
| **File Upload Bypass** | High | Extension allow-list, MIME via `finfo`, double-extension check, randomized stored name, size + quota, no execute in upload dir (`.htaccess php_flag engine off`), scan for `<?php` | Upload `shell.php.jpg` |
| **Session Fixation/Hijack** | High | `session_regenerate_id(true)` on login; `use_strict_mode=1`; `httponly`, `secure`, `samesite`; inactivity timeout 30m; IP/UA binding optionally | Session tests |
| **Auth Bypass** | Critical | `password_hash` (Argon2id if available else Bcrypt cost 12) + `password_verify`; never plaintext; timing-safe compare | Auth tests |
| **Privilege Escalation** | Critical | Role assignment only via admin with `users.edit` perm; no self-role change endpoint | Vertical privilege tests |
| **Brute Force** | High | RateLimiter (DB/file) per IP+user: login 5/15m, register 3/h, reset 3/h, upload 20/h; exponential backoff; `lockout_until` | Rate limit tests |
| **Account Enumeration** | Medium | Login/reset responses generic: “If account exists, email sent”; same timing | Enumeration tests |
| **Command Injection** | Critical | No `exec/shell_exec` from HTTP; provisioner allow-list only; `disable_functions` | Code review + grep `exec` |
| **Malicious Filenames** | High | Sanitize: `preg_replace('/[^a-zA-Z0-9._-]/','_', $name)`, length 255, no leading dot, no reserved Windows names (`CON`, `NUL`) | Upload tests |
| **Oversized Uploads** | Medium | `upload_max_filesize` + app quota + `Content-Length` check; deny > plan limit | Quota tests |
| **Unauthorized DB/File Access** | Critical | DB ownership + plan limit checks; file jail per account | IDOR tests |
| **Information Disclosure** | High | `display_errors Off`; generic 500 page; no stack in prod; logs to `storage/logs/` not web; `expose_php Off`; `.htaccess` blocks `.env` | Error handling tests |

### 7.2 Additional Controls

- **Password policy:** 8+ chars, 1 upper, 1 lower, 1 digit, optionally 1 symbol; `password_hash` with `PASSWORD_ARGON2ID` fallback to `PASSWORD_BCRYPT` cost 12; rehash on login if needed.
- **Email verification:** token 64 bytes `random_bytes`, SHA256 stored, 24h expiry, single-use, resend rate-limited 3/h.
- **Password reset:** same token scheme, 1h expiry, invalidate all prior tokens on use, do not reveal existence.
- **Audit logging:** every §26 action; never log `password`, `token`, `password_hash`.
- **Logging:** `storage/logs/app.log`, `auth.log`, `audit.log` with rotation (daily, keep 14 days); PSR-3-like levels.
- **Secrets:** only via `.env`; `config/app.php` fails safe if `DB_PASSWORD` missing; never commit `.env`.

### 7.3 Windows-Specific

- Block `C:` `D:` `\\` `:` `*` `?` `"` `<` `>` `|` in file paths.
- `realpath` on Windows returns `C:\...` — prefix check must be case-insensitive and normalized to `/`.

---

## 8. Phased Development Plan

> **Rule:** Complete → test → security review → docs → report → approval → next phase. No skipping.

### Phase 0 — Inspection ✅ (This Document)

- [x] Inspect filesystem, git, env, PHP, MySQL, Apache
- [x] Produce `docs/implementation-plan.md`
- [ ] **Review & approval** — stop here

### Phase 1 — Foundation (Est. 5–7 days)

**Goal:** Auth, RBAC, dashboards, sessions — the secure skeleton.

| Task | Files |
|---|---|
| 1.1 `composer.json` + `vlucas/phpdotenv` + PSR-4 autoload | `composer.json`, `config/bootstrap.php` |
| 1.2 `.env.example` + `.env` (gitignored) + `config/*.php` | `.env.example`, `config/app.php`, `config/database.php`, `config/session.php` |
| 1.3 `public/index.php` + `public/.htaccess` + `routes/web.php` + Router | `public/index.php`, `public/.htaccess`, `routes/web.php`, `app/Helpers/Router.php` |
| 1.4 `database/migrations/*` + `scripts/migrate.php` + `scripts/create-admin.php` | `database/migrations/*.php`, `scripts/*.php` |
| 1.5 `Database` singleton PDO wrapper (prepared, exceptions, transactions) | `config/database.php`, `app/Helpers/Database.php` |
| 1.6 `Users` + `Roles` + `Permissions` models/repos + seed | `app/Models/*`, `app/Repositories/*`, `database/seeders/*` |
| 1.7 Registration (validate, hash, pending/active per setting) | `app/Controllers/AuthController.php`, `app/Validators/*` |
| 1.8 Login/logout + session regeneration + lockout + rate limit | `app/Services/AuthService.php`, `app/Middleware/*` |
| 1.9 Password reset (token hash, expiry, invalidate) | `app/Controllers/PasswordResetController.php` |
| 1.10 Email verification (token, expiry, resend) — mail stub initially | `app/Controllers/VerificationController.php` |
| 1.11 RBAC middleware + permission checks | `app/Middleware/RbacMiddleware.php`, `app/Helpers/Auth.php` |
| 1.12 CSRF helper + middleware | `app/Helpers/Csrf.php`, `app/Middleware/CsrfMiddleware.php` |
| 1.13 Customer + Admin dashboards (Bootstrap 5, responsive) | `app/Views/dashboard/*`, `public/assets/*` |
| 1.14 Audit + app logging | `app/Services/AuditService.php`, `storage/logs/` |
| 1.15 Layout: navbar, sidebar, alerts, breadcrumbs | `app/Views/layouts/*` |

**Exit criteria:** Register → verify → login → dashboard → logout works; admin/customer RBAC enforced; no SQLi/XSS/CSRF bypass; `php scripts/create-admin.php` works.

### Phase 2 — Hosting (Est. 3–4 days)

- Plans CRUD (admin, `plans.manage` permission; FREE/BASIC/PREMIUM seed)
- Hosting accounts CRUD + ownership + IDOR guards + status transitions (pending→active→suspended→terminated) via `HostingService` + `LocalMockProvisioner::createHostingAccount()` (mkdir jail)
- Quota display (storage/bandwidth/db/domain) + enforcement backend
- Subdomain creation under `customer.freehost.example` (configurable `APP_DOMAIN`)
- Admin: manage accounts (suspend/activate), view stats
- Tests: quota, IDOR, status authZ

### Phase 3 — File Manager (Est. 5–6 days, highest security)

- `FileManagerService` with `PathGuard` (realpath + prefix jail per account)
- List, mkdir, upload (UploadGuard), download (X-Sendfile/stream), rename, delete, move, copy, create/edit text files (MIME text-only, size cap)
- Quota enforcement (storage_used recalculated from `du` mock)
- Frontend: Bootstrap file table, breadcrumbs, modals, CodeMirror/monaco for editor (or textarea)
- Aggressive security tests: traversal, upload bypass, IDOR, quota

### Phase 4 — Database Management (Est. 3 days)

- Customer: create/delete DB, create/delete DB user, reset password, view info — all via `DatabaseProvisioner` (mock: track in `customer_databases` + optional `CREATE DATABASE` if privileged)
- Limits per plan; ownership checks; never expose password after creation (show once + reset flow)
- Tests: authZ, limit, enumeration

### Phase 5 — Domains

- Domain model + subdomain management UI + status
- Architecture for future custom domains (validation, DNS stub)
- No hard-coded domain — `system_settings.main_domain`

### Phase 6 — Administration

- User search/view/create/edit/suspend/activate/delete (soft vs hard rules)
- Hosting/plan management, audit log viewer (filter, pagination), usage monitoring, system settings (platform name, registration toggle, verification required, default plan, maintenance mode, upload limits, password policy)
- Notifications (poll or DB, read/unread)

### Phase 7 — Provisioning Abstraction

- `HostingProvisionerInterface` formalized; `LocalMockProvisioner` + `NullProvisioner` for prod stub
- Job table `provisioning_jobs(id, account_id, operation, payload JSON, status, result JSON, created_at)` for async future
- Document Linux production mapping

### Phase 8 — Hardening

- Full checklist §38 sweep; `grep -R "exec\|shell_exec\|system\|passthru"` audit
- Rate limiter hardening, CSP, HSTS, security headers
- Error handling (generic 4xx/5xx pages, no leaks), log rotation
- Dependency audit (`composer audit`)

### Phase 9 — Documentation & Release

- Update `README.md`, `docs/installation.md`, `docs/architecture.md`, `docs/database.md`, `docs/security.md`, `docs/development.md`, `docs/deployment.md`, `docs/troubleshooting.md`
- Verify fresh clone → `composer install` → `.env` → `php scripts/migrate.php` → `php scripts/create-admin.php` → login → manual browser test (desktop/mobile)
- Tag `v1.0.0`

---

## 9. Configuration & Environment

### 9.1 `.env.example` (to create in Phase 1)

```ini
APP_NAME="FreeHost Manager"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/freehost-manager/public
APP_DOMAIN=freehost.example
APP_KEY=base64:GENERATE_WITH_scripts/generate-key.php

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freehost_manager
DB_USERNAME=freehost_app
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_LIFETIME=30
SESSION_SECURE=false
SESSION_SAMESITE=Lax

MAIL_MAILER=log
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@freehost.example
MAIL_FROM_NAME="FreeHost Manager"

UPLOAD_MAX_MB=20
PASSWORD_MIN_LENGTH=8
REGISTRATION_ENABLED=true
EMAIL_VERIFICATION_REQUIRED=false
DEFAULT_PLAN_SLUG=free
MAINTENANCE_MODE=false
```

- `.env` never committed; `config/*.php` reads via `$_ENV` with defaults + fails safe (throw if DB_* missing).

### 9.2 Apache

- Add `<VirtualHost *:80>` docroot `C:/AppServ/www/freehost-manager/public`, `AllowOverride All`, or at minimum `.htaccess` rewrite.
- Block: `RedirectMatch 404 /\.env`, `\.git`, `\.log`, `\.sql`.

---

## 10. Provisioning Abstraction

```php
interface HostingProvisionerInterface {
    public function createHostingAccount(HostingAccount $account): ProvisionResult;
    public function suspendHostingAccount(HostingAccount $account): ProvisionResult;
    public function activateHostingAccount(HostingAccount $account): ProvisionResult;
    public function terminateHostingAccount(HostingAccount $account): ProvisionResult;
    public function createSubdomain(HostingAccount $account, string $subdomain): ProvisionResult;
    public function createDatabase(HostingAccount $account, string $dbName): ProvisionResult;
    public function deleteDatabase(HostingAccount $account, string $dbName): ProvisionResult;
    // ... createDomain, deleteDomain, createDatabaseUser, etc.
}
final class LocalMockProvisioner implements HostingProvisionerInterface {
    // mkdir storage/hosting/{id}/public_html, touch index.php, log to audit
    // no shell_exec
}
```

Production implementation (`LinuxProvisioner`) will SSH/API to hosting nodes — out of scope for local dev, documented in `docs/deployment.md`.

---

## 11. Testing Strategy

### 11.1 Pyramid

- **Unit (PHPUnit 9/10, PHP 7.3 compat check):** Validators, `PathGuard`, `UploadGuard`, `RateLimiter`, `QuotaCalculator`, `AuthService`, `Csrf`.
- **Integration (PDO + test DB `freehost_manager_test`):** registration, login, hosting create, file ops, DB ops — with transaction rollback per test.
- **Security (dedicated suite):** SQLi payloads, XSS payloads, CSRF missing, IDOR (user A accesses user B id), path traversal fuzz, upload bypass (`.php`, `.phtml`, double ext, MIME spoof), brute force.
- **Manual browser:** Chrome/Firefox responsive (DevTools), forms, error messages, navigation, dashboards, file manager — checklist per phase.

### 11.2 Tooling

- `composer.json` `require-dev: phpunit/phpunit`, `phpstan` optionally.
- `phpunit.xml` with `bootstrap=tests/bootstrap.php`.
- `tests/` mirrors `app/`: `Unit/Validators`, `Unit/Security/PathGuardTest.php`, `Integration/AuthTest.php`, `Security/TraversalTest.php`.
- No mark-complete without `phpunit` green + manual check.

### 11.3 Test-First Mindset (§40)

Define expected behavior → implement → test → fail → fix → retest → security review → doc.

---

## 12. Deployment Plan

### 12.1 Local Development (Windows/AppServ)

1. Upgrade PHP to 8.2 (or 8.3) — replace `C:\AppServ\php7` + update `httpd.conf`.
2. Recover MySQL root password (reset via `--skip-grant-tables` or `my.ini`), create `freehost_manager` DB + `freehost_app` user `GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP ON freehost_manager.*`.
3. `git clone` → `composer install` → `copy .env.example .env` → edit DB creds → `php scripts/migrate.php` → `php scripts/create-admin.php` (interactive, no default password) → `http://localhost/freehost-manager/public/` → login.

### 12.2 Production (Linux — future)

- Separate control panel host (PHP-FPM, no customer code execution) + hosting nodes (Apache/Nginx, PHP-FPM pools per customer, chroot/jail, quotas via `quota`/`cgroups`, MySQL per-node, DNS API, Let's Encrypt).
- Provisioning worker runs as limited user, queue (DB table or Redis), validated allow-list commands, logging, job status.
- Backups, monitoring, TLS termination, WAF, fail2ban.

---

## 13. Documentation Plan

| Document | Phase | Content |
|---|---|---|
| `README.md` | 1, 9 | Overview, stack, quick start, status |
| `docs/installation.md` | 1 | Step-by-step Windows + Linux, DB setup, admin creation |
| `docs/architecture.md` | 1, 7 | Layers, request lifecycle, provisioning, future infra |
| `docs/database.md` | 1 | Schema, ER, migrations, seed |
| `docs/security.md` | 1, 8 | Threat model, mitigations, headers, checklist |
| `docs/development.md` | 1 | Conventions, git workflow, testing |
| `docs/deployment.md` | 7, 9 | Apache vhost, .htaccess, Linux prod, backups |
| `docs/troubleshooting.md` | 9 | Common errors, MySQL reset, PHP upgrade |
| `docs/api.md` | later | Stub — business logic is API-ready (§44) |

Docs reflect **actual** implementation, never speculative features.

---

## 14. Risks & Limitations

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| R1 | **PHP 7.3 EOL** — no security patches since 2021 | Critical | Upgrade to PHP 8.2+ in Phase 1 prereq; code targets 8.1+; verify before any auth code |
| R2 | **Windows cannot provide filesystem/process isolation** | Critical | Declare dev-only; `PathGuard` is app-level; prod requires Linux jails/cgroups; never claim multi-tenant safety on Windows |
| R3 | **MySQL root password unknown** | Blocking | Reset procedure documented; create least-privilege app user |
| R4 | **Apache shared DocumentRoot exposes `../freehost-manager/.env`** | High | Move docroot to `public/` + `.htaccess` deny; add vhost |
| R5 | **Apache/MySQL versions from 2019** | Medium | Patch or note in docs; not blocking dev |
| R6 | **No shell isolation — `disable_functions` empty** | High | Set in `php.ini` + never call `exec` from web; provisioner is mock |
| R7 | **Session `httponly/samesite/strict_mode` off** | High | Fix in Phase 1 `config/session.php` + `php.ini` |
| R8 | **Customer PHP execution in same origin as control panel** | Critical (future) | Prod: separate hosts; dev: uploads not executed (`.htaccess php_flag engine off` in `storage/hosting/`) |
| R9 | **No Node.js — frontend build limited** | Low | Use CDN Bootstrap 5; no build step required for MVP |
| R10 | **Single developer, no CI** | Medium | `php -l` + `phpunit` + manual `git diff` before push; secret scan |

**Known limitations honestly documented:** local AppServ is not a secure public host; DNS/SSL/billing/monitoring/reseller are future phases, not MVP.

---

## 15. Definition of Done (per phase)

Per §52, a feature is done only when:

- [ ] Works (happy + error paths)
- [ ] Tested (unit + integration + security + manual browser)
- [ ] AuthZ verified (IDOR, RBAC)
- [ ] Security reviewed (no secrets, no injection, no traversal)
- [ ] UI works (desktop/tablet/mobile, Bootstrap 5)
- [ ] DB correct (FKs, indexes, migrations reversible)
- [ ] Docs updated (reflects reality)
- [ ] No regressions (existing flows still pass)
- [ ] `git status`/`git diff` clean of secrets
- [ ] Reported: Completed / Files Changed / DB Changes / Tests / Security Review / Known Issues / Next Phase

---

## 16. Next Steps

### Immediate (awaiting your approval)

1. **Review this plan** — confirm structure, DB design, security controls, phase order.
2. **Decide on PHP upgrade** — approve upgrading `C:\AppServ\php7` → PHP 8.2/8.3 (recommended) or constrain code to 7.3 (not recommended, insecure).
3. **Recover MySQL credentials** — provide or reset root password so Phase 1 migrations can run.
4. **Approve Phase 1 start** — reply “Proceed to Phase 1” to authorize foundation implementation.

### Phase 1 Kickoff Checklist (will execute after approval)

- [ ] Backup `C:\AppServ\php7/php.ini` and `httpd.conf`
- [ ] Install PHP 8.2, update Apache `LoadModule`/`PHPIniDir`, set `php.ini` hardening
- [ ] `composer.json` + `composer install` (requires Composer — install if missing)
- [ ] Create `.env.example` + `config/` + `public/index.php` + `public/.htaccess`
- [ ] Create `database/migrations/` + `scripts/migrate.php` + `scripts/create-admin.php`
- [ ] Create MySQL DB `freehost_manager` + app user
- [ ] Implement auth/RBAC/middleware/CSRF/dashboards per Phase 1 table
- [ ] Run `phpunit`, manual test, security sweep, then **report Phase 1** per §62

---

> **Golden rule (§63):** Security > Correctness > Maintainability > Testability > UX > Performance > Convenience.
> This plan builds FreeHost Manager as if it will eventually serve real customers — starting with a secure, testable foundation.

**— End of Implementation Plan —**

