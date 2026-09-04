# Security — FreeHost Manager (Phase 4)

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

## Phase 3 Additions — File Manager
- **Isolation** — every op via `PathGuard::resolve(root, relative)` + prefix check (realpath, case-insensitive Windows, encoded/null/absolute blocked); browser path never trusted; absolute `C:\`/`/var` never displayed.
- **Ownership** — `FileService::requireActiveAccount` checks `hosting_account.user_id===userId` and `status==='active'` for all 10 routes; IDOR tested (user2 cannot list user1 files).
- **Upload** — `UploadGuard`: extension blocklist (`php,phtml,phar`), double extension, MIME `finfo`, php content sniff, size `20MB`, quota `remainingBytes`, sanitized `basename`, duplicate auto-rename, `move_uploaded_file` only after validation; no trust in client MIME/size.
- **Download** — validates ownership/active, `PathGuard`, `is_dir` blocked, flag files `.suspended/.htaccess` blocked, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`.
- **Text edit** — allow-list `txt,html,css,js,json,xml,md,php,htaccess...` 512KB limit, binary null check, atomic `tmp+rename`, escaped display (`e()`), quota delta check.
- **Mkdir/rename/delete** — `isSafeFilename` (no `/\0` `..` `.hidden` `CON` `<>:*?`), no traversal, no root deletion, no overwrite, audit `directory.create`, `file.delete`, `file.rename`.
- **Quota** — `getQuotaInfo` via `RecursiveDirectoryIterator` vs `plan.storageLimitMb`; enforced on upload/create/edit; remaining displayed; negative/overflow prevented.
- **Audit** — `file.list/upload/download/create/edit/rename/delete`, `directory.create/delete` with `hosting_account_id`, `relative_path`, `size`, never file content/secrets.
- **No exec** — grep confirms no `exec/system/passthru` in `app/`; customer PHP never executed via control panel (`storage/.htaccess php_flag engine off`).

## Verification (Phase 3)
- **93 PHPUnit tests (PHP 8.3):** 56 prior + 37 Phase3FileManager covering list own/block another/IDOR/traversal/encoded/null/absolute/Windows/Linux/escape/.env/delete root/rename outside/download outside/upload outside/invalid names/extensions/oversized/quota/duplicate/suspended/terminated/unauth/CSRF/XSS/malicious/audit/no secrets/edit ownership/size/rename/mkdir/delete/download/quota/php upload.
- Manual (php83 -S): login → hosting → File Manager → browse `/public_html` → mkdir → upload txt → duplicate auto-rename → create text → edit → rename → download → delete → quota bar updates; suspended account blocked.

## Phase 4 Additions — Database Hosting
- **Naming:** generated `fh_{accountId}_{part}` and `fh_{accountId}_u_{part}`, validated `^[a-z][a-z0-9_]{2,29}$`, reserved (`mysql` etc.), no spaces/quotes/`;`/`--`, length 64/32, prevents SQL injection via identifier.
- **Least-privilege:** never use root; `LocalMockProvisioner` only logs, no `CREATE DATABASE` as root; `freehost_app` has limited GRANT only on `freehost_manager.*`; no credentials in code/logs/Git/URLs.
- **Credentials:** `random_bytes` 16 chars (upper/lower/digit/symbol), encrypted with `APP_KEY` (`sodium_secretbox` or `AES-GCM`), stored `encrypted_password`, shown once via flash, never logged, decrypt via `DatabaseService::decryptPassword`, not in audit metadata.
- **Ownership/IDOR:** every DB/user op checks `hosting_account.user_id===actorId` and `status==='active'`; URL ID tampering → 403; cross-customer access blocked.
- **Quota:** `plan.databaseLimit` enforced server-side (FREE 2) — tested; no trust in browser.
- **Suspended/terminated:** blocked for create/delete/user ops.
- **CSRF:** all POST (`/databases`, `/databases/delete`, `/databases/{id}/users`) via `CsrfMiddleware`.
- **Audit:** `database.create/delete`, `database.user_create/delete` with safe metadata (name, username, not password).

## Verification (Phase 4)
- **112 PHPUnit tests (PHP 8.3):** 93 prior + 19 Phase4Database covering creation/ownership/IDOR/duplicate/invalid/SQLi/quota/suspended/terminated/user create/unauthorized/duplicate/invalid names/unauthorized deletion/audit/secret leakage/CSRF/no exec/XSS.
- Manual (php83 -S): hosting → Databases → create `mydb` → duplicate rejected → invalid `bad name` rejected → quota 2 → create user `myuser` → password shown once → duplicate user rejected → delete user → delete db → admin `/admin/databases` view.

## Recommendations Before Production
- Upgrade AppServ PHP 7.3 → 8.3, Apache 2.4.41 → latest, set `expose_php Off`, `display_errors Off`, `session.cookie_secure 1`, `open_basedir` per vhost.
- Move DocumentRoot to `public/`, enforce `Require all denied` on `storage/`.
- Use HTTPS (HSTS), rotate `APP_KEY`, use SMTP with TLS, rotate DB passwords.
