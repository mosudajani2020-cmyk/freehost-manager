<?php

declare(strict_types=1);

namespace App\Models;

final class Invoice
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?int $subscriptionId,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $dueDate,
        public readonly ?string $paidAt,
        public readonly ?string $hostedUrl,
        public readonly ?string $providerRef,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromArray(array $r): self
    {
        return new self(
            id: (int)$r['id'],
            userId: (int)$r['user_id'],
            subscriptionId: isset($r['subscription_id']) && $r['subscription_id'] !== null ? (int)$r['subscription_id'] : null,
            amountCents: (int)$r['amount_cents'],
            currency: $r['currency'],
            status: $r['status'],
            dueDate: $r['due_date'] ?? null,
            paidAt: $r['paid_at'] ?? null,
            hostedUrl: $r['hosted_url'] ?? null,
            providerRef: $r['provider_ref'] ?? null,
            createdAt: $r['created_at'],
            updatedAt: $r['updated_at'],
        );
    }

    public function formattedAmount(): string
    {
        return number_format($this->amountCents / 100, 2) . ' ' . $this->currency;
    }
}
