<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\HostingAccount;

final class LocalMockDomainProvider implements DomainProviderInterface
{
    public function assignDomain(HostingAccount $account, string $hostname): array
    {
        // Mock: just validate and log, no real DNS
        error_log("[MockDomain] assign {$hostname} to account {$account->id}");
        return ['success'=>true,'message'=>'Domain assigned (mock)'];
    }

    public function removeDomain(HostingAccount $account, string $hostname): array
    {
        error_log("[MockDomain] remove {$hostname} from {$account->id}");
        return ['success'=>true,'message'=>'Domain removed (mock)'];
    }

    public function verifyOwnership(HostingAccount $account, string $hostname): bool
    {
        // Mock: true if hostname ends with configured main domain or is subdomain of it
        $main = strtolower(trim($_ENV['APP_DOMAIN'] ?? 'freehost.example'));
        $host = strtolower($hostname);
        return $host === $main || str_ends_with($host, '.' . $main);
    }
}
