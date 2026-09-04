<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
    'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 30), // minutes
    'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'samesite' => $_ENV['SESSION_SAMESITE'] ?? 'Lax',
    'cookie_name' => $_ENV['SESSION_COOKIE_NAME'] ?? 'FHSESSID',
    'path' => '/',
    'domain' => null,
];
