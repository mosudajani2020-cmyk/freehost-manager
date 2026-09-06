<?php

declare(strict_types=1);

namespace App\Services\Worker;

/**
 * WorkerLogger — structured, secret-safe logger for the provisioning worker (P1).
 *
 * Writes JSON lines (ts, level, event, worker, context) to a log file (default
 * storage/logs/worker.log) or to the PHP error log when no file is available.
 *
 * Safety properties:
 *   - sensitive context keys are redacted entirely;
 *   - every string value is run through sanitizeMessage() to scrub embedded
 *     credentials (password=, api_key=, Bearer tokens, ...);
 *   - exception messages are sanitized before they are written.
 */
final class WorkerLogger
{
    private $handle = null;

    public function __construct(
        private readonly string $workerId,
        ?string $file = null,
    ) {
        if ($file === null || $file === '') {
            $file = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs/worker.log' : '';
        }
        if ($file !== '') {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $this->handle = @fopen($file, 'a');
        }
    }

    public function info(string $event, array $context = []): void
    {
        $this->log('info', $event, $context);
    }

    public function warning(string $event, array $context = []): void
    {
        $this->log('warning', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->log('error', $event, $context);
    }

    public function debug(string $event, array $context = []): void
    {
        $this->log('debug', $event, $context);
    }

    public function log(string $level, string $event, array $context = []): void
    {
        $entry = json_encode(
            ['ts' => date('c'), 'level' => $level, 'event' => $event, 'worker' => $this->workerId] + $this->redactContext($context),
            JSON_UNESCAPED_SLASHES
        );
        $line = $entry === false ? '{"level":"error","event":"log_encode_failed"}' : $entry;
        if ($this->handle) {
            @fwrite($this->handle, $line . PHP_EOL);
        } else {
            error_log('[Worker] ' . $line);
        }
    }

    public function close(): void
    {
        if ($this->handle) {
            @fclose($this->handle);
            $this->handle = null;
        }
    }

    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd', 'api_key', 'apikey', 'access_key',
        'secret', 'token', 'signature', 'authorization', 'credential', 'key',
    ];

    /**
     * Scrub secret-bearing patterns from a message. Applied to every string that
     * leaves the worker, including exception messages, so credentials embedded in
     * unexpected error output are never persisted or logged.
     */
    public static function sanitizeMessage(string $message): string
    {
        $patterns = [
            '/(password|passwd|pwd)\s*[=:]\s*\S+/i' => '$1=[REDACTED]',
            '/(api[_-]?key|access[_-]?key|secret|token|signature|authorization|credential)\s*[=:]\s*\S+/i' => '$1=[REDACTED]',
            '/Bearer\s+[A-Za-z0-9._\-]+/i' => 'Bearer [REDACTED]',
        ];
        $sanitized = preg_replace(array_keys($patterns), array_values($patterns), $message);
        return $sanitized === null ? $message : $sanitized;
    }

    private function redactContext(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->redactContext($value);
                continue;
            }
            if (!is_string($value)) {
                $out[$key] = $value;
                continue;
            }
            if ($this->isSensitiveKey((string)$key)) {
                $out[$key] = '[REDACTED]';
                continue;
            }
            $out[$key] = self::sanitizeMessage($value);
        }
        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }
        return false;
    }
}