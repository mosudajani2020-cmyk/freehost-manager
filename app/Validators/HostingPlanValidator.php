<?php

declare(strict_types=1);

namespace App\Validators;

final class HostingPlanValidator
{
    public static function validate(array $data): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        $slug = strtolower(trim($data['slug'] ?? ''));
        $storage = $data['storage_limit_mb'] ?? null;
        $bandwidth = $data['bandwidth_limit_mb'] ?? null;
        $dbLimit = $data['database_limit'] ?? null;
        $domainLimit = $data['domain_limit'] ?? null;
        $subLimit = $data['subdomain_limit'] ?? null;

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            $errors['name'] = 'Name must be 2-50 chars.';
        }
        if ($slug === '' || !preg_match('/^[a-z0-9\-]{2,50}$/', $slug)) {
            $errors['slug'] = 'Slug must be 2-50 chars: a-z, 0-9, hyphen.';
        }
        foreach (['storage_limit_mb'=>$storage, 'bandwidth_limit_mb'=>$bandwidth] as $k=>$v) {
            if ($v === null || $v === '' ) {
                $errors[$k] = 'Required.';
            } elseif (!filter_var($v, FILTER_VALIDATE_INT) || (int)$v < 0 || (int)$v > 1000000) {
                $errors[$k] = 'Must be integer 0-1000000.';
            }
        }
        foreach (['database_limit'=>$dbLimit, 'domain_limit'=>$domainLimit, 'subdomain_limit'=>$subLimit] as $k=>$v) {
            if ($v === null || $v === '') {
                $errors[$k] = 'Required.';
            } elseif (!filter_var($v, FILTER_VALIDATE_INT) || (int)$v < 0 || (int)$v > 1000) {
                $errors[$k] = 'Must be integer 0-1000.';
            }
        }

        return $errors;
    }
}
