<?php

declare(strict_types=1);

namespace App\Services\Isolation;

/**
 * HostingNodeIsolationInterface — typed contract for Linux-side tenant isolation (P2).
 *
 * Exposes specific typed operations. Strictly forbids generic shell/command execution
 * runners (no exec, shell_exec, system, etc.).
 */
interface HostingNodeIsolationInterface
{
    public function createCustomerIdentity(CustomerSystemIdentity $identity): bool;
    public function removeCustomerIdentity(CustomerSystemIdentity $identity): bool;
    public function createCustomerFilesystem(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout): bool;
    public function applyFilesystemPermissions(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout): bool;
    public function createPhpFpmPool(CustomerSystemIdentity $identity, HostingFilesystemLayout $layout, ResourceIsolationPolicy $policy): bool;
    public function removePhpFpmPool(CustomerSystemIdentity $identity): bool;
    public function applyResourcePolicy(CustomerSystemIdentity $identity, ResourceIsolationPolicy $policy): bool;
}