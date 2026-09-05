<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Services\Providers\MonitoringProviderInterface;

final class MonitoringService
{
    public function __construct(
        private readonly Database $db,
        private readonly MonitoringProviderInterface $provider,
        private readonly AuditService $audit,
    ) {}

    public function getSystemHealth(int $requesterId): array
    {
        // Admin only check should be done in controller via Rbac, but double-check
        $health = $this->provider->getSystemHealth();
        $this->audit->log($requesterId, 'monitoring.health_view', 'system', 'health', 'success', ['status'=>$health['status']]);
        return $health;
    }

    public function getProvisioningHealth(int $requesterId): array
    {
        $health = $this->provider->getProvisioningHealth();
        return $health;
    }

    public function getOperationalStats(int $requesterId): array
    {
        $stats = [
            'total_users' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM users"),
            'hosting_total' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts"),
            'hosting_active' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts WHERE status='active'"),
            'hosting_suspended' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts WHERE status='suspended'"),
            'databases' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM customer_databases"),
            'backups' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM backups"),
            'backups_failed' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM backups WHERE status='failed'"),
            'provisioning_failed' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE status='failed'"),
            'provisioning_queued' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE status='queued'"),
            'audit_last_24h' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
        ];
        $this->audit->log($requesterId, 'monitoring.stats_view', 'system', 'stats', 'success', []);
        return $stats;
    }

    public function getFailedJobs(int $requesterId): array
    {
        $jobs = $this->provider->getFailedJobs();
        return $jobs;
    }
}
