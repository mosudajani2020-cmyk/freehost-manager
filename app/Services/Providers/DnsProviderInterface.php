<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface DnsProviderInterface
{
    public function createRecord(string $hostname, string $type, string $value, int $ttl = 3600): array;
    public function updateRecord(string $hostname, string $type, string $value): array;
    public function deleteRecord(string $hostname, string $type): array;
    public function getRecord(string $hostname, string $type): ?array;
    public function verifyPropagation(string $hostname): bool;
}
