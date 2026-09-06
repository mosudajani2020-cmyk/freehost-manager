<?php

declare(strict_types=1);

namespace App\Models;

final class ProvisioningJob
{
    public function __construct(
        public readonly int $id,
        public readonly string $jobUuid,
        public readonly int $hostingAccountId,
        public readonly ?int $nodeId,
        public readonly string $operation,
        public readonly ?string $payload,
        public readonly string $status,
        public readonly int $attempts,
        public readonly int $maxAttempts,
        public readonly ?string $lastError,
        public readonly string $idempotencyKey,
        public readonly ?int $requestedBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $completedAt,
        public readonly ?string $workerId = null,
        public readonly ?string $claimedAt = null,
        public readonly ?string $heartbeatAt = null,
        public readonly ?string $nextAttemptAt = null,
    ) {}

    public static function fromArray(array $r): self
    {
        return new self(
            id: (int)$r['id'],
            jobUuid: $r['job_uuid'],
            hostingAccountId: (int)$r['hosting_account_id'],
            nodeId: isset($r['node_id']) && $r['node_id'] !== null ? (int)$r['node_id'] : null,
            operation: $r['operation'],
            payload: $r['payload'] ?? null,
            status: $r['status'],
            attempts: (int)$r['attempts'],
            maxAttempts: (int)$r['max_attempts'],
            lastError: $r['last_error'] ?? null,
            idempotencyKey: $r['idempotency_key'],
            requestedBy: isset($r['requested_by']) && $r['requested_by'] !== null ? (int)$r['requested_by'] : null,
            createdAt: $r['created_at'],
            updatedAt: $r['updated_at'],
            completedAt: $r['completed_at'] ?? null,
            workerId: $r['worker_id'] ?? null,
            claimedAt: $r['claimed_at'] ?? null,
            heartbeatAt: $r['heartbeat_at'] ?? null,
            nextAttemptAt: $r['next_attempt_at'] ?? null,
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['active','failed','terminated'], true);
    }

    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->attempts < $this->maxAttempts;
    }
}
