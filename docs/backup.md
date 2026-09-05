# Backup Procedure — FreeHost Manager

## Scope
- **Application DB** (`freehost_manager`) — full and incremental via `BackupService` + `LocalMockBackupProvider` (Phase 7 mock, metadata only `pending→running→completed/failed→expired`, `storage/backups/hosting_{id}/...` mock path, no real `tar`).
- **Customer files** (`storage/hosting/{username}_{rand}/public_html`) — future `restic`/`borg` via `BackupProviderInterface`.
- **Retention:** `retention_days` 1-365 (default 7), `expires_at = created_at + retention`, `expireOld()` marks `expired` where `expires_at < NOW()` (no destructive `DELETE` without explicit `POST /hosting/{id}/backups/delete`).

## Procedure (Mock, Phase 7)
1. Customer `POST /hosting/{id}/backups` (type `full`/`incremental`, retention 7d, CSRF, ownership, `pending→running→completed` via `LocalMockBackupProvider` size 100KB-5MB).
2. List `GET /hosting/{id}/backups` (shows `size_bytes`, `expires_at`, `status`).
3. Delete metadata `POST /hosting/{id}/backups/delete` (blocks `running`, audit, provider `deleteBackup` mock).
4. Admin `GET /admin/backups` (search, all statuses), `POST /admin/backups/expire` (marks expired, not deletes).

## Production (Future)
- Real backup via `BackupProviderInterface` (e.g., `restic` to S3), `file_path` is S3 key, `size_bytes` real, `completed_at`, `last_error`.
- Cron `0 2 * * * php /var/www/freehost-manager/scripts/backup.php --all` + `expire` daily.
- No destructive auto-deletion without retention rules + admin confirmation; `expired` retained per policy, manual purge via `DELETE` only.

## Restore
- Mock: no restore; production will use `restic restore` to `storage/hosting` staging, verify via `audit_logs`, no secrets in logs.

## Security
- Customer only sees own `hosting_account_id` (IDOR `userId` check), admin `Rbac(admin)` for `GET /admin/backups`, `file_path` metadata only (no filesystem access via web), no `exec`, no secrets in audit.

## Operational
- Monitor `GET /admin/monitoring` shows `backups_failed` count; alert if `failed >3` → `warning`.
