<?php

declare(strict_types=1);

namespace App\Services\Providers;

final class LocalMockPaymentGateway implements PaymentGatewayInterface
{
    public function createPayment(int $amountCents, string $currency, string $description, array $metadata = []): array
    {
        // Never store card numbers/CVV — mock only
        $ref = 'mock_' . bin2hex(random_bytes(8));
        $url = 'https://pay.mock/' . bin2hex(random_bytes(8));
        error_log("[MockPayment] create {$amountCents} {$currency} {$description} ref {$ref}");
        return ['success'=>true,'provider_ref'=>$ref,'hosted_url'=>$url,'status'=>'pending'];
    }

    public function verifyWebhook(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function refundPayment(string $providerRef): array
    {
        error_log("[MockPayment] refund {$providerRef}");
        return ['success'=>true,'status'=>'refunded'];
    }
}
