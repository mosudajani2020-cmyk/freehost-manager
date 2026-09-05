<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\Providers\UsageCollectorInterface;

final class UsageService
{
    public function __construct(
        private readonly Database $db,
        private readonly HostingAccountRepository $accounts,
        private readonly HostingPlanRepository $plans,
        private readonly UsageCollectorInterface $collector,
        private readonly AuditService $audit,
    ) {}

    public function collectAndRecord(int $hostingAccountId, int $requestedBy): array
    {
        $acct = $this->accounts->findById($hostingAccountId);
        if (!$acct) throw new \RuntimeException('Hosting account not found', 404);
        $plan = $this->plans->findById($acct->planId);
        if (!$plan) throw new \RuntimeException('Plan not found', 404);

        $data = $this->collector->collect($hostingAccountId);
        $now = date('Y-m-d H:i:s');

        // Record snapshot for each type
        $types = [
            'storage' => [$data['storage_bytes'] / (1024*1024), $plan->storageLimitMb],
            'bandwidth' => [$data['bandwidth_bytes'] / (1024*1024), $plan->bandwidthLimitMb],
            'database' => [$data['database_count'], $plan->databaseLimit],
            'domain' => [$data['domain_count'], $plan->domainLimit],
            'subdomain' => [(int)$this->db->fetchColumn("SELECT COUNT(*) FROM subdomains WHERE hosting_account_id=?", [$hostingAccountId]), $plan->subdomainLimit],
        ];
        foreach ($types as $type => [$used, $limit]) {
            $this->db->query("INSERT INTO usage_records (hosting_account_id, type, used, limit_val, recorded_at) VALUES (?,?,?,?,?)", [$hostingAccountId, $type, (int)$used, (int)$limit, $now]);
        }

        $this->audit->log($requestedBy, 'usage.collected', 'hosting_account', (string)$hostingAccountId, 'success', ['storage_mb'=> (int)$types['storage'][0]]);
        return $data;
    }

    public function getUsage(int $userId, int $hostingAccountId): array
    {
        $acct = $this->accounts->findById($hostingAccountId);
        if (!$acct) throw new \RuntimeException('Not found', 404);
        if ($acct->userId !== $userId) throw new \RuntimeException('Access denied', 403);
        $plan = $this->plans->findById($acct->planId);
        $data = $this->collector->collect($hostingAccountId);
        $storageUsedMb = round($data['storage_bytes'] / (1024*1024), 2);
        $bandwidthUsedMb = round($data['bandwidth_bytes'] / (1024*1024), 2);

        // Get latest usage_records for history
        $history = $this->db->fetchAll("SELECT * FROM usage_records WHERE hosting_account_id=? ORDER BY recorded_at DESC LIMIT 10", [$hostingAccountId]);

        // Quota warnings
        $warnings = [];
        if ($plan) {
            if ($storageUsedMb / $plan->storageLimitMb > 0.9) $warnings[] = 'Storage usage >90%';
            if ($bandwidthUsedMb / $plan->bandwidthLimitMb > 0.9) $warnings[] = 'Bandwidth >90%';
            if ($data['database_count'] >= $plan->databaseLimit) $warnings[] = 'Database limit reached';
            if ($data['domain_count'] >= $plan->domainLimit) $warnings[] = 'Domain limit reached';
        }

        return [
            'account'=>$acct,
            'plan'=>$plan,
            'storage_used_mb'=>$storageUsedMb,
            'storage_limit_mb'=>$plan?->storageLimitMb ?? 0,
            'bandwidth_used_mb'=>$bandwidthUsedMb,
            'bandwidth_limit_mb'=>$plan?->bandwidthLimitMb ?? 0,
            'database_used'=>$data['database_count'],
            'database_limit'=>$plan?->databaseLimit ?? 0,
            'domain_used'=>$data['domain_count'],
            'domain_limit'=>$plan?->domainLimit ?? 0,
            'subdomain_used'=>(int)$this->db->fetchColumn("SELECT COUNT(*) FROM subdomains WHERE hosting_account_id=?", [$hostingAccountId]),
            'subdomain_limit'=>$plan?->subdomainLimit ?? 0,
            'warnings'=>$warnings,
            'history'=>$history,
        ];
    }

    public function getAggregated(int $hostingAccountId, string $period='daily'): array
    {
        // For Phase 7, return last 7 days or 30 days aggregation mock
        return $this->collector->aggregate($hostingAccountId, $period);
    }
}
