<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Security\Csrf;

final class CsrfMiddleware
{
    public function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (!Csrf::validateRequest()) {
            http_response_code(419);
            // Log
            error_log('[CSRF] Invalid token from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ' for ' . ($_SERVER['REQUEST_URI'] ?? ''));
            if (file_exists(APP_PATH . '/Views/errors/419.php')) {
                require APP_PATH . '/Views/errors/419.php';
            } else {
                echo '<h1>419 Page Expired</h1><p>CSRF token mismatch. Please refresh and try again.</p>';
            }
            exit;
        }
    }
}
