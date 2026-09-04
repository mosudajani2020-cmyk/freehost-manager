<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Structured application logger — never logs secrets.
 */
final class Logger
{
    private const SECRET_KEYS = ['password', 'password_hash', 'token', 'token_hash', 'app_key', 'db_password', 'secret'];

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function auth(string $message, array $context = []): void
    {
        self::write('AUTH', $message, $context, 'auth.log');
    }

    private static function write(string $level, string $message, array $context, string $file = 'app.log'): void
    {
        $context = self::scrub($context);
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $logFile = $dir . DIRECTORY_SEPARATOR . $file;
        $ts = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf("[%s] %s [%s] %s%s%s", $ts, $level, $ip, $message, $ctx, PHP_EOL);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private static function scrub(array $context): array
    {
        foreach (self::SECRET_KEYS as $secret) {
            foreach ($context as $k => $v) {
                if (stripos((string) $k, $secret) !== false) {
                    $context[$k] = '[REDACTED]';
                }
            }
        }
        return $context;
    }
}
