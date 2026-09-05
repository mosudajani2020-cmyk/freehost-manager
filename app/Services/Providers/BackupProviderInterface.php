<?php

declare(strict_types=1);

namespace App\Services\Providers;

interface BackupProviderInterface
{
    public function createBackup(int $hostingAccountId, string $type, int $retentionDays): array; // ['success'=>bool,'size_bytes'=>int,'file_path'=>string]
    public function getStatus(int $backupId): array;
    public function deleteBackup(int $backupId): array;
}
