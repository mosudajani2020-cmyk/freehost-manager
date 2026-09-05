# API / Integration Documentation — FreeHost Manager

## Overview
Phase 1-9 is **modular monolith, API-ready** — business logic in `Services`, not HTML. No public API yet (per spec §44), but `ProvisioningService`, `SubscriptionService`, `DnsService` etc. are callable for future REST.

## Provisioning API (Future, HMAC)
- **Base:** `https://provision.freehost.example/api`
- **Auth:** `X-Api-Key: fhm_* preview`, `X-Timestamp: unix`, `X-Nonce: 16 hex`, `X-Signature: HMAC-SHA256(method|path|bodyHash|timestamp|nonce, apiKeyPlain)` where `bodyHash=SHA256(body)`, `SIGN_TTL 300`, `nonce` stored in `rate_limits` (`nonce:{nonce}`) replay protection.
- **Endpoints (mock `LocalMockProvisioner` today, `LinuxProvisioner` future):**
  - `POST /provision/hosting` `{hosting_account_id, operation, payload, idempotency_key}` → `job_uuid` `queued`
  - `GET /provision/jobs/{uuid}` → `status` `pending/queued/provisioning/active/failed/retrying`
  - `POST /provision/jobs/{id}/retry` (if `failed` & `attempts<3`)
- **Idempotency:** `idempotency_key` UNIQUE (`hash(account|operation|payload) + random`), duplicate returns same job (tested).
- **Allow-list:** only `HostingProvisionerInterface` methods (`createHostingAccount` etc., no `exec`).

## DNS/SSL Providers (Interfaces)
- `DnsProviderInterface: createRecord(hostname,type,value,ttl), deleteRecord, getRecord, verifyPropagation` → `LocalMockDnsProvider` logs only (no Route53).
- `CertificateProviderInterface: requestCertificate(hostname) → {status, expires_at: +90d}, renew, revoke, getStatus` → `LocalMockCertificateProvider` (`fail` in hostname → `failed`).

## Payment Webhook (Mock)
- `POST /billing/webhook` (future) — `PaymentGatewayInterface::verifyWebhook(payload, signature, secret)` HMAC `hash_hmac('sha256', payload, secret)` `hash_equals`, idempotent `status===` check, `pending→successful` activates `subscription` `trial/past_due→active`, duplicate `successful` blocked.
- **Test:** `payload={"provider_ref":"mock_...","status":"successful"}`, `signature=hash_hmac`, `secret=$_ENV['WEBHOOK_SECRET']`.

## Usage/Backup/Monitoring (Future)
- `UsageCollectorInterface: collect(hostingAccountId) → {storage_bytes, bandwidth_bytes, database_count}`, `aggregate(period)` → `usage_records`.
- `BackupProviderInterface: createBackup(hostingAccountId,type,retentionDays) → {size_bytes, file_path}` (mock `storage/backups/...`), `expireOld()` marks `expired`.
- `MonitoringProviderInterface: getSystemHealth() → {status: healthy/warning/critical, checks: {failed_provisioning_jobs, failed_backups}}`.

## Security
- All provider calls via `AuditService` (no secrets, scrubbed `password/fhm_`), `prepared statements`, `e()` escaped, `Rbac(admin)` for `GET /admin/*`, `Access denied` for `hosting_account.user_id` mismatch, CSRF on all POST.

## Example (PHP)
```php
$service = new ProvisioningService($db, new HostingNodeRepository($db), new ProvisioningJobRepository($db), new LocalMockProvisioner(), $audit);
$job = $service->dispatch($hostingId, 'createHostingAccount', [], $userId, 'idem-'.bin2hex(random_bytes(8)), $nodeId);
// $job->status === 'active' (mock synchronous)
```
