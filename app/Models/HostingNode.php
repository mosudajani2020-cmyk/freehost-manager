<?php

declare(strict_types=1);

namespace App\Models;

final class HostingNode
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $hostname,
        public readonly ?string $ipAddress,
        public readonly ?string $region,
        public readonly string $status,
        public readonly int $maxAccounts,
        public readonly int $currentAccounts,
        public readonly ?string $apiUrl,
        public readonly ?string $apiKeyHash,
        public readonly ?string $apiKeyPreview,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $r): self
    {
        return new self(
            id: (int)$r['id'],
            name: $r['name'],
            hostname: $r['hostname'],
            ipAddress: $r['ip_address'] ?? null,
            region: $r['region'] ?? null,
            status: $r['status'],
            maxAccounts: (int)$r['max_accounts'],
            currentAccounts: (int)$r['current_accounts'],
            apiUrl: $r['api_url'] ?? null,
            apiKeyHash: $r['api_key_hash'] ?? null,
            apiKeyPreview: $r['api_key_preview'] ?? null,
            createdAt: $r['created_at'],
            updatedAt: $r['updated_at'],
        );
    }

    public function isActive(): bool { return $this->status === 'active'; }
}
