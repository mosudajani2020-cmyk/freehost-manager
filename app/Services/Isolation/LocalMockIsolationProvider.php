<?php

declare(strict_types=1);

namespace App\Services\Isolation;

/**
 * LocalMockIsolationProvider — local development / Windows mock implementation of hosting isolation (P2).
 *
 * Simulates filesystem structure under the storage/hosting root safely on Windows
 * without attempting real system user creation, FPM socket binding, or cgroups.
 */
final class LocalMockIsolationProvider implements HostingNodeIsolationInterface
{
    public function createCustomerIdentity(CustomerSystemIdentity $identity): bool
    {
        error_log("[MockIsolation] createCustomerIdentity user={$identity->username()} uid={$identity->uid()}");
        return true;
    }

    public function removeCustomerIdentity(CustomerSystemIdentity $identity): bool
    {
        error_log("[MockIsolation] removeCustomerIdentity user={$identity->username()}");
        return true;
    }

    public function createCustomerFilesystem(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout): bool
    {
        $root = $layout->getCustomerRoot($identity->accountId());
        $dirs = [
            $layout->getPublicHtml($identity->accountId()),
            $layout->getLogsDirectory($identity->accountId()),
            $layout->getTmpDirectory($identity->accountId()),
            $layout->getPhpFpmPoolDirectory($identity->accountId()),
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
        error_log("[MockIsolation] createCustomerFilesystem root={$root}");
        return true;
    }

    public function applyFilesystemPermissions(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout): bool
    {
        error_log("[MockIsolation] applyFilesystemPermissions user={$identity->username()}");
        return true;
    }

    public function createPhpFpmPool(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout, ResourceIsolationPolicy $policy): bool
    {
        $poolConfig = PhpFpmPoolConfigGenerator::generate($identity, $layout, $policy);
        $poolFile = $layout->getPhpFpmPoolDirectory($identity->accountId()) . '/' . $identity->username() . '.conf';
        @file_put_contents($poolFile, $poolConfig);
        error_log("[MockIsolation] createPhpFpmPool user={$identity->username()}");
        return true;
    }

    public function removePhpFpmPool(CustomerSystemIdentity $identity): bool
    {
        error_log("[MockIsolation] removePhpFpmPool user={$identity->username()}");
        return true;
    }

    public function applyResourcePolicy(CustomerSystemIdentity $identity, ResourceIsolationPolicy $policy): bool
    {
        error_log("[MockIsolation] applyResourcePolicy user={$identity->username()} memory={$policy->memoryLimitMb}MB");
        return true;
    }
}