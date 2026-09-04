<?php

declare(strict_types=1);

namespace App\Models;

final class DnsRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $hostingAccountId,
        public readonly string $hostname,
        public readonly string $type,
        public readonly string $value,
        public readonly int $ttl,
        public readonly ?int $priority,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $r): self
    {
        return new self(
            id: (int)$r['id'],
            hostingAccountId: (int)$r['hosting_account_id'],
            hostname: $r['hostname'],
            type: $r['type'],
            value: $r['value'],
            ttl: (int)$r['ttl'],
            priority: isset($r['priority']) && $r['priority'] !== null ? (int)$r['priority'] : null,
            status: $r['status'],
            createdAt: $r['created_at'],
            updatedAt: $r['updated_at'],
        );
    }
}
