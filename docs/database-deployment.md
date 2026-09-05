# Database Deployment — FreeHost Manager

## Compatibility
- MySQL 8.0+ or MariaDB 10.4+ (tested 8.0.17)
- Charset `utf8mb4` collate `utf8mb4_unicode_ci`, `ENGINE=InnoDB`

## Procedure
1. Create DB and least-privilege user:
```sql
CREATE DATABASE freehost_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'freehost_app'@'localhost' IDENTIFIED BY '<strong>';
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES ON freehost_manager.* TO 'freehost_app'@'localhost';
FLUSH PRIVILEGES;
```
Never use `root` for app. Verify `SHOW GRANTS FOR 'freehost_app'@'localhost'`.

2. Configure `.env` `DB_*`.

3. Run migrations (idempotent, no DDL transactions):
```
php scripts/migrate.php
# [SKIP] 001..007 or [RUN] 007_create_billing.php
```
Creates 17 tables + seeds (roles, permissions, plans, local-mock-1 node, settings). Rerun is safe (`IF NOT EXISTS`).

4. Verify:
```
mysql -u freehost_app -p -e "USE freehost_manager; SHOW TABLES; SELECT * FROM migrations; SELECT * FROM hosting_plans;"
```

## Migrations (001-007)
- 001 users/roles/permissions/pivots
- 002 hosting_plans/accounts/domains/subdomains/customer_databases/database_users/usage_records + FREE/BASIC/PREMIUM
- 003 password_resets/email_verifications/notifications/audit_logs/system_settings/rate_limits
- 004 hosting_nodes/provisioning_jobs + local-mock-1
- 005 dns_records/ssl_certificates + subdomains dns_status/ssl_status
- 006 backups + usage_records recorded_date
- 007 hosting_plans backup_limit/email_limit/price_cents/currency + subscriptions/invoices

## Rollback
- Migrations are not automatically reversible; for rollback, restore from backup (see `docs/backup.md`) or manually `DROP TABLE` in reverse order. Never `DROP` in production without backup.
- For failed migration, `migrations` table tracks `batch`; not yet applied migrations can be re-run after fixing `my.ini` (e.g., `sync_relay_log_info` corruption) or duplicate FK names.
- Keep `mysqldump` before any migration:
```
mysqldump -u root -p freehost_manager > backup-$(date +%F).sql
```

## Permissions & Indexes
- Least privilege as above; no `SUPER`, no `FILE`.
- Indexes on `users.email/username`, `hosting_accounts(user_id)`, `provisioning_jobs(idempotency_key UNIQUE)`, `backups(expires_at)`, `audit_logs(created_at)`.
- FKs `CASCADE/RESTRICT` as per migrations.

## Production Checklist
- `APP_DEBUG=false`, `DB_PASSWORD` vault, `APP_KEY` base64, `php -l` clean, `composer audit` clean.
