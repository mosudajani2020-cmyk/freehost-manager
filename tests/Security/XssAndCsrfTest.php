<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Csrf;
use PHPUnit\Framework\TestCase;

final class XssAndCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
    }

    public function testEscapePreventsXss(): void
    {
        $malicious = '<script>alert(1)</script>';
        $escaped = e($malicious);
        $this->assertNotEquals($malicious, $escaped);
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    public function testSqlInjectionPayloadEscaped(): void
    {
        $payload = "' OR 1=1 --";
        $escaped = e($payload);
        $this->assertEquals('&#039; OR 1=1 --', $escaped); // htmlspecialchars with ENT_QUOTES|ENT_HTML5 produces &#039;
        // More important: prepared statements prevent it — not output encoding; this just verifies e() works
        $this->assertStringContainsString('&#039;', $escaped);
    }

    public function testCsrfTokenGeneration(): void
    {
        $token = Csrf::generate();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes hex
        $this->assertTrue(Csrf::validate($token));
    }

    public function testCsrfInvalidTokenRejected(): void
    {
        Csrf::generate();
        $this->assertFalse(Csrf::validate('invalid'));
        $this->assertFalse(Csrf::validate(''));
        $this->assertFalse(Csrf::validate(null));
    }

    public function testCsrfTokenMismatch(): void
    {
        $token = Csrf::generate();
        $this->assertFalse(Csrf::validate($token . 'tampered'));
    }

    public function testOutputEncodingForAllContexts(): void
    {
        $cases = [
            '<img src=x onerror=alert(1)>',
            '"><svg onload=alert(1)>',
            "'; DROP TABLE users; --",
            '../../../etc/passwd',
        ];
        foreach ($cases as $input) {
            $escaped = e($input);
            $this->assertStringNotContainsString('<', $escaped, "Failed for: $input");
            // Ensure e() doesn't just return input unchanged
            if (str_contains($input, '<') || str_contains($input, '"') || str_contains($input, "'")) {
                $this->assertNotEquals($input, $escaped);
            }
        }
    }
}
