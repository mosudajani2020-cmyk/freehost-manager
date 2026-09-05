# Monitoring Procedure — FreeHost Manager

## Health Endpoints (Admin Only, Rbac)
- `GET /admin/monitoring` — `MonitoringService` + `LocalMockMonitoringProvider`:
  - System health: `healthy` if `failed_provisioning_jobs==0 && failed_backups==0`, `warning` if `>0`, `critical` if `>5`
  - Provisioning health: `queued`/`failed` counts
  - Operational stats: `total_users`, `hosting_total/active/suspended`, `databases`, `backups`, `audit_24h`
  - Recent `audit_logs` (10) and `failed provisioning_jobs` (10)
- `GET /admin/monitoring/usage` — hosting storage limits
- `GET /admin/provisioning` — filter by `failed`/`queued`, retry

## Metrics
- **System:** `SELECT COUNT(*) FROM provisioning_jobs WHERE status='failed'` → `critical` if >5
- **Backups:** `SELECT COUNT(*) FROM backups WHERE status='failed'` → `warning` if >3
- **Usage:** `UsageService::collectAndRecord` → `storage/bandwidth/database/domain` vs `plan` limits, `>90%` warning, `usage_records` snapshotted via `POST /hosting/{id}/usage/collect`
- **Audit:** `audit_logs` `provisioning.*`, `file.*`, `database.*`, `dns.*`, `ssl.*`, `backup.*` (no secrets, scrubbed)

## Alerting (Future)
- Prometheus `freehost_manager_failed_jobs`, `freehost_manager_storage_percent`, `freehost_manager_audit_rate`
- Grafana dashboards + Alertmanager `critical` → Slack/PagerDuty
- Log `storage/logs/app.log` never contains `password`/`fhm_` (scrubbed via `Logger::scrub`)

## Workers/Queues
- Phase 7 mock: `provisioning_jobs` `dispatch()` → `processJob()` synchronous (future async worker polling `status='queued'` with `SELECT ... FOR UPDATE SKIP LOCKED`, HMAC `X-Signature`, idempotency, `attempts/max_attempts 3`, `last_error`, `completed_at`).
- Cron for `usage`/`backup`/`cert` renew (see `docs/environment.md`).

## Verification
- `php scripts/migrate.php` all `SKIP`
- `php vendor/bin/phpunit --testdox` 179 tests OK
- Manual: `GET /admin/monitoring` shows `healthy` → create failed job → shows `warning` → retry → `active`.
