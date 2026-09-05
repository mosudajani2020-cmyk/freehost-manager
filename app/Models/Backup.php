<?php

declare(strict_types=1);

namespace App\Models;

final class Backup
{
    public function __construct(
        public readonly int $id,
        public readonly int $hostingAccountId,
        public readonly string $type,
        public readonly string $status,
        public readonly ?int $sizeBytes,
        public readonly ?string $filePath,
        public readonly int $retentionDays,
        public readonly ?string $expiresAt,
        public readonly ?int $requestedBy,
        public readonly ?string $completedAt,
        public readonly ?string $lastError,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $r): self
    {
        return new self(
            id: (int)$r['id'],
            hostingAccountId: (int)$r['hosting_account_id'],
            type: $r['type'],
            status: $r['status'],
            sizeBytes: isset($r['size_bytes']) && $r['size_bytes'] !== null ? (int)$r['size_bytes'] : null,
            filePath: $r['file_path'] ?? null,
            retentionDays: (int)$r['retention_days'],
            expiresAt: $r['expires_at'] ?? null,
            requestedBy: isset($r['requested_by']) && $r['requested_by'] !== null ? (int)$r['requested_by'] : null,
            completedAt: $r['completed_at'] ?? null,
            lastError: $r['last_error'] ?? null,
            createdAt: $r['created_at'],
            updatedAt: $r['updated_at'],
        );
    }
}
