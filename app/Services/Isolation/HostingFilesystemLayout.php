<?php

declare(strict_types=1);

namespace App\Services\Isolation;

use App\Security\PathGuard;

/**
 * HostingFilesystemLayout — trusted path-mapping mechanism for customer hosting storage (P2).
 *
 * Separates control-panel storage from customer hosting roots.
 * Configurable via FHM_HOSTING_ROOT environment variable (defaulting to /srv/freehost/customers
 * in production / linux, or storage/hosting in local/windows mock development).
 */
final class HostingFilesystemLayout
{
    private readonly string $hostingRoot;

    public function __construct(?string $hostingRoot = null)
    {
        $root = $hostingRoot ?? env('FHM_HOSTING_ROOT', '');
        if ($root === '') {
            $root = defined('STORAGE_PATH') ? STORAGE_PATH . '/hosting' : '/srv/freehost/customers';
        }
        $this->hostingRoot = rtrim(str_replace('\\', '/', $root), '/');
    }

    public function getHostingRoot(): string
    {
        return $this->hostingRoot;
    }

    public function getCustomerRoot(int $accountId): string
    {
        $identity = new CustomerSystemIdentity($accountId);
        $path = $this->hostingRoot . '/' . $identity->username();
        $this->guardPath($path);
        return $path;
    }

    public function getPublicHtml(int $accountId): string
    {
        $path = $this->getCustomerRoot($accountId) . '/public_html';
        $this->guardPath($path);
        return $path;
    }

    public function getLogsDirectory(int $accountId): string
    {
        $path = $this->getCustomerRoot($accountId) . '/logs';
        $this->guardPath($path);
        return $path;
    }

    public function getTmpDirectory(int $accountId): string
    {
        $path = $this->getCustomerRoot($accountId) . '/tmp';
        $this->guardPath($path);
        return $path;
    }

    public function getPhpFpmPoolDirectory(int $accountId): string
    {
        $path = $this->getCustomerRoot($accountId) . '/fpm';
        $this->guardPath($path);
        return $path;
    }

    /**
     * Ensure a path is strictly inside the hosting root and contains no traversal attacks.
     */
    private function guardPath(string $path): void
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = str_replace('\\', '/', $this->hostingRoot);

        if (str_contains($normalizedPath, '..') || str_contains($normalizedPath, "\0")) {
            throw new \RuntimeException('Path traversal detected in customer filesystem layout.');
        }

        if (!str_starts_with($normalizedPath, $normalizedRoot . '/') && $normalizedPath !== $normalizedRoot) {
            throw new \RuntimeException('Path escapes hosting root boundary: ' . $path);
        }
    }
}