<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Synchronizer token CSRF protection.
 */
final class Csrf
{
    private const TOKEN_KEY = '_csrf_token';
    private const TOKEN_TIME_KEY = '_csrf_time';

    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Should not happen — bootstrap starts session
            return '';
        }
        if (empty($_SESSION[self::TOKEN_KEY])) {
            return self::generate();
        }
        // Rotate every 2 hours optionally, but keep token stable within session
        return $_SESSION[self::TOKEN_KEY];
    }

    public static function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY] = $token;
        $_SESSION[self::TOKEN_TIME_KEY] = time();
        return $token;
    }

    public static function validate(?string $token): bool
    {
        if (empty($token) || empty($_SESSION[self::TOKEN_KEY])) {
            return false;
        }
        return hash_equals((string) $_SESSION[self::TOKEN_KEY], $token);
    }

    public static function validateRequest(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true; // not required for safe methods
        }
        $token = $_POST['_csrf'] ?? $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        // Also check header
        if ($token === null && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        return self::validate(is_string($token) ? $token : null);
    }
}
