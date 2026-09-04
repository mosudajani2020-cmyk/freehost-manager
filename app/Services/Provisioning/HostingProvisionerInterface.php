<?php

declare(strict_types=1);

namespace App\Services\Provisioning;

use App\Models\HostingAccount;

/**
 * Provisioning abstraction — Phase 2 uses LocalMockProvisioner only.
 * Future LinuxProvisioner will handle real filesystem/PHP-FPM/DNS.
 * Never call exec/shell from web requests — this interface is allow-list only.
 */
interface HostingProvisionerInterface
{
    public function createHostingAccount(HostingAccount $account): ProvisionResult;
    public function suspendHostingAccount(HostingAccount $account): ProvisionResult;
    public function activateHostingAccount(HostingAccount $account): ProvisionResult;
    public function terminateHostingAccount(HostingAccount $account): ProvisionResult;
    public function createSubdomain(HostingAccount $account, string $subdomain, string $fullDomain): ProvisionResult;
    public function deleteSubdomain(HostingAccount $account, string $fullDomain): ProvisionResult;
}
