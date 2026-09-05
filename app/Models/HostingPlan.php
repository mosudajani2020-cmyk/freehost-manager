<?php

declare(strict_types=1);

namespace App\Models;

final class HostingPlan
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly int $storageLimitMb,
        public readonly int $bandwidthLimitMb,
        public readonly int $databaseLimit,
        public readonly int $domainLimit,
        public readonly int $subdomainLimit,
        public readonly int $backupLimit,
        public readonly int $emailLimit,
        public readonly int $priceCents,
        public readonly string $currency,
        public readonly string $status,
        public readonly bool $isDefault,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: $row['name'],
            slug: $row['slug'],
            description: $row['description'] ?? null,
            storageLimitMb: (int) $row['storage_limit_mb'],
            bandwidthLimitMb: (int) $row['bandwidth_limit_mb'],
            databaseLimit: (int) $row['database_limit'],
            domainLimit: (int) $row['domain_limit'],
            subdomainLimit: (int) $row['subdomain_limit'],
            backupLimit: (int) ($row['backup_limit'] ?? 3),
            emailLimit: (int) ($row['email_limit'] ?? 1),
            priceCents: (int) ($row['price_cents'] ?? 0),
            currency: $row['currency'] ?? 'USD',
            status: $row['status'],
            isDefault: (bool) $row['is_default'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
