<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Worker\WorkerLogger;

/**
 * P1 — WorkerLogger: structured, secret-safe logging for the worker.
 * Sensitive keys are redacted; every string is sanitized for embedded creds.
 */
final class WorkerLoggerTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/fh_worker_' . bin2hex(random_bytes(4)) . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    public function testSanitizeMessageRedactsCredentialPatterns(): void
    {
        $dirty = 'unsubscribe password=MySecret api_key=KEY12349 token=TOK bearer=BTX Bearer abc.def';
        $clean = WorkerLogger::sanitizeMessage($dirty);
        $this->assertStringNotContainsString('MySecret', $clean);
        $this->assertStringNotContainsString('KEY12349', $clean);
        $this->assertStringNotContainsString('TOK', $clean);
        $this->assertStringNotContainsString('abc.def', $clean);
        $this->assertStringContainsString('[REDACTED]', $clean);
    }

    public function testSensitiveContextKeysAreRedacted(): void
    {
        $logger = new WorkerLogger('w1', $this->tmpFile);
        $logger->info('job', ['password'=>'hunter2', 'api_key'=>'supersecret', 'token'=>'tok', 'normal'=>'hello']);
        $logger->close();

        $line = trim((string)file_get_contents($this->tmpFile));
        $this->assertStringContainsString('"password":"[REDACTED]"', $line);
        $this->assertStringContainsString('"api_key":"[REDACTED]"', $line);
        $this->assertStringContainsString('"token":"[REDACTED]"', $line);
        $this->assertStringContainsString('"normal":"hello"', $line);
        $this->assertStringNotContainsString('hunter2', $line);
        $this->assertStringNotContainsString('supersecret', $line);
    }

    public function testExceptionMessageIsSanitized(): void
    {
        $logger = new WorkerLogger('w1', $this->tmpFile);
        $logger->error('boom', ['error' => 'mysql password=root1234 host=localhost']);
        $logger->close();

        $line = trim((string)file_get_contents($this->tmpFile));
        $this->assertStringNotContainsString('root1234', $line);
        $this->assertStringContainsString('[REDACTED]', $line);
    }
}