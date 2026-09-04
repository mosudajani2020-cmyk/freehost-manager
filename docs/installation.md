# Installation — FreeHost Manager (Phase 1)

## Requirements
- **PHP 8.3+** (mandatory — fails safe with 500 if <8.3). Current AppServ ships PHP 7.3 (EOL); install PHP 8.3 parallel for CLI/migrations (e.g. `C:\php83\php.exe`).
- MySQL 8.0+ / MariaDB 10.4+
- Apache 2.4 with `mod_rewrite` enabled, `AllowOverride All`
- Composer 2.x
- Git

## Environment Blocker
AppServ default PHP is 7.3.10. Code targets 8.3 and will **refuse to run** on 7.3:
- `public/index.php` and `scripts/migrate.php` check `version_compare(PHP_VERSION,'8.3.0','<')` and exit 500.
- Tests require 8.3 (`tests/bootstrap.php` exits if <8.3).
- `composer.json` requires `php ^8.3`.

**Do not downgrade code to 7.3.** Use a separate PHP 8.3 binary for CLI:

```powershell
C:\php83\php.exe -v
C:\php83\php.exe C:\Users\IMYAA\AppData\Local\Temp\composer.phar install --ignore-platform-reqs
```

For Apache, create a VirtualHost with PHP 8.3 module (or run php -S for dev).

## Steps (Windows/AppServ)
1. Clone:
   ```powershell
   git clone https://github.com/mosudajani2020-cmyk/freehost-manager.git
   cd freehost-manager
   ```
2. Composer:
   ```powershell
   C:\php83\php.exe composer.phar install
   ```
   (On PHP 7.3 host, `composer install --ignore-platform-reqs` still installs, but runtime needs 8.3.)
3. Env:
   ```powershell
   copy .env.example .env
   # edit .env — set DB_PASSWORD, APP_KEY, APP_URL
   C:\php83\php.exe scripts/generate-key.php
   # paste into APP_KEY=base64:...
   ```
   Required vars: `APP_NAME,APP_ENV,APP_DEBUG,APP_URL,APP_DOMAIN,APP_KEY, DB_HOST,DB_PORT,DB_DATABASE,DB_USERNAME,DB_PASSWORD`.
4. Database:
   ```sql
   CREATE DATABASE freehost_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'freehost_app'@'localhost' IDENTIFIED BY 'your_secure_password_here';
   GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES ON freehost_manager.* TO 'freehost_app'@'localhost';
   FLUSH PRIVILEGES;
   ```
   The app never uses root — least privilege `freehost_app`.
5. Migrations:
   ```powershell
   C:\php83\php.exe scripts/migrate.php
   # should report [SKIP]/[RUN] and batch
   ```
6. Admin:
   ```powershell
   C:\php83\php.exe scripts/create-admin.php
   # interactive — no default password
   ```
7. Apache DocumentRoot:
   - Must be `C:/AppServ/www/freehost-manager/public` (not project root). Else `.env` is web-exposed.
   - Ensure `public/.htaccess` is effective (`AllowOverride All`).
   - Storage dirs have `.htaccess: Require all denied`.
8. Browse:
   ```
   http://localhost/freehost-manager/public/login
   # or if vhost: http://localhost/login
   ```
   Login with admin created above. Customer dashboard at `/dashboard`, admin at `/admin/dashboard`.

## Verifying
- `C:\php83\php.exe vendor/bin/phpunit --testdox` → 36 tests pass.
- `C:\php83\php.exe -l app/**/*.php` no syntax errors.
- `storage/logs/app.log` and `storage/logs/mail.log` writable.

## Troubleshooting
- **500 "PHP 8.3 required"** → upgrade CLI PHP, update `httpd.conf` `PHPIniDir` / `LoadModule`.
- **DB connection failed** → check `.env` DB_PASSWORD, `SHOW GRANTS FOR 'freehost_app'@'localhost'`.
- **.env not found** → copy `.env.example`.
- **CSRF 419** → session not started, check `storage/sessions` perms.
