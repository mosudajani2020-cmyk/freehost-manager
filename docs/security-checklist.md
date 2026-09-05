# Security Checklist — FreeHost Manager (Production)

## Authentication
- [x] `password_hash` Argon2id/Bcrypt, `password_verify`, rehash, `PASSWORD_MIN_LENGTH` 8 + upper/lower/digit, `failed_login_count`/`lockout_until` 5/15m, `RateLimiter` 5/15m, generic `Invalid credentials`
- [x] `session.use_strict_mode=1`, `HttpOnly`, `SameSite=Lax` (Strict in prod), `Secure` when HTTPS, `regenerate_id` on login, 30m inactivity

## Authorization
- [x] RBAC `admin`/`customer` + 16 perms, `RbacMiddleware` on every `/admin/*`, server-side checks, no UI-only hiding, IDOR `hosting_account.user_id===actorId` for hosting/files/databases/dns/ssl/usage/backups

## CSRF/XSS/SQLi
- [x] `CsrfMiddleware` 419 on all POST/PUT/PATCH/DELETE, `SameSite` not sole
- [x] `e()` `htmlspecialchars ENT_QUOTES|ENT_SUBSTITUTE` everywhere, CSP `default-src 'self'` + `X-Content-Type-Options` `nosniff`, no `innerHTML` with user data
- [x] PDO prepared everywhere, no `SELECT * FROM users WHERE username = '$username'`

## File/System
- [x] `PathGuard::resolve` + symlink block `is_link`, `UploadGuard` blocklist `php/phtml/phar`, double ext, `finfo` MIME, php sniff, `isSafeFilename` `CON`/`<>:`, `rrmdir` child-first, no `exec`
- [x] `storage/hosting` isolated via `PathGuard`, `php_flag engine off` in `storage/.htaccess`, `public/.htaccess` blocks `\.env`
- [x] Quota `RecursiveDirectoryIterator` vs `plan.*Limit` server-side, `pending→running→completed` not destructive, `usage_records` snapshots

## Provisioning/DNS/SSL
- [x] `HostingProvisionerInterface` + `LocalMockProvisioner` only logs, `HostingNode` `api_key_hash` preview, HMAC `X-Signature` 5-min TTL + nonce `rate_limits`, `job_uuid`/`idempotency_key` UNIQUE, retry max 3
- [x] `DnsService` strict `hostname` 253/label 63 `^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$`, `APP_DOMAIN` takeover `hostname===main || ends_with(.main)`, `hostname` UNIQUE, lifecycle `pending/active/failed/suspended/removed`, `LocalMockDnsProvider` no real API
- [x] `SslService` `pending/issuing/active/renewing/expired/failed/revoked` 90d mock, `LocalMockCertificateProvider`, no `exec`

## Logging/Secrets
- [x] `Logger::scrub` redacts `password/token`, `AuditService` never logs `password/token`/`encrypted_password` plaintext, `APP_KEY` base64 32 bytes from `scripts/generate-key.php`, `DB_PASSWORD` vault, `fhm_` hash preview only

## Headers/Cookies/CORS
- [x] `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, `CSP`, `Strict-Transport-Security` when `HTTPS`, `X-XSS-Protection: 0`

## Production
- [x] `APP_ENV=production` `APP_DEBUG=false` generic 500, `SESSION_SECURE=true`, `SESSION_SAMESITE=Strict`, `composer audit` clean, `php -l` clean, `migrations` idempotent, `freehost_app` least-privilege, indexes/FKs, `php -S` never in prod, `storage` 750, `.env` 640
