# Customer Manual — FreeHost Manager

## Registration & Login
- `GET /register` (full_name, username `^[a-z0-9_\.]{3,50}$`, email, password `StrongPass1` 8+ upper/lower/digit, confirm) → `POST /register` (RateLimiter 3/h, duplicate check, `password_hash` Argon2id) → `GET /login` → `POST /login` (RateLimiter 5/15m, `regenerate_id`, lockout 5/15m) → `GET /dashboard`.

## Dashboard
`GET /dashboard` shows `Welcome`, `hostingAccounts` (ID, username, plan, status, storage `used/limit`, subdomains `used/limit`), `Recent activity` (audit), `Notifications`. Sidebar: `Hosting` → `GET /hosting`, `Files` → `/hosting/{id}/files`, `Databases` → `/hosting/{id}/databases`, `DNS` → `/hosting/{id}/dns`, `SSL` → `/hosting/{id}/ssl`, `Usage` → `/hosting/{id}/usage`, `Backups` → `/hosting/{id}/backups`.

## Hosting
- `GET /hosting` (cards with plan, storage, status) → `GET /hosting/create` (username `^[a-z0-9]{3,32}$`, select `FREE/BASIC/PREMIUM`) → `POST /hosting` (CSRF, unique, 5 per user, `LocalMockProvisioner` creates `storage/hosting/{username}_{rand}/public_html` + `.htaccess`, audit) → `GET /hosting/{id}` (plan limits, storage/bandwidth, subdomains, buttons: `File Manager`, `Databases`, `DNS`, `SSL`, `Usage`, `Backups` if `active` else disabled).
- `POST /hosting/{id}/subdomain` (sub `^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$`, reserved `www` blocked, quota `plan.subdomainLimit` 2/5/50, duplicate `full_domain` UNIQUE, auto-creates `dns_records` `A` + `ssl_certificates` `active` 90d mock, audit).

## File Manager
`GET /hosting/{id}/files?path=` (breadcrumbs `relative` only, quota bar `used/limit/remaining %`, `Files` table with `e()` escaped names, `Download` `Content-Disposition: attachment`, modals `Rename`/`Delete` with `csrf_field()`):
- `POST /hosting/{id}/files/mkdir` ( `isSafeFilename`, no `..`, quota)
- `POST /hosting/{id}/files/upload` (`UploadGuard` blocklist `php`, `finfo` MIME, size 20MB, quota `remainingBytes`, sanitized `basename`, duplicate `name_1`, `move_uploaded_file` only after validation, audit)
- `GET /hosting/{id}/files/download?path=` (ownership, `is_dir` blocked, flag `.suspended` blocked)
- `POST /hosting/{id}/files/delete` (no root, `rrmdir` child-first, audit)
- `POST /hosting/{id}/files/rename` (no overwrite, audit)
- `GET /hosting/{id}/files/create` + `POST /hosting/{id}/files/create` (text allow-list 512KB, `isSafeFilename`)
- `GET /hosting/{id}/files/edit?path=` + `POST /hosting/{id}/files/edit` (512KB, atomic `tmp+rename`, escaped textarea, quota delta)

## Databases
`GET /hosting/{id}/databases` (limit `count/limit`, `fh_{id}_{name}` input, create) → `POST /hosting/{id}/databases` (validate `^[a-z][a-z0-9_]{2,29}$`, reserved `mysql`, quota `plan.databaseLimit` 2/5/20, `LocalMock` mock, audit) → `GET /hosting/{id}/databases/{dbId}` (users, `fh_{id}_u_{user}` input) → `POST /hosting/{id}/databases/{dbId}/users` (password 16 chars shown once, encrypted `APP_KEY` `sodium`/`AES-GCM`, never logged) → `POST /hosting/{id}/databases/users/delete` (ownership, audit) → `POST /hosting/{id}/databases/delete` (cascade users, audit).

## DNS/SSL
- `GET /hosting/{id}/dns` (records, subdomains DNS status) → `POST /hosting/{id}/dns` (hostname `sub.freehost.example` must be subdomain of `APP_DOMAIN`, strict 253/label 63, duplicate `hostname` UNIQUE, takeover prevention, `A` 127.0.0.1 mock, audit) → `POST /hosting/{id}/dns/delete`
- `GET /hosting/{id}/ssl` (certs, subdomains SSL status `pending/issuing/active`) → `POST /hosting/{id}/ssl/request` (must own hostname via subdomain, `LocalMockCertificateProvider` 90d mock, `failed` if hostname contains `fail`) → `POST /hosting/{id}/ssl/renew`/`revoke` (audit).

## Usage/Backups
- `GET /hosting/{id}/usage` (storage `used/limit` via `RecursiveDirectoryIterator`, bandwidth mock, database/domain counts, warnings `>90%`, history `usage_records`, `POST /hosting/{id}/usage/collect` CSRF)
- `GET /hosting/{id}/backups` (list `pending/running/completed/failed/expired`, `POST /hosting/{id}/backups` type `full` retention 7d, max 2 pending, `LocalMockBackupProvider` size 100KB-5MB mock path, audit) → `POST /hosting/{id}/backups/delete` (not `running`, metadata only).

## Billing
`GET /billing` (subscriptions `trial/active/past_due/cancelled/expired`, invoices `pending/successful/failed/cancelled/refunded` `formattedAmount`, plans `FREE/BASIC/PREMIUM` with `price_cents`/`currency`) → `POST /billing/subscribe` (plan + hosting, mock gateway `mock_` + `https://pay.mock/`, pending invoice) → `POST /billing/cancel` (ownership or admin).

## Security
- All state-changing `POST` require `csrf_field()`, ownership `hosting_account.user_id===userId` or 403, `e()` escaped, no `.env` via `public/.htaccess`, no customer PHP execution via control panel (`storage/.htaccess php_flag engine off`).

## Support
- Logout `POST /logout` (CSRF, `regenerate_id`).
