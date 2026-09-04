# Security — FreeHost Manager (Phase 2)

## Baseline (Phase 1 implemented)
- **SQL Injection** — PDO prepared statements everywhere (`Database::query` with `prepare/execute`). No concatenation. Tests include `' OR 1=1 --` payload in validators.
- **XSS** — `e()` helper (`htmlspecialchars ENT_QUOTES|ENT_SUBSTITUTE`). All views escape. CSP header in `public/index.php`. File editor will be isolated (Phase 3).
- **CSRF** — Synchronizer token (`App\Security\Csrf`): 32 bytes `random_bytes`, per-session, validated on POST/PUT/PATCH/DELETE via `CsrfMiddleware` (419 on failure). Also checks `X-CSRF-TOKEN` header. Never rely on SameSite alone (but SameSite=Lax set).
- **IDOR** — Ownership checks in Services (e.g. `hosting_accounts.user_id === currentUser.id`). RBAC enforced server-side via `RbacMiddleware`. No UI-only hiding.
- **Broken Access Control** — RBAC: roles `admin,customer`, permissions granular (`users.view`...). Every protected route has middleware; controllers re-check.
- **Path Traversal** — `PathGuard::resolve($base,$userPath)` uses normalization + realpath prefix check (case-insensitive on Windows), blocks `..`, absolute, encoded (`%2e`), null byte, Windows drives. Tested with `../../../etc/passwd`, `%2e%2f`, `C:\`.
- **File Upload Bypass** — `UploadGuard` (blocklist `php,phtml,phar,cgi,htaccess`, double extension, MIME via `finfo`, php content sniff). Phase 1 no file manager yet; guard is unit-tested.
- **Session** — `session.use_strict_mode=1`, `use_only_cookies=1`, `cookie_httponly=1`, `cookie_samesite=Lax`, `Secure` when HTTPS, `session_regenerate_id(true)` on login, inactivity timeout 30m (`public/index.php`), UA hash anomaly log (not strict IP binding).
- **Password Storage** — `password_hash` Argon2id else Bcrypt cost 12, `password_verify`, rehash on login. Never plaintext. DB user passwords: `encrypted_password` (future encryption, not hash) shown once.
- **Rate Limiting & Lockout** — `RateLimiter` DB-backed (table `rate_limits`) with session fallback. Login 5/15m per IP+user, register 3/h, reset 3/h, verify 3/h. Lockout 15m after 5 failures (`users.lockout_until`). Audit logs blocked attempts.
- **Account Enumeration** — Generic responses: login "Invalid credentials", reset/verify "If account exists, email sent".
- **Command Injection** — No `exec/shell_exec` from HTTP. `disable_functions` recommended in php.ini; provisioner will be allow-list only.
- **Secrets** — `.env` excluded, `config/*` never logs values, `Logger::scrub` redacts password/token, `AuditService` never logs secrets.

## Headers
Set in `public/index.php` and `.htaccess`:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- CSP: self + cdn.jsdelivr.net/cdnjs.

## Logging & Audit
- `storage/logs/app.log`, `auth.log`, `mail.log` (dev mail driver). No secrets.
- `audit_logs` table: login, registration, password reset, email verify, admin actions, with ip, ua, result, metadata JSON.

## Windows-Specific
- Blocks `C:`, `\\`, `:`, `* ? " < > |` in paths.
- `realpath` prefix check handles backslashes.

## Phase 2 Additions
- **Hosting quotas** — enforced in `HostingService` (server-side): storage/bandwidth/database/domain/subdomain limits vs plan; never trust browser values; tested with limit 2 on FREE.
- **Lifecycle** — pending→active→suspended→terminated with admin-only transitions, suspended/terminated block subdomain creation; audit logged.
- **Subdomain validation** — strict regex `^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$`, reserved (`www` etc.), duplicate `full_domain` UNIQUE, main domain from `system_settings`/env (never hard-coded).
- **Ownership/IDOR** — every hosting/subdomain/plan operation checks `hosting_accounts.user_id === actorId` or `admin` role; URL ID tampering returns 403.
- **Plan RBAC** — `plans.manage` only for admin; customer GET/POST to `/admin/plans` denied via RbacMiddleware (tested).
- **Provisioning** — `LocalMockProvisioner` only creates safe `storage/hosting` dirs; no `exec/system`; `STORAGE_PATH` fallback for tests; audit logs for create/suspend/activate/terminate.

## Unimplemented (deferred to Phase 3+)
- Full file manager enforcement (move/copy) — guard ready, enforcement Phase 3.
- Real DNS/SSL/production isolation — still mocked.

## Verification (Phase 2)
- 56 PHPUnit tests (PHP 8.3): +15 Phase2Hosting tests covering IDOR, quota, duplicate/invalid subdomain, suspended/terminated, SQLi, XSS, CSRF, audit, no exec, admin lifecycle.
- Manual: customer create hosting → view → create subdomain → duplicate rejected → quota blocked → admin suspend/activate/terminate; customer cannot access another user's hosting via URL.

## Recommendations Before Production
- Upgrade AppServ PHP 7.3 → 8.3, Apache 2.4.41 → latest, set `expose_php Off`, `display_errors Off`, `session.cookie_secure 1`, `open_basedir` per vhost.
- Move DocumentRoot to `public/`, enforce `Require all denied` on `storage/`.
- Use HTTPS (HSTS), rotate `APP_KEY`, use SMTP with TLS, rotate DB passwords.
