<?php

declare(strict_types=1);

// PHP version guard — fail safe with clear message
if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    http_response_code(500);
    // Avoid leaking paths; generic but useful
    echo '<h1>Server Configuration Error</h1>';
    echo '<p>This application requires PHP 8.3 or higher. Current version: ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>Please upgrade PHP. See docs/installation.md for requirements.</p>';
    error_log('[FreeHost Manager] PHP version check failed: ' . PHP_VERSION . ' < 8.3.0');
    exit(1);
}

// Composer autoload
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo '<h1>Application Not Installed</h1><p>Run <code>composer install</code> first.</p>';
    exit(1);
}
require $autoload;

// Load environment variables
$envPath = dirname(__DIR__);
try {
    if (file_exists($envPath . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable($envPath);
        $dotenv->load();
        // Validate required vars
        $dotenv->required(['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'APP_KEY', 'APP_URL']);
    } else {
        // In CLI (migrations) we still try to load, but log warning
        if (php_sapi_name() !== 'cli') {
            error_log('[FreeHost Manager] .env not found at ' . $envPath);
        }
        // Try to load .env.example defaults for validation message
        if (file_exists($envPath . '/.env.example')) {
            $dotenv = Dotenv\Dotenv::createImmutable($envPath, '.env.example');
            $dotenv->load();
        }
    }
} catch (Throwable $e) {
    // Fail safe — do not expose secrets
    error_log('[FreeHost Manager] Env load error: ' . $e->getMessage());
    if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
        throw $e;
    }
    http_response_code(500);
    echo '<h1>Configuration Error</h1><p>Check application logs.</p>';
    exit(1);
}

// Load configs
$configApp = require __DIR__ . '/app.php';
$configDb = require __DIR__ . '/database.php';
$configSession = require __DIR__ . '/session.php';

// Error handling
if (($configApp['debug'] ?? false) === true) {
    ini_set('display_errors', '0'); // never display to user, log instead
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Define base constants
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');
