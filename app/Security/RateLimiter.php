<?php

declare(strict_types=1);

namespace App\Security;

use App\Helpers\Database;

/**
 * Simple DB-backed rate limiter with in-memory fallback.
 */
final class RateLimiter
{
    /**
     * Check if action is allowed. Increments counter atomically.
     * @param string $key  e.g. "login:127.0.0.1" or "login:user@example.com"
     * @param int $maxAttempts
     * @param int $windowSeconds
     * @return bool true if allowed
     */
    public static function hit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        return self::attempt($key, $maxAttempts, $windowSeconds) === true;
    }

    public static function attempt(string $key, int $max, int $window): bool
    {
        $db = self::db();
        if ($db === null) {
            // Fallback: session-based (per-process)
            return self::fallbackAttempt($key, $max, $window);
        }
        $now = time();
        $windowStart = $now - $window;

        try {
            // Ensure table exists (migration creates it, but fallback)
            $db->query(
                "CREATE TABLE IF NOT EXISTS rate_limits (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    rate_key VARCHAR(190) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_rate_key_created (rate_key, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            // Count hits in window
            $count = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND created_at >= FROM_UNIXTIME(?)",
                [$key, $windowStart]
            );
            if ($count >= $max) {
                return false;
            }
            $db->execute("INSERT INTO rate_limits (rate_key) VALUES (?)", [$key]);
            // Cleanup old entries probabilistically (10%)
            if (random_int(1, 10) === 1) {
                $db->execute("DELETE FROM rate_limits WHERE created_at < FROM_UNIXTIME(?)", [$windowStart - 3600]);
            }
            return true;
        } catch (\Throwable $e) {
            error_log('[RateLimiter] DB error: ' . $e->getMessage());
            return self::fallbackAttempt($key, $max, $window);
        }
    }

    public static function remaining(string $key, int $max, int $window): int
    {
        $db = self::db();
        if ($db === null) {
            return $max;
        }
        $windowStart = time() - $window;
        $count = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND created_at >= FROM_UNIXTIME(?)",
            [$key, $windowStart]
        );
        return max(0, $max - $count);
    }

    public static function clear(string $key): void
    {
        $db = self::db();
        if ($db !== null) {
            try {
                $db->execute("DELETE FROM rate_limits WHERE rate_key = ?", [$key]);
            } catch (\Throwable) {
            }
        }
        // Also clear fallback
        if (isset($_SESSION['_rate_fallback'][$key])) {
            unset($_SESSION['_rate_fallback'][$key]);
        }
    }

    private static function db(): ?Database
    {
        try {
            return Database::getInstance();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function fallbackAttempt(string $key, int $max, int $window): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true; // allow if no session
        }
        $now = time();
        $_SESSION['_rate_fallback'] ??= [];
        $entry = $_SESSION['_rate_fallback'][$key] ?? ['count' => 0, 'start' => $now];
        if ($now - $entry['start'] > $window) {
            $entry = ['count' => 0, 'start' => $now];
        }
        if ($entry['count'] >= $max) {
            $_SESSION['_rate_fallback'][$key] = $entry;
            return false;
        }
        $entry['count']++;
        $_SESSION['_rate_fallback'][$key] = $entry;
        return true;
    }
}
