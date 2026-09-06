<?php

declare(strict_types=1);

namespace App\Services\Isolation;

/**
 * LinuxIsolationProvider — production Linux hosting node isolation provider (P2).
 *
 * Designed for dedicated Linux hosting nodes (P3/P4). When invoked on non-Linux
 * environments (e.g. Windows local development), it fails safely with a clear RuntimeException.
 *
 * Strictly adheres to the rule: NO generic command runners.
 */
final class LinuxIsolationProvider implements HostingNodeIsolationInterface
{
    private function assertLinuxRuntime(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            throw new \RuntimeException('LinuxIsolationProvider can only be executed on a Linux hosting node runtime.');
        }
    }

    public function createCustomerIdentity(CustomerSystemIdentity $identity): bool
    {
        $this->assertLinuxRuntime();
        // Future P3/P4 Linux implementation (via restricted node API / daemon)
        throw new \BadMethodCallException('Not implemented for local runtime.');
    }

    public function removeCustomerIdentity(CustomerSystemIdentity $identity): bool
    {
        $this->assertLinuxRuntime();
        throw new \BadMethodCallException('Not implemented for local runtime.');
    }

    public function createCustomerFilesystem(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout): bool
    {
        $this->assertLinuxRuntime();
        throw new \BadMethodCallException('Not implemented for local runtime.');
    }

    public function applyFilesystemPermissions(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout): bool
    {
        $this->assertLinuxRuntime();
        throw new \BadMethodCallException('Not implemented for local runtime.');
    }

    public function createPhpFpmPool(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout, ResourceIsolationPolicy $policy): bool
    {
        $this->assertLinuxRuntime();
        throw new \BadMethodCallException('Not implemented for local runtime.');
    }

    public function removePhpFpmPool(CustomerSystemIdentity $identity): bool
    {
        $this->assertLinuxRuntime();
        throw new \BadMethodCallException('Not implemented for local runtime.');
    }

    public function applyResourcePolicy(CustomerSystemIdentity $identity, ResourceIsolationPolicy $policy): bool
    {
        $this->assertLinuxRuntime();
        throw new \BadMethodCallException('Not implemented for local runtime.');
    }
}