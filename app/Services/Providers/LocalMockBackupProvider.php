<?php

declare(strict_types=1);

namespace App\Services\Providers;

final class LocalMockBackupProvider implements BackupProviderInterface
{
    public function createBackup(int $hostingAccountId, string $type, int $retentionDays): array
    {
        // Mock: simulate backup creation without touching filesystem destructively
        // Generate fake size and path (metadata only)
        $size = random_int(1024*100, 1024*1024*5); // 100KB - 5MB
        $path = "storage/backups/hosting_{$hostingAccountId}/" . date('Ymd_His') . "_{$type}.tar.gz";
        // Do not actually create file (to avoid retention issues), just log
        error_log("[MockBackup] create hosting {$hostingAccountId} type {$type} size {$size}");
        // Simulate failure 10% for testing? For now always success; failure handled via service if needed
        return ['success'=>true,'size_bytes'=>$size,'file_path'=>$path];
    }

    public function getStatus(int $backupId): array
    {
        return ['status'=>'completed'];
    }

    public function deleteBackup(int $backupId): array
    {
        error_log("[MockBackup] delete {$backupId}");
        return ['success'=>true,'message'=>'Deleted (mock)'];
    }
}
