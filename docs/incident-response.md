# Incident Response — FreeHost Manager

## Roles
- **On-call:** admin via `GET /admin/monitoring` + `audit_logs`
- **Escalation:** `admin` role only for `/admin/*` (Rbac), `audit.view` permission

## Detection
- `GET /admin/monitoring` shows `critical` (failed jobs >5, backups >3), `GET /admin/provisioning?status=failed`, `GET /admin/backups?status=failed`, `storage/logs/app.log` (no secrets), `audit_logs` `result='failure'`

## Containment
1. Set `MAINTENANCE_MODE=true` in `system_settings` or `.env` (future feature flag) to block customer writes.
2. Suspend affected `hosting_accounts` via `POST /admin/hosting/{id}/suspend` (audit).
3. For compromised node, set `hosting_nodes.status='maintenance'` via `POST /admin/nodes/{id}`.

## Eradication
- For file injection: `PathGuard` blocks traversal/symlink, `UploadGuard` blocks `php`; delete via `POST /hosting/{id}/files/delete` (audit, no root deletion).
- For DB takeover: strict hostname `APP_DOMAIN` check, `hostname` UNIQUE, `subdomains.hosting_account_id` ownership.
- For quota bypass: `HostingService`/`DnsService`/`DatabaseService` server-side checks, not UI trust.

## Recovery
- Restore from `mysqldump` `backup-$(date +%F).sql` (see `docs/database-deployment.md`), re-run `php scripts/migrate.php`.
- For backups, `expireOld()` only marks `expired`, not delete; restore via `restic` (future).

## Lessons Learned
- Log to `audit_logs` (no secrets via `scrub`), `storage/logs/app.log` (scrubbed), `provisioning_jobs.last_error` (no secrets).
- Review `docs/security.md` checklist after each incident.

## Communication
- Customer notifications via `notifications` table (future email), admin via `audit.view`.

## Hardening Post-Incident
- Run `composer audit`, `php -l`, `phpunit`, `grep -R exec`, `php scripts/migrate.php`, verify `X-Content-Type-Options`, `HSTS`, `CSP`, `.env` not in `public/`, `storage` 750, `APP_DEBUG=false`.
