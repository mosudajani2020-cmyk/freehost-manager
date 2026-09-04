<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Models\HostingAccount;

interface DomainProviderInterface
{
    public function assignDomain(HostingAccount $account, string $hostname): array; // ['success'=>bool,'message'=>string]
    public function removeDomain(HostingAccount $account, string $hostname): array;
    public function verifyOwnership(HostingAccount $account, string $hostname): bool;
}
