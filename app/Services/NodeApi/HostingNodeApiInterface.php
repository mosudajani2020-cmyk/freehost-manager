<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

/**
 * Typed Node API operation contract (P3). No generic execution.
 */
interface HostingNodeApiInterface
{
    public function createIdentity(array $payload): array;
    public function createFilesystem(array $payload): array;
    public function configurePhpFpm(array $payload): array;
    public function applyResourcePolicy(array $payload): array;
    public function suspend(array $payload): array;
    public function unsuspend(array $payload): array;
    public function terminate(array $payload): array;
}
