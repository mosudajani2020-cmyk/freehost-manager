<?php

declare(strict_types=1);

namespace App\Models;

final class HostingAccount
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $planId,
        public readonly string $username,
        public readonly ?string $domain,
        public readonly string $status,
        public readonly float $storageUsedMb,
        public readonly float $bandwidthUsedMb,
        public readonly string $rootPath,
        public readonly ?string $suspendedAt,
        public readonly ?string $terminatedAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public ?HostingPlan $plan = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            planId: (int) $row['plan_id'],
            username: $row['username'],
            domain: $row['domain'] ?? null,
            status: $row['status'],
            storageUsedMb: (float) $row['storage_used_mb'],
            bandwidthUsedMb: (float) $row['bandwidth_used_mb'],
            rootPath: $row['root_path'],
            suspendedAt: $row['suspended_at'] ?? null,
            terminatedAt: $row['terminated_at'] ?? null,
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    public function canCreateSubdomain(): bool
    {
        return $this->status === 'active';
    }
}
