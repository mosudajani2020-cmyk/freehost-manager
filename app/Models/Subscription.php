<?php

declare(strict_types=1);

namespace App\Models;

final class Subscription
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?int $hostingAccountId,
        public readonly int $planId,
        public readonly string $status,
        public readonly ?string $trialEndsAt,
        public readonly ?string $currentPeriodStart,
        public readonly ?string $currentPeriodEnd,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $r): self
    {
        return new self(
            id: (int)$r['id'],
            userId: (int)$r['user_id'],
            hostingAccountId: isset($r['hosting_account_id']) && $r['hosting_account_id'] !== null ? (int)$r['hosting_account_id'] : null,
            planId: (int)$r['plan_id'],
            status: $r['status'],
            trialEndsAt: $r['trial_ends_at'] ?? null,
            currentPeriodStart: $r['current_period_start'] ?? null,
            currentPeriodEnd: $r['current_period_end'] ?? null,
            createdAt: $r['created_at'],
            updatedAt: $r['updated_at'],
        );
    }
}
