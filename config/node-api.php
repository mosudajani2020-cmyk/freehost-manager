<?php

declare(strict_types=1);

/**
 * Node API configuration (P3).
 * All values come from env with safe production defaults.
 */
return [
    'version' => 'v1',
    'timestamp_tolerance' => max(60, (int)($_ENV['NODE_API_TIMESTAMP_TOLERANCE'] ?? 300)),
    'nonce_ttl' => max(60, (int)($_ENV['NODE_API_NONCE_TTL'] ?? 600)),
    'body_limit' => max(1024, (int)($_ENV['NODE_API_BODY_LIMIT'] ?? 65536)),
    'connect_timeout' => max(1, (int)($_ENV['NODE_API_CONNECT_TIMEOUT'] ?? 5)),
    'timeout' => max(2, (int)($_ENV['NODE_API_TIMEOUT'] ?? 10)),
    'rate_limit_auth_fail' => max(5, (int)($_ENV['NODE_API_RATE_LIMIT_AUTH_FAIL'] ?? 10)),
    'rate_limit_window' => max(30, (int)($_ENV['NODE_API_RATE_LIMIT_WINDOW'] ?? 60)),
    'tls_verify' => (function() {
        $env = strtolower($_ENV['APP_ENV'] ?? 'development');
        $verify = $_ENV['NODE_API_TLS_VERIFY'] ?? null;
        if ($verify !== null) {
            return filter_var($verify, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }
        // production must verify; development may relax for localhost mock
        return $env === 'production' ? true : false;
    })(),
    'allowed_operations' => [
        'hosting.create_identity',
        'hosting.create_filesystem',
        'hosting.configure_php_fpm',
        'hosting.apply_resource_policy',
        'hosting.suspend',
        'hosting.unsuspend',
        'hosting.terminate',
    ],
    'allowed_endpoints' => [
        'POST /v1/node/hosting/create-identity' => 'hosting.create_identity',
        'POST /v1/node/hosting/create-filesystem' => 'hosting.create_filesystem',
        'POST /v1/node/hosting/configure-php-fpm' => 'hosting.configure_php_fpm',
        'POST /v1/node/hosting/apply-resource-policy' => 'hosting.apply_resource_policy',
        'POST /v1/node/hosting/suspend' => 'hosting.suspend',
        'POST /v1/node/hosting/unsuspend' => 'hosting.unsuspend',
        'POST /v1/node/hosting/terminate' => 'hosting.terminate',
        'GET /v1/node/health' => '__health__',
    ],
];
