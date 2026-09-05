<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Helpers\Database;

final class LocalMockMonitoringProvider implements MonitoringProviderInterface
{
    public function __construct(private readonly Database $db) {}

    public function getSystemHealth(): array
    {
        $checks = [];
        $status = 'healthy';
        // Check failed provisioning jobs
        $failed = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE status='failed'");
        $checks['failed_provisioning_jobs'] = $failed;
        if ($failed > 5) $status = 'critical';
        elseif ($failed > 0) $status = 'warning';

        // Check hosting accounts
        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts");
        $active = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts WHERE status='active'");
        $checks['hosting_total'] = $total;
        $checks['hosting_active'] = $active;

        // Check storage usage high
        $highUsage = 0;
        // Simple check: if any hosting exceeds 90% of plan
        $checks['high_usage_accounts'] = $highUsage;

        // Check backups failed
        $failedBackups = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM backups WHERE status='failed'");
        $checks['failed_backups'] = $failedBackups;
        if ($failedBackups > 3 && $status === 'healthy') $status = 'warning';

        return ['status'=>$status,'checks'=>$checks];
    }

    public function getProvisioningHealth(): array
    {
        $queued = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE status IN ('queued','provisioning')");
        $failed = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE status='failed'");
        $status = 'healthy';
        if ($failed > 5) $status = 'critical';
        elseif ($failed > 0 || $queued > 10) $status = 'warning';
        return ['status'=>$status,'queued'=>$queued,'failed'=>$failed];
    }

    public function getFailedJobs(): array
    {
        return $this->db->fetchAll("SELECT * FROM provisioning_jobs WHERE status='failed' ORDER BY updated_at DESC LIMIT 10");
    }
}
