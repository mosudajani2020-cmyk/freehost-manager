<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface PaymentGatewayInterface
{
    public function createPayment(int $amountCents, string $currency, string $description, array $metadata = []): array;
    public function verifyWebhook(string $payload, string $signature, string $secret): bool;
    public function refundPayment(string $providerRef): array;
}
