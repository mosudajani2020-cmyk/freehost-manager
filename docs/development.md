# Development — FreeHost Manager (Phase 1)

## Stack
- PHP 8.3+, MySQL 8.0, Apache 2.4, Bootstrap 5 CDN, vanilla JS.
- Composer: `vlucas/phpdotenv` (env), `phpunit/phpunit` (dev).

## Structure
See `docs/architecture.md`. Controllers thin, Services hold rules, Repositories do PDO.

## Conventions
- Strict types `declare(strict_types=1)` in all PHP files.
- PSR-4 `App\` → `app/`, `Tests\` → `tests/`.
- Use `e()` for output, prepared statements, `Csrf::token()` in forms.
- No business logic in routes or views.
- No secrets in git — `.env` ignored, `APP_KEY` generated.

## Running Locally (with PHP 8.3 parallel)
```powershell
C:\php83\php.exe -S localhost:8000 -t public
# or Apache vhost DocumentRoot public/
C:\php83\php.exe scripts/migrate.php
C:\php83\php.exe scripts/create-admin.php
C:\php83\php.exe vendor/bin/phpunit --testdox
```

## Git Workflow
Branch `main` → `origin/main`. Before commit:
```powershell
& "C:\Program Files\Git\cmd\git.exe" status
& "C:\Program Files\Git\cmd\git.exe" diff
# check no .env, no logs, no secrets
C:\php83\php.exe -l app/**/*.php
C:\php83\php.exe vendor/bin/phpunit
```

## Testing
- `phpunit.xml` defines Unit/Integration/Security suites.
- `tests/bootstrap.php` requires 8.3.
- Run: `C:\php83\php.exe vendor/bin/phpunit --testdox` (36 tests Phase 1).
- Security tests include brute force, traversal, upload bypass, IDOR mocks.

## Adding Features (Future Phases)
1. Create migration in `database/migrations/`.
2. Add Service + Repository.
3. Add Controller + routes in `routes/web.php` with middleware.
4. Add View with `csrf_field()` and `e()`.
5. Add tests, run phpunit, manual browser test (desktop/mobile).
6. Update docs.

## Phase Boundaries
Do not implement Phase 2+ (hosting, files, DB, domains) until Phase 1 review passes. Each phase must meet Definition of Done (20 checks).
