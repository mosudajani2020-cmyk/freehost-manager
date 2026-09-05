<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface UsageCollectorInterface
{
    public function collect(int $hostingAccountId): array; // ['storage_bytes'=>int,'bandwidth_bytes'=>int,'database_count'=>int,'domain_count'=>int]
    public function aggregate(int $hostingAccountId, string $period): array; // daily/monthly
}
