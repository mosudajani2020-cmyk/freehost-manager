<?php

declare(strict_types=1);

namespace App\Models;

final class SslCertificate
{
    public function __construct(
        public readonly int $id,
        public readonly int $hostingAccountId,
        public readonly string $hostname,
        public readonly string $status,
        public readonly string $provider,
        public readonly ?string $expiresAt,
        public readonly bool $autoRenew,
        public readonly ?string $lastError,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $r): self
    {
        return new self(
            id: (int)$r['id'],
            hostingAccountId: (int)$r['hosting_account_id'],
            hostname: $r['hostname'],
            status: $r['status'],
            provider: $r['provider'],
            expiresAt: $r['expires_at'] ?? null,
            autoRenew: (bool)$r['auto_renew'],
            lastError: $r['last_error'] ?? null,
            createdAt: $r['created_at'],
            updatedAt: $r['updated_at'],
        );
    }
}
