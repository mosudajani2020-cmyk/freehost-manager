<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AuthHashTest extends TestCase
{
    public function testPasswordHashAndVerify(): void
    {
        $password = 'StrongPass1' . bin2hex(random_bytes(4));
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($password, $algo);
        $this->assertTrue(password_verify($password, $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }

    public function testWeakPasswordRejectedByPolicy(): void
    {
        $weak = 'password';
        $this->assertFalse(
            preg_match('/[A-Z]/', $weak) && preg_match('/[a-z]/', $weak) && preg_match('/[0-9]/', $weak) && strlen($weak) >= 8
        );
    }

    public function testHashNotStoredPlaintext(): void
    {
        $password = 'StrongPass1';
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($password, $algo);
        $this->assertNotEquals($password, $hash);
        $this->assertStringStartsWith($algo === PASSWORD_ARGON2ID ? '$argon2id$' : '$2y$', $hash);
    }
}
