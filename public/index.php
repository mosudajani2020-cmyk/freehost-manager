<?php

declare(strict_types=1);

// PHP 8.3+ version guard — file is parseable on 7.x (no 8.3 syntax here)
if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><title>Configuration Error</title></head><body>';
    echo '<h1>Server Configuration Error</h1>';
    echo '<p>This application requires PHP 8.3 or higher. Current: ' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>See docs/installation.md. Environment prerequisite not met.</p>';
    echo '</body></html>';
    exit(1);
}

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Helpers\Router;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RbacMiddleware;

// Security headers — always, even on error pages
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (!headers_sent()) {
    // CSP minimal — allow Bootstrap CDN, self scripts/styles
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self'");
}

// Session bootstrap — secure defaults
$sessionConfig = require CONFIG_PATH . '/session.php';
$secure = $sessionConfig['secure'] ?? false;
// Detect HTTPS if behind reverse proxy considerations minimal for local
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $secure = true;
}
session_name($sessionConfig['cookie_name'] ?? 'FHSESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $sessionConfig['path'] ?? '/',
    'domain' => $sessionConfig['domain'] ?? '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => $sessionConfig['samesite'] ?? 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    // use_strict_mode should be php.ini, but enforce via ini_set if possible
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

// Inactivity timeout (30m default)
$lifetime = ($sessionConfig['lifetime'] ?? 30) * 60;
if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity'] > $lifetime)) {
    session_unset();
    session_destroy();
    session_start();
    // Regenerate to avoid fixation after timeout
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

// Flash helper — make available
if (!isset($_SESSION['_flash'])) {
    $_SESSION['_flash'] = [];
}

// Load routes
$router = new Router();

// Central route definitions
require BASE_PATH . '/routes/web.php';

// Dispatch
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    error_log('[FreeHost Manager] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $debug = ($configApp['debug'] ?? false) === true;
    http_response_code(500);
    if ($debug && php_sapi_name() !== 'cli') {
        // Show minimal debug — never secrets
        echo '<h1>Internal Server Error</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        // Generic page
        if (file_exists(APP_PATH . '/Views/errors/500.php')) {
            require APP_PATH . '/Views/errors/500.php';
        } else {
            echo '<h1>Internal Server Error</h1><p>Something went wrong. Check logs.</p>';
        }
    }
}
