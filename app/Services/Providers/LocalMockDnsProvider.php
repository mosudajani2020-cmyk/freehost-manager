<?php

declare(strict_types=1);

namespace App\Services\Providers;

final class LocalMockDnsProvider implements DnsProviderInterface
{
    public function createRecord(string $hostname, string $type, string $value, int $ttl = 3600): array
    {
        // Simulate occasional failure for testing? For now always success
        error_log("[MockDNS] create {$type} {$hostname} -> {$value}");
        // Simulate DNS injection check: provider also validates hostname strictly
        if (str_contains($hostname, ' ') || str_contains($hostname, "'") || str_contains($hostname, '"') || str_contains($hostname, ';')) {
            return ['success'=>false,'message'=>'DNS injection detected'];
        }
        return ['success'=>true,'message'=>'DNS record created (mock)'];
    }

    public function updateRecord(string $hostname, string $type, string $value): array
    {
        error_log("[MockDNS] update {$hostname} {$type}");
        return ['success'=>true,'message'=>'Updated (mock)'];
    }

    public function deleteRecord(string $hostname, string $type): array
    {
        error_log("[MockDNS] delete {$hostname} {$type}");
        return ['success'=>true,'message'=>'Deleted (mock)'];
    }

    public function getRecord(string $hostname, string $type): ?array
    {
        return ['hostname'=>$hostname,'type'=>$type,'value'=>'127.0.0.1','ttl'=>3600,'status'=>'active'];
    }

    public function verifyPropagation(string $hostname): bool
    {
        // Mock: always true after short delay
        return true;
    }
}
