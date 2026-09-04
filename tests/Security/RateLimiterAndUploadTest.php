<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\UploadGuard;
use PHPUnit\Framework\TestCase;

final class RateLimiterAndUploadTest extends TestCase
{
    public function testBlockedExtensions(): void
    {
        $this->assertFalse(UploadGuard::isExtensionAllowed('shell.php'));
        $this->assertFalse(UploadGuard::isExtensionAllowed('test.phtml'));
        $this->assertFalse(UploadGuard::isExtensionAllowed('backdoor.phar'));
        $this->assertFalse(UploadGuard::isExtensionAllowed('shell.php.jpg')); // double
        $this->assertTrue(UploadGuard::isExtensionAllowed('image.png', ['png','jpg','txt']));
        $this->assertFalse(UploadGuard::isExtensionAllowed('image.exe', ['png','jpg']));
    }

    public function testNoExtensionRejected(): void
    {
        $this->assertFalse(UploadGuard::isExtensionAllowed('noextension'));
    }

    public function testSizeCheck(): void
    {
        $this->assertTrue(UploadGuard::checkSize(1024, 2048));
        $this->assertFalse(UploadGuard::checkSize(3000, 2048));
        $this->assertFalse(UploadGuard::checkSize(0, 2048));
    }

    public function testMimeBlockingForPhpContent(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upload_test');
        file_put_contents($tmp, '<?php echo "evil"; ?>');
        // Should be blocked if extension is image but content is php
        $this->assertFalse(UploadGuard::validateMime($tmp, 'image.jpg'));
        // For txt with php content, current logic allows? (depends) — at least ensure php mime blocked
        $this->assertFalse(UploadGuard::validateMime($tmp, 'shell.png'));
        unlink($tmp);
    }

    public function testPathTraversalPayloads(): void
    {
        $payloads = [
            '../etc/passwd',
            '..\\..\\windows\\system32',
            '%2e%2e%2fetc%2fpasswd',
            '....//....//etc/passwd',
            '/absolute/path',
            'C:\\Windows\\System32\\cmd.exe',
            "file\0.txt",
        ];
        foreach ($payloads as $payload) {
            // These should be rejected by PathGuard — not UploadGuard directly, but ensure isExtensionAllowed doesn't bypass
            // At least check that payloads containing .. are not considered safe filenames
            $this->assertFalse(\App\Security\PathGuard::isSafeFilename($payload), "Payload should be unsafe: $payload");
        }
    }
}
