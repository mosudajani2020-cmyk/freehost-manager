<?php

declare(strict_types=1);

namespace App\Validators;

final class RegistrationValidator
{
    /**
     * @return array<string,string> field => error
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $fullName = trim($data['full_name'] ?? '');
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirm = $data['password_confirm'] ?? $data['password_confirmation'] ?? '';

        if ($fullName === '') {
            $errors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            $errors['full_name'] = 'Full name must be between 2 and 120 characters.';
        } elseif (!preg_match('/^[\p{L}\p{M} \-\'\.]+$/u', $fullName)) {
            $errors['full_name'] = 'Full name contains invalid characters.';
        }

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!preg_match('/^[a-z0-9_\.]{3,50}$/', $username)) {
            $errors['username'] = 'Username must be 3-50 chars: lowercase letters, numbers, underscore, dot.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        } elseif (strlen($email) > 190) {
            $errors['email'] = 'Email is too long.';
        }

        $minLen = (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8);
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < $minLen) {
            $errors['password'] = "Password must be at least {$minLen} characters.";
        } elseif (!self::isStrong($password)) {
            $errors['password'] = 'Password must contain uppercase, lowercase and digit.';
        }

        if ($confirm === '') {
            $errors['password_confirm'] = 'Please confirm password.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        return $errors;
    }

    public static function isStrong(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        return true;
    }

    public static function sanitize(array $data): array
    {
        return [
            'full_name' => trim($data['full_name'] ?? ''),
            'username' => strtolower(trim($data['username'] ?? '')),
            'email' => strtolower(trim($data['email'] ?? '')),
        ];
    }
}
