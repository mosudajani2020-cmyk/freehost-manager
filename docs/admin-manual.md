# Administrator Manual — FreeHost Manager

## Login
`GET /login` → `POST /login` (RateLimiter) → `GET /admin/dashboard` (requires `admin` role). Default admin via `php scripts/create-admin.php` (interactive, Argon2id, no default password).

## Dashboards
- `GET /admin/dashboard` — stats `total_users/active/suspended`, `hosting_total/active/suspended`, `databases`, recent users/audit.
- Sidebar: `Hosting Accounts` (`/admin/hosting`), `Plans` (`/admin/plans`), `Nodes` (`/admin/nodes`), `Provisioning` (`/admin/provisioning`), `Databases` (`/admin/databases`), `DNS` (`/admin/dns`), `SSL` (`/admin/ssl`), `Monitoring` (`/admin/monitoring`), `Backups` (`/admin/backups`), `Users` (`/admin/users`), `Audit` (`/admin/audit`), `Settings` (`/admin/settings`).

## Hosting Management
- `GET /admin/hosting` (search `username/email`, 20pp) → `GET /admin/hosting/{id}` (owner, plan, quota, subdomains) → `POST /admin/hosting/{id}/suspend|activate|terminate` (audit, provisioning job).

## Plans
- `GET /admin/plans` → `GET /admin/plans/create` → `POST /admin/plans` (validate `storage 0-1M`, `price_cents 0-10M`, `is_default` handling) → `GET /admin/plans/{id}/edit` → `POST /admin/plans/{id}`.

## Nodes/Provisioning
- `GET /admin/nodes` → `POST /admin/nodes` (generates `fhm_*` API key hash, preview once) → `GET /admin/nodes/{id}` (jobs).
- `GET /admin/provisioning` (filter `failed`), `GET /admin/provisioning/{id}` → `POST /admin/provisioning/{id}/retry` (if `failed` & `attempts<3`) or `fail`.

## Databases/DNS/SSL
- `GET /admin/databases` (search), `GET /admin/databases/{id}` (users)
- `GET /admin/dns` → `POST /admin/dns/{id}/status` (`pending/active/failed/suspended/removed`)
- `GET /admin/ssl` → `POST /admin/ssl/{id}/status` (`pending/issuing/active/renewing/expired/failed/revoked`)

## Usage/Backups/Monitoring
- `GET /admin/monitoring` (system health `healthy/warning/critical`, provisioning health, stats, failed jobs, audit) → `GET /admin/monitoring/usage` → `GET /admin/backups` (search) → `POST /admin/backups/expire`.
- `GET /admin/users` → `GET /admin/users/{id}` → `POST /admin/users/{id}/status` (active/suspended/banned, audit)
- `GET /admin/audit` (search `action`, 30pp)
- `GET /admin/settings` (non-secret `system_settings` only) → `POST /admin/settings` (whitelist `is_secret=0`)

## Billing
- `GET /admin/billing/subscriptions` → `POST /admin/billing/subscriptions/{id}/status` (trial/active/past_due/cancelled/expired)
- `GET /admin/billing/invoices` → `POST /admin/billing/invoices/{id}/status` (pending/successful/failed/cancelled/refunded, `refunded` via `LocalMockPaymentGateway`, duplicate `successful` blocked, webhook HMAC)

## Security
- All `POST` require `csrf_field()`, all `GET /admin/*` require `Rbac(admin)`, audit `admin.*`, no secrets in logs (`scrub`), no `exec`, `PathGuard` for files.

## Platform Statistics
- `GET /admin/monitoring` shows `total_users`, `hosting_total`, `databases`, `backups`, `audit_24h`.

