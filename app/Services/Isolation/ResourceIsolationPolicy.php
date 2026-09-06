<?php

declare(strict_types=1);

namespace App\Services\Isolation;

use App\Models\HostingPlan;

/**
 * ResourceIsolationPolicy — defines resource boundaries for CPU, RAM, process count,
 * disk quota, and PHP limits per hosting account / plan (P2).
 */
final class ResourceIsolationPolicy
{
    public function __construct(
        public readonly int $memoryLimitMb,
        public readonly int $cpuLimitPercent,
        public readonly int $processLimit,
        public readonly int $maxExecutionTime,
        public readonly int $uploadMaxMb,
        public readonly int $diskQuotaMb,
        public readonly int $inodeQuota,
    ) {}

    public static function fromPlan(HostingPlan $plan): self
    {
        // Derive reasonable resource isolation defaults from plan specifications
        $storage = $plan->storageLimitMb;
        return new self(
            memoryLimitMb: $storage >= 10240 ? 512 : 256,
            cpuLimitPercent: $storage >= 10240 ? 200 : 100, // e.g. 1 or 2 cores
            processLimit: 50,
            maxExecutionTime: 60,
            uploadMaxMb: 50,
            diskQuotaMb: $storage,
            inodeQuota: $storage * 1000, // rough estimate: 1000 inodes per MB
        );
    }

    public static function defaultPolicy(): self
    {
        return new self(
            memoryLimitMb: 256,
            cpuLimitPercent: 100,
            processLimit: 30,
            maxExecutionTime: 30,
            uploadMaxMb: 20,
            diskQuotaMb: 1024,
            inodeQuota: 100000,
        );
    }

    public function toArray(): array
    {
        return [
            'memory_limit_mb' => $this->memoryLimitMb,
            'cpu_limit_percent' => $this->cpuLimitPercent,
            'process_limit' => $this->processLimit,
            'max_execution_time' => $this->maxExecutionTime,
            'upload_max_mb' => $this->uploadMaxMb,
            'disk_quota_mb' => $this->diskQuotaMb,
            'inode_quota' => $this->inodeQuota,
        ];
    }
}