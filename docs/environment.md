# Environment Configuration — FreeHost Manager

## Required Variables (.env)
Copy `.env.example` → `.env` and set:

```
APP_NAME="FreeHost Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.freehost.example
APP_DOMAIN=freehost.example
APP_KEY=base64:<32_bytes> # from `php scripts/generate-key.php`

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freehost_manager
DB_USERNAME=freehost_app
DB_PASSWORD=<strong>

SESSION_DRIVER=file
SESSION_LIFETIME=30
SESSION_SECURE=true
SESSION_SAMESITE=Strict
SESSION_COOKIE_NAME=FHSESSID

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@freehost.example
MAIL_PASSWORD=<secret>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@freehost.example
MAIL_FROM_NAME="FreeHost Manager"

UPLOAD_MAX_MB=20
PASSWORD_MIN_LENGTH=8
REGISTRATION_ENABLED=true
EMAIL_VERIFICATION_REQUIRED=true
DEFAULT_PLAN_SLUG=free
MAINTENANCE_MODE=false
WEBHOOK_SECRET=<random_32>
```

## Production Requirements
- `APP_ENV=production`, `APP_DEBUG=false` (never display stack traces)
- `SESSION_SECURE=true` (requires HTTPS), `SESSION_SAMESITE=Strict` for production, `HttpOnly` always
- `APP_KEY` base64 32 bytes, never commit, rotate via `scripts/generate-key.php`
- `DB_PASSWORD` strong, least-privilege `freehost_app` only
- `MAIL_*` via vault, not Git
- `WEBHOOK_SECRET` for payment webhooks HMAC

## Validation
`config/bootstrap.php` requires `DB_HOST,DB_DATABASE,DB_USERNAME,DB_PASSWORD,APP_KEY,APP_URL` via `vlucas/phpdotenv` and fails safe (500, no secrets) if missing.

## File Permissions (Linux)
```
chown -R www-data:www-data storage/ public/
chmod 750 storage/logs storage/sessions storage/cache
chmod 640 .env
chmod 750 storage/hosting # jailed per account, 755 for public_html
```

## HTTPS
- Terminate TLS at Nginx/Apache or CDN, set `X-Forwarded-Proto` and `$_SERVER['HTTPS']=on` for `public/index.php` HSTS.
- `.htaccess` and `public/index.php` set `Strict-Transport-Security: max-age=63072000` when HTTPS.

## DNS/SSL
- `APP_DOMAIN` is source of truth for subdomains (`sub.freehost.example`); `system_settings.main_domain` overrides.
- No real DNS/ACME in Phase 7 mock; production uses `DnsProviderInterface` (Cloudflare/Route53) and `CertificateProviderInterface` (ACME) with `api_key_hash` preview only.

## Cron / Queue (Future)
- No cron required for Phase 7 mock; production will need:
  - `* * * * * php /var/www/freehost-manager/scripts/expire-backups.php`
  - `0 2 * * * php /var/www/freehost-manager/scripts/renew-certs.php`
  - Queue worker `php /var/www/freehost-manager/scripts/worker.php` polling `provisioning_jobs` where `status='queued'`
