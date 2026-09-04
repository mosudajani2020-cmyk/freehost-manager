<?php

declare(strict_types=1);

namespace App\Validators;

final class LoginValidator
{
    public static function validate(array $data): array
    {
        $errors = [];
        $login = trim($data['login'] ?? $data['username'] ?? $data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($login === '') {
            $errors['login'] = 'Username or email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }
        return $errors;
    }
}
