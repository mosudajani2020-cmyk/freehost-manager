<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Services\AuditService;
use App\Services\MonitoringService;
use App\Services\Providers\LocalMockMonitoringProvider;

final class AdminMonitoringController
{
    private function service(): MonitoringService
    {
        $db = Database::getInstance();
        return new MonitoringService($db, new LocalMockMonitoringProvider($db), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $health = $this->service()->getSystemHealth($userId);
        $provisioning = $this->service()->getProvisioningHealth($userId);
        $stats = $this->service()->getOperationalStats($userId);
        $failed = $this->service()->getFailedJobs($userId);
        // Recent audit
        $db = Database::getInstance();
        $recentAudit = $db->fetchAll("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 10");
        View::render('admin.monitoring.index', ['title'=>'System Monitoring','health'=>$health,'provisioning'=>$provisioning,'stats'=>$stats,'failed'=>$failed,'recentAudit'=>$recentAudit]);
    }

    public function usage(array $params = []): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT ha.id, ha.username, ha.status, hp.name as plan_name, hp.storage_limit_mb FROM hosting_accounts ha JOIN hosting_plans hp ON hp.id=ha.plan_id ORDER BY ha.id DESC LIMIT 50");
        // Add warnings
        foreach ($rows as &$r) {
            $r['storage_used_mb'] = 0;
            // Calculate storage via FileService? For admin, just show placeholder
        }
        View::render('admin.monitoring.usage', ['title'=>'Usage Overview','rows'=>$rows]);
    }
}
