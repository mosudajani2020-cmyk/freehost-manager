<?php

declare(strict_types=1);

namespace App\Models;

final class Subdomain
{
    public function __construct(
        public readonly int $id,
        public readonly int $hostingAccountId,
        public readonly ?int $domainId,
        public readonly string $subdomain,
        public readonly string $fullDomain,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            hostingAccountId: (int) $row['hosting_account_id'],
            domainId: isset($row['domain_id']) && $row['domain_id'] !== null ? (int) $row['domain_id'] : null,
            subdomain: $row['subdomain'],
            fullDomain: $row['full_domain'],
            status: $row['status'],
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }
}
