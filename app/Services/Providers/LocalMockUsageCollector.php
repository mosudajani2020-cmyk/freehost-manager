<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Helpers\Database;

final class LocalMockUsageCollector implements UsageCollectorInterface
{
    public function __construct(private readonly Database $db) {}

    public function collect(int $hostingAccountId): array
    {
        // Storage via filesystem (if hosting root exists) else 0
        $row = $this->db->fetch("SELECT root_path FROM hosting_accounts WHERE id=?", [$hostingAccountId]);
        $storageBytes = 0;
        if ($row && is_dir($row['root_path'])) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($row['root_path'], \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) if ($f->isFile()) $storageBytes += $f->getSize();
        }
        // Bandwidth mock: random or based on usage_records
        $bandwidthBytes = random_int(0, 100*1024*1024);
        $dbCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM customer_databases WHERE hosting_account_id=?", [$hostingAccountId]);
        $domainCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM domains WHERE hosting_account_id=?", [$hostingAccountId]) + (int)$this->db->fetchColumn("SELECT COUNT(*) FROM subdomains WHERE hosting_account_id=?", [$hostingAccountId]);

        return [
            'storage_bytes'=>$storageBytes,
            'bandwidth_bytes'=>$bandwidthBytes,
            'database_count'=>$dbCount,
            'domain_count'=>$domainCount,
        ];
    }

    public function aggregate(int $hostingAccountId, string $period): array
    {
        // For Phase 7, return mock daily/monthly aggregates from usage_records
        $rows = $this->db->fetchAll("SELECT * FROM usage_records WHERE hosting_account_id=? ORDER BY recorded_at DESC LIMIT 10", [$hostingAccountId]);
        return ['period'=>$period,'records'=>$rows];
    }
}
