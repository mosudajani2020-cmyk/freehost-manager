# Deployment — FreeHost Manager (Phase 6)

## Overview
Phase 5 implements a **production-ready provisioning abstraction** without turning Windows/AppServ into a production hosting server. The control panel remains isolated; provisioning is via a restricted service/worker.

## Architecture
```
FreeHost Manager (Control Panel)
        |
        |  HTTPS + HMAC (X-Timestamp/X-Nonce/X-Signature) + API Key
        v
Provisioning Service / API (restricted worker)
        |
        +--> Hosting Node 1 (Linux, Nginx/Apache, PHP-FPM pool per customer, chroot/jail, cgroup)
        +--> Hosting Node N
        +--> Database Service (MySQL, least-privilege per-customer DB/user)
        +--> DNS API (future)
        +--> TLS/ACME (future)
```

**Local development:** `LocalMockProvisioner` creates `storage/hosting/{username}_{rand}/public_html` + `.htaccess` + `.suspended/.terminated` flags + subdomain dirs + mock DB entries. No shell, no SSH, no root.

**Production:** `LinuxProvisioner` (future) will SSH/API to nodes as limited user, execute allow-list commands only, with job queue, idempotency, signing, and audit.

## Hosting Nodes
- Table `hosting_nodes(id, name, hostname, ip, region, status, max_accounts, current_accounts, api_url, api_key_hash, preview)`.
- Admin UI: `/admin/nodes`, `/admin/nodes/create` (generates `fhm_+32 hex` API key, stores SHA256 hash, shows once + preview), `/admin/nodes/{id}`.
- Selection: least-loaded `active` node for `createHostingAccount` via `ProvisioningService::selectNode`.

## Provisioning Jobs
- Table `provisioning_jobs(id, job_uuid CHAR36, hosting_account_id, node_id, operation, payload JSON, status, attempts, max_attempts 3, last_error, idempotency_key UNIQUE, requested_by, timestamps)`.
- States: `pending → queued → provisioning → active` or `failed`/`retrying` → `failed` (max 3) or `active` on retry, plus `suspended`/`terminated` for account lifecycle.
- Idempotency: `idempotency_key` UNIQUE prevents duplicate jobs (same account+op+payload+random suffix). `dispatch()` returns existing job.
- Retry: `canRetry()` only if `failed` and `attempts < max_attempts`; `retryJob()` sets `queued` and re-processes via `LocalMockProvisioner`.
- Failure: `last_error` stored, `audit_logs` `provisioning.failed/retrying`, `failed` terminal.
- Auto-processing: `dispatch()` queues then synchronously `processJob()` for local mock (future async worker will poll `queued`).

## Authentication & Signing (Control Panel ↔ Provisioning Service)
- **API Key:** `fhm_` + 32 hex (16 bytes), hash SHA256 stored, preview `fhm_...` shown once. Never commit, never log plaintext.
- **HMAC:** `X-Signature = HMAC-SHA256(method|path|bodyHash|timestamp|nonce, apiKeyPlain)` where `bodyHash = SHA256(body)`.
- **Headers:** `X-Api-Key: preview`, `X-Timestamp: unix`, `X-Nonce: random 16 hex`, `X-Signature: hmac`.
- **Replay protection:** `timestamp` 5-min TTL (`SIGN_TTL 300`), `nonce` stored in `rate_limits` (`nonce:{nonce}`) and rejected on reuse.
- **Implementation:** `ProvisioningService::generateApiKey()`, `signRequest()`, `verifyRequest()` (checks TTL, nonce uniqueness via `rate_limits`, `hash_equals`).

## Security
- Web request **never** `exec`/`shell_exec` — only `LocalMockProvisioner` file ops + mock DB logs.
- No SSH passwords/private keys in Git (preview only, hash stored).
- All payloads via prepared statements, validated identifiers, ownership checks before dispatch.
- Suspended/terminated accounts blocked before dispatch (checked in `ProvisioningService::dispatch`).
- Invalid node → 400, invalid job → 404, IDOR: customer cannot see another's jobs (checked via `hosting_accounts.user_id`).

## Local Development vs Production
| Aspect | Local Mock (Phase 5) | Production (Future) |
|--------|---------------------|---------------------|
| Filesystem | `storage/hosting` isolated via `PathGuard`, `php_flag engine off` | Linux chroot/jail, per-customer user, `open_basedir` |
| Process | Control panel PHP only | PHP-FPM pools per customer, cgroup limits |
| DB | Mock `customer_databases` table only, no `CREATE DATABASE` as root | Least-privilege `CREATE DATABASE`/`CREATE USER` via worker, `GRANT` limited |
| DNS/SSL | `system_settings.main_domain` only | Route53/Cloudflare API, Let's Encrypt ACME |
| Worker | Synchronous `processJob()` | Async queue (DB table `provisioning_jobs` polled, or Redis) + `completed_at` |

## Installation
1. Same as Phases 1-4 (see `docs/installation.md`).
2. Migrations include `004_create_provisioning.php` (auto-seeds `local-mock-1`).
3. No manual node creation needed for dev; for production, create via `/admin/nodes/create` and store API key securely (vault).

## Operational
- **Monitor:** `/admin/provisioning` (filter by status), `/admin/provisioning/{id}` (payload, attempts, last_error, retry/fail actions).
- **Audit:** all `provisioning.queued/active/failed/retrying` in `audit_logs`.
- **Logs:** `storage/logs/app.log` never contains passwords/keys (scrubbed).
- **Scaler:** add nodes via admin, `max_accounts`/`current_accounts` for scheduling.

## Limitations (Phase 5)
- No real SSH, no actual Linux nodes, no TLS/DNS, no background worker (synchronous). Future worker will be separate process, authenticated via same HMAC, with job `queued` → `provisioning` → `active`.

## Phase 6 — DNS/SSL (Local Mock)
- **DNS:** `dns_records` + `subdomains.dns_status` via `DnsService` + `LocalMockDnsProvider` (no Route53/Cloudflare). Hostname validation strict, duplicate `hostname` UNIQUE, takeover prevention (must be subdomain of `APP_DOMAIN`), lifecycle `pending→active→suspended→removed`.
- **SSL:** `ssl_certificates` + `subdomains.ssl_status` via `SslService` + `LocalMockCertificateProvider` (no Let's Encrypt). Request → `active` 90d, `renew`/`revoke` lifecycle `pending/issuing/active/renewing/expired/failed/revoked`, `expires_at` auto.
- **Production:** DNS API (Cloudflare/Route53) with same `DnsProviderInterface`; ACME (Let's Encrypt) via `CertificateProviderInterface` with `api_key_hash` + HMAC, async worker, auto-renew cron.

## Limitations (Phase 6)
- No real DNS API, no real ACME, no public DNS modification. All `LocalMock*` logs only. No private keys in Git.

