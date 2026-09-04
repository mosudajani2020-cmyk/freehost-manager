<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\RegistrationValidator;
use PHPUnit\Framework\TestCase;

final class RegistrationValidatorTest extends TestCase
{
    public function testValidDataPasses(): void
    {
        $errors = RegistrationValidator::validate([
            'full_name' => 'John Doe',
            'username' => 'john_doe123',
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'password_confirm' => 'StrongPass1',
        ]);
        $this->assertEmpty($errors);
    }

    public function testMissingFields(): void
    {
        $errors = RegistrationValidator::validate([]);
        $this->assertArrayHasKey('full_name', $errors);
        $this->assertArrayHasKey('username', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testInvalidEmail(): void
    {
        $errors = RegistrationValidator::validate([
            'full_name' => 'Test',
            'username' => 'testuser',
            'email' => 'not-an-email',
            'password' => 'StrongPass1',
            'password_confirm' => 'StrongPass1',
        ]);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testInvalidUsername(): void
    {
        $errors = RegistrationValidator::validate([
            'full_name' => 'Test User',
            'username' => 'AB', // too short, uppercase
            'email' => 'test@example.com',
            'password' => 'StrongPass1',
            'password_confirm' => 'StrongPass1',
        ]);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testWeakPassword(): void
    {
        $errors = RegistrationValidator::validate([
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'weak',
            'password_confirm' => 'weak',
        ]);
        $this->assertArrayHasKey('password', $errors);

        // Missing uppercase/digit
        $errors2 = RegistrationValidator::validate([
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'alllowercase1',
            'password_confirm' => 'alllowercase1',
        ]);
        $this->assertArrayHasKey('password', $errors2);
    }

    public function testPasswordMismatch(): void
    {
        $errors = RegistrationValidator::validate([
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'StrongPass1',
            'password_confirm' => 'Different1',
        ]);
        $this->assertArrayHasKey('password_confirm', $errors);
    }

    public function testXssInFullNameBlocked(): void
    {
        $errors = RegistrationValidator::validate([
            'full_name' => '<script>alert(1)</script>',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'StrongPass1',
            'password_confirm' => 'StrongPass1',
        ]);
        $this->assertArrayHasKey('full_name', $errors);
    }

    public function testSqlInjectionInUsernameBlocked(): void
    {
        $errors = RegistrationValidator::validate([
            'full_name' => 'Test User',
            'username' => "' OR 1=1 --",
            'email' => 'test@example.com',
            'password' => 'StrongPass1',
            'password_confirm' => 'StrongPass1',
        ]);
        $this->assertArrayHasKey('username', $errors);
    }
}
