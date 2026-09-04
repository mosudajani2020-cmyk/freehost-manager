<?php

declare(strict_types=1);

namespace App\Validators;

final class HostingAccountValidator
{
    public static function validateCreate(array $data): array
    {
        $errors = [];
        $username = strtolower(trim($data['username'] ?? ''));
        $planId = $data['plan_id'] ?? null;

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (!preg_match('/^[a-z0-9]{3,32}$/', $username)) {
            $errors['username'] = 'Username must be 3-32 chars, a-z0-9 only (lowercase).';
        }
        if (empty($planId) || !filter_var($planId, FILTER_VALIDATE_INT)) {
            $errors['plan_id'] = 'Valid plan is required.';
        }
        return $errors;
    }

    public static function validateSubdomain(string $subdomain): array
    {
        $errors = [];
        $sub = strtolower(trim($subdomain));
        if ($sub === '') {
            $errors['subdomain'] = 'Subdomain is required.';
        } elseif (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $sub)) {
            $errors['subdomain'] = 'Invalid subdomain: 1-63 chars, a-z0-9, hyphens not at ends.';
        } elseif (in_array($sub, ['www','mail','ftp','admin','api','cdn','ns1','ns2'], true)) {
            $errors['subdomain'] = 'Reserved subdomain.';
        } elseif (str_contains($sub, '--')) {
            // allow but warn? we block double hyphen for safety
            $errors['subdomain'] = 'Subdomain cannot contain double hyphens.';
        }
        return $errors;
    }
}
