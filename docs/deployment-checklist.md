# Deployment Checklist — FreeHost Manager (Production)

## Pre-Deployment
- [x] PHP 8.3+ (`php -v` 8.3.33), `composer.json` `php ^8.3`, `composer audit` clean
- [x] `composer install --no-dev --optimize-autoloader` (prod, no `phpunit`)
- [x] `.env` from `.env.example` with `APP_ENV=production` `APP_DEBUG=false` `APP_URL=https://panel.freehost.example` `APP_DOMAIN=freehost.example` `APP_KEY=base64:...` `DB_*` `SESSION_SECURE=true` `SESSION_SAMESITE=Strict`
- [x] `storage/` 750, `storage/logs` 750, `storage/sessions` 0700, `.env` 640, `public/` DocumentRoot, `storage/.htaccess` `Require all denied`
- [x] `php -l` all `app/**/*.php` clean, `php scripts/migrate.php` all `SKIP` (or `[RUN]` 007), `php vendor/bin/phpunit --testdox` 179 tests OK

## Database
- [x] MySQL 8.0+ `freehost_manager` `utf8mb4` `freehost_app` least-privilege `SHOW GRANTS`, indexes/FKs verified, `migrations` table `batch`, backup `mysqldump -u root -p freehost_manager > backup-$(date +%F).sql`

## Filesystem
- [x] `PathGuard` symlink block, `UploadGuard` blocklist, `isSafeFilename`, `public/.htaccess` blocks `\.env`, `storage/.htaccess` `php_flag engine off`, quota `RecursiveDirectoryIterator` server-side, `rrmdir` child-first

## Provisioning
- [x] `hosting_nodes` `local-mock-1` active, `provisioning_jobs` idempotency `UNIQUE`, retry max 3, HMAC `X-Signature` 5-min TTL + nonce `rate_limits`, no `exec`, `LocalMock*` only (no real nodes/DNS/ACME)

## Security
- [x] `grep -R "exec(" app/` negative (with `(?<!->)`), `e()` everywhere, `CsrfMiddleware` on all POST, `Rbac(admin)` on `/admin/*`, IDOR `hosting_account.user_id`, `password_hash` Argon2id, `RateLimiter` 5/15m, `Logger::scrub`, audit `provisioning.*` etc., headers `X-Content-Type-Options`, `HSTS` when `HTTPS`, `CSP`, `.env` not in `public/`

## Monitoring/Backup
- [x] `GET /admin/monitoring` `healthy/warning/critical`, `GET /admin/backups` `expireOld` marks `expired` not `DELETE`, `usage_records` snapshotted, `audit_logs` 24h stats

## Final Smoke (via `php -S localhost:8000 -t public` with `php83`)
- [x] `POST /register` → `POST /login` → `GET /dashboard` (hosting table) → `GET /hosting` → `POST /hosting` (create `fh_*`) → `GET /hosting/{id}` → `GET /hosting/{id}/files` (mkdir/upload) → `GET /hosting/{id}/databases` (create `fh_{id}_*`) → `GET /hosting/{id}/dns` → `GET /hosting/{id}/ssl` → `GET /hosting/{id}/usage` → `GET /hosting/{id}/backups` → `GET /billing` → `GET /admin/monitoring` (admin only) → `GET /admin/users` → `GET /admin/audit`

## Rollback
- [x] Keep `backup-$(date +%F).sql` + `storage/` tar, `git log` `f8dd72c` etc., to rollback: `git checkout <prev>`, `mysql freehost_manager < backup.sql`, `php scripts/migrate.php` (idempotent), verify `php -l` + `phpunit`.

## Go/No-Go
- [x] `APP_DEBUG=false`, `SESSION_SECURE=true`, `composer audit` clean, 179 tests OK, `php -l` clean, `migrations` SKIP, `grep` no secrets, `working tree clean`, **DO NOT** claim deployed or provisioned real server.

