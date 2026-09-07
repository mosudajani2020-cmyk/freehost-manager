<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

use App\Security\RateLimiter;

final class NodeRateLimiter
{
    public static function checkAuthFailure(string $nodeIdOrIp, array $config): bool
    {
        $key = 'node_api:auth_fail:' . $nodeIdOrIp;
        $max = (int)($config['rate_limit_auth_fail'] ?? 10);
        $window = (int)($config['rate_limit_window'] ?? 60);
        return RateLimiter::attempt($key, $max, $window);
    }

    public static function checkGeneric(string $key, int $max, int $window): bool
    {
        return RateLimiter::attempt($key, $max, $window);
    }
}
