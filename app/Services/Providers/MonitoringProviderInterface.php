<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface MonitoringProviderInterface
{
    public function getSystemHealth(): array; // ['status'=>healthy|warning|critical|unknown, 'checks'=>[]]
    public function getProvisioningHealth(): array;
    public function getFailedJobs(): array;
}
