<?php

declare(strict_types=1);

/**
 * Application configuration.
 * All values come from environment variables with safe defaults.
 * PHP 8.3+ required.
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'FreeHost Manager',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
    'domain' => $_ENV['APP_DOMAIN'] ?? 'freehost.example',
    'key' => $_ENV['APP_KEY'] ?? '',
    'upload_max_mb' => (int) ($_ENV['UPLOAD_MAX_MB'] ?? 20),
    'password_min_length' => (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
    'registration_enabled' => filter_var($_ENV['REGISTRATION_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'email_verification_required' => filter_var($_ENV['EMAIL_VERIFICATION_REQUIRED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'default_plan_slug' => $_ENV['DEFAULT_PLAN_SLUG'] ?? 'free',
    'maintenance_mode' => filter_var($_ENV['MAINTENANCE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
