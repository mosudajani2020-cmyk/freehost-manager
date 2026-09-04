<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\PathGuard;
use PHPUnit\Framework\TestCase;

final class PathGuardTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/fh_test_base_' . bin2hex(random_bytes(4));
        mkdir($this->base, 0755, true);
        mkdir($this->base . '/subdir', 0755, true);
    }

    protected function tearDown(): void
    {
        // Cleanup
        @rmdir($this->base . '/subdir');
        @rmdir($this->base);
    }

    public function testValidPathAllowed(): void
    {
        $resolved = PathGuard::resolve($this->base, 'subdir/file.txt');
        $this->assertStringContainsString('subdir/file.txt', $resolved);
    }

    public function testTraversalBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, '../etc/passwd');
    }

    public function testEncodedTraversalBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, '%2e%2e%2fetc/passwd');
    }

    public function testDoubleEncodedTraversalBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, '%252e%252e%252fetc/passwd');
    }

    public function testAbsolutePathBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, '/etc/passwd');
    }

    public function testWindowsDriveBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, 'C:\\Windows\\System32');
    }

    public function testNullByteBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, "file.txt\0.jpg");
    }

    public function testBackslashTraversalBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, '..\\..\\windows');
    }

    public function testSafeFilename(): void
    {
        $this->assertTrue(PathGuard::isSafeFilename('my_file-1.0.txt'));
        $this->assertFalse(PathGuard::isSafeFilename('../evil.txt'));
        $this->assertFalse(PathGuard::isSafeFilename('CON'));
        $this->assertFalse(PathGuard::isSafeFilename('.hidden'));
        $this->assertFalse(PathGuard::isSafeFilename('file with spaces.txt')); // spaces not allowed
    }

    public function testSanitizeFilename(): void
    {
        $sanitized = PathGuard::sanitizeFilename('my file.php');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9._\-]+$/', $sanitized);
        $this->assertStringNotContainsString(' ', $sanitized);
    }

    public function testTraversalWithDotSegments(): void
    {
        $this->expectException(\RuntimeException::class);
        PathGuard::resolve($this->base, 'a/../../b');
    }
}
