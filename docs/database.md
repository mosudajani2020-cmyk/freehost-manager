# Database — FreeHost Manager (Phase 6)

## Engine & Collation
All tables: `ENGINE=InnoDB`, `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.

## Migrations
Runner: `php scripts/migrate.php` (requires PHP 8.3+).
- Creates `freehost_manager` DB if missing.
- Tracks in `migrations (id, migration, batch, executed_at)`.
- Executes unapplied `database/migrations/*.php` in lexical order, without wrapping DDL in transactions (MySQL implicit commit).

Files:
- `001_create_users_and_rbac.php` — users, roles, permissions, pivots + seed.
- `002_create_hosting_plans_and_accounts.php` — hosting_plans, hosting_accounts, domains, subdomains, customer_databases, database_users, usage_records + seed plans.
- `003_create_support_tables.php` — password_resets, email_verifications, notifications, audit_logs, system_settings, rate_limits + seed settings.
- `004_create_provisioning.php` — hosting_nodes, provisioning_jobs + seed local-mock-1.
- `005_create_dns_ssl.php` — dns_records, ssl_certificates, subdomains dns_status/ssl_status columns.

Rerunning is idempotent — `IF NOT EXISTS` and `[SKIP]`.

## Schema Summary (Phase 1)
- **users** — id, full_name, username UNIQUE, email UNIQUE, password_hash, status ENUM(pending,active,suspended,banned), email_verified_at, last_login_at, failed_login_count, lockout_until, timestamps. Index on status.
- **roles** — id, name UNIQUE (admin,customer), display_name.
- **permissions** — id, name UNIQUE (users.view,...), display_name.
- **role_permissions** — (role_id, permission_id) PK, FK cascade.
- **user_roles** — (user_id, role_id) PK, FK cascade.
- **hosting_plans** — id, name UNIQUE, slug UNIQUE, storage/bandwidth/database/domain/subdomain limits, status, is_default.
- **hosting_accounts** — id, user_id FK, plan_id FK, username UNIQUE, domain, status ENUM(pending,active,suspended,terminated), storage/bandwidth used, root_path, suspended/terminated_at.
- **domains** — id, hosting_account_id FK, domain UNIQUE, status, is_primary.
- **subdomains** — id, hosting_account_id FK, domain_id FK nullable, subdomain, full_domain UNIQUE, status.
- **customer_databases** — id, hosting_account_id FK, name UNIQUE (prefix `fh_{id}_`), charset, status.
- **database_users** — id, customer_database_id FK, username UNIQUE, encrypted_password TEXT NULL, host, privileges.
- **usage_records** — id, hosting_account_id FK, type ENUM, used, limit_val, recorded_at.
- **password_resets** — id, user_id FK, token_hash (SHA256), expires_at, used_at.
- **email_verifications** — id, user_id FK, token_hash, expires_at, verified_at.
- **notifications** — id, user_id FK, title, body, type, is_read, read_at.
- **audit_logs** — id, user_id FK nullable, action, resource_type, resource_id, ip_address, user_agent, result ENUM, metadata JSON, created_at. Indexed on user_id, action, created_at.
- **system_settings** — id, key UNIQUE, value, type, is_secret, updated_by FK, timestamps.
- **rate_limits** — id, rate_key, created_at (also created dynamically by RateLimiter).
- **migrations** — id, migration UNIQUE, batch, executed_at.

## Seeds
- Roles: admin (all perms), customer (hosting.view, hosting.create, files.view, files.upload, files.delete, databases.create, databases.delete).
- Permissions: 16 granular (users.*, hosting.*, files.*, databases.*, plans.manage, audit.view, settings.manage).
- Plans: FREE (500 MB/5 GB/2 DB/1 domain/2 subs, default), BASIC (2 GB/20 GB/5 DB), PREMIUM (10 GB/100 GB/20 DB).
- Settings: platform_name, main_domain, registration_enabled, email_verification_required, default_plan_slug, maintenance_mode, upload_max_mb, password_min_length.

## Security Notes
- All queries via PDO prepared statements (see `app/Helpers/Database.php`).
- Password hashes: Argon2id or Bcrypt, never plaintext. DB user passwords: `encrypted_password` (not hash) for future authenticated encryption.
- Least privilege: app uses `freehost_app` with limited GRANT, never root.
- Tokens: `random_bytes(32)` → hex → `hash('sha256')` stored, 1h (reset) / 24h (verify) expiry, single-use.

## Phase 2-6 Usage
- `hosting_plans` full CRUD via `HostingPlanRepository` (admin only, validated limits 0-1M MB, 0-1000 counts).
- `hosting_accounts` created via `HostingService::createAccount` (ownership, unique username, status lifecycle, root_path under `storage/hosting`, now also records `provisioning_jobs`).
- `subdomains` via `HostingService::createSubdomain` (strict regex, reserved, duplicate `full_domain` UNIQUE, quota `plan.subdomainLimit`, auto-creates `dns_records` + `ssl_certificates` mock).
- `system_settings.main_domain` is source of truth for subdomains (fallback `APP_DOMAIN`).
- **File Manager (Phase 3)** uses filesystem only (`storage/hosting/{username}_{rand}/public_html`) — no new tables; quota calculated via `RecursiveDirectoryIterator` vs `plan.storageLimitMb`.
- **Database Hosting (Phase 4)** uses `customer_databases` (`hosting_account_id` FK, `name` UNIQUE `fh_{accountId}_{part}`) and `database_users` (`username` UNIQUE `fh_{accountId}_u_{part}`, `encrypted_password` via `APP_KEY`), quota `plan.databaseLimit`.
- **Provisioning (Phase 5)** uses `hosting_nodes` (name/hostname UNIQUE, `api_key_hash` preview, `status`, `current_accounts`) and `provisioning_jobs` (`job_uuid` UNIQUE, `idempotency_key` UNIQUE, `status` 8 states, `attempts`, `payload` JSON, FKs).
- **DNS/SSL (Phase 6)** uses `dns_records` (`hosting_account_id` FK, `hostname`+`type` UNIQUE, `value`, `ttl`, `status` pending/active/failed/suspended/removed) and `ssl_certificates` (`hostname` UNIQUE, `status` pending/issuing/active/renewing/expired/failed/revoked, `provider` local_mock, `expires_at`), plus `subdomains.dns_status/ssl_status` sync.
- All FKs/indexes verified; `hosting_accounts(username)` UNIQUE, `subdomains(full_domain)` UNIQUE, `customer_databases(name)` UNIQUE, `database_users(username)` UNIQUE, `dns_records(hostname,type)` UNIQUE, `ssl_certificates(hostname)` UNIQUE, `provisioning_jobs(idempotency_key)` UNIQUE.

## Next Phases
Phase 7 will handle advanced provisioning worker, billing, and production hardening.
