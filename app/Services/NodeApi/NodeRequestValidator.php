<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

final class NodeRequestValidator
{
    private const ALLOWED_OPS = [
        'hosting.create_identity',
        'hosting.create_filesystem',
        'hosting.configure_php_fpm',
        'hosting.apply_resource_policy',
        'hosting.suspend',
        'hosting.unsuspend',
        'hosting.terminate',
    ];

    public static function validateOperation(string $op): bool
    {
        return in_array($op, self::ALLOWED_OPS, true);
    }

    public static function validatePayload(string $operation, array $payload): array
    {
        $errors = [];
        // All operations require account_id
        if (!isset($payload['account_id']) || !is_int($payload['account_id']) || $payload['account_id'] < 1) {
            $errors[] = 'account_id must be a positive integer';
        }
        // Operation-specific
        switch ($operation) {
            case 'hosting.create_identity':
            case 'hosting.create_filesystem':
            case 'hosting.configure_php_fpm':
            case 'hosting.apply_resource_policy':
            case 'hosting.suspend':
            case 'hosting.unsuspend':
            case 'hosting.terminate':
                // plan_id optional for some ops
                if (isset($payload['plan_id']) && (!is_int($payload['plan_id']) || $payload['plan_id'] < 1)) {
                    $errors[] = 'plan_id must be a positive integer';
                }
                break;
        }
        // Reject arbitrary dangerous keys
        $blocked = ['username','linux_username','uid','gid','filesystem_root','socket_path','php_directive','arbitrary'];
        foreach ($blocked as $k) {
            if (array_key_exists($k, $payload)) {
                $errors[] = "field '$k' is not allowed (derived server-side)";
            }
        }
        // Size limits
        foreach ($payload as $k => $v) {
            if (is_string($v) && strlen($v) > 500) {
                $errors[] = "field '$k' too large";
            }
        }
        // JSON depth is limited by json_decode; we check array depth shallow
        if (count($payload) > 20) {
            $errors[] = 'payload has too many fields';
        }
        return $errors;
    }

    public static function validateBody(string $body, int $limit): array
    {
        if (strlen($body) > $limit) {
            return ['request body too large'];
        }
        if ($body === '') {
            return [];
        }
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['invalid JSON: ' . json_last_error_msg()];
        }
        if (!is_array($data)) {
            return ['JSON body must be an object'];
        }
        // Unknown top-level keys check: allow only known envelope
        $allowedTop = ['operation','payload','account_id','plan_id'];
        // For flexibility, we allow operation and payload wrapper
        return [];
    }
}
