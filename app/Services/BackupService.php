<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\HostingAccountRepository;
use App\Repositories\BackupRepository;
use App\Services\Providers\BackupProviderInterface;

final class BackupService
{
    public function __construct(
        private readonly Database $db,
        private readonly HostingAccountRepository $accounts,
        private readonly BackupRepository $backups,
        private readonly BackupProviderInterface $provider,
        private readonly AuditService $audit,
    ) {}

    private function requireActiveAccount(int $userId, int $accountId): \App\Models\HostingAccount
    {
        $acct = $this->accounts->findById($accountId);
        if (!$acct) throw new \RuntimeException('Hosting account not found', 404);
        if ($acct->userId !== $userId) {
            $this->audit->log($userId, 'backup.access_denied', 'hosting_account', (string)$accountId, 'failure', ['reason'=>'ownership']);
            throw new \RuntimeException('Access denied', 403);
        }
        if ($acct->status !== 'active') throw new \RuntimeException('Hosting account is ' . $acct->status, 403);
        return $acct;
    }

    public function list(int $userId, int $accountId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $list = $this->backups->findByHosting($accountId);
        return ['account'=>$acct,'backups'=>$list];
    }

    public function create(int $userId, int $accountId, string $type='full', int $retentionDays=7): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $type = $type === 'incremental' ? 'incremental' : 'full';
        if ($retentionDays < 1 || $retentionDays > 365) throw new \RuntimeException('Retention must be 1-365 days', 400);
        // Check not too many pending/running
        $pending = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM backups WHERE hosting_account_id=? AND status IN ('pending','running')", [$accountId]);
        if ($pending >= 2) throw new \RuntimeException('Too many pending backups, wait for completion', 400);

        $backup = $this->backups->create($accountId, $type, $retentionDays, $userId);
        // Mark running, then call provider (mock)
        $this->backups->updateStatus($backup->id, 'running');
        try {
            $res = $this->provider->createBackup($accountId, $type, $retentionDays);
            if (!$res['success']) throw new \RuntimeException($res['message'] ?? 'Provider failed');
            $this->backups->updateStatus($backup->id, 'completed', $res['size_bytes'] ?? null, $res['file_path'] ?? null);
            $this->audit->log($userId, 'backup.create', 'backup', (string)$backup->id, 'success', ['hosting'=>$accountId,'type'=>$type]);
            return ['success'=>true,'message'=>'Backup completed','backup'=>$this->backups->findById($backup->id)];
        } catch (\Throwable $e) {
            $this->backups->updateStatus($backup->id, 'failed', null, null, $e->getMessage());
            $this->audit->log($userId, 'backup.failed', 'backup', (string)$backup->id, 'failure', ['error'=>$e->getMessage()]);
            throw new \RuntimeException('Backup failed: ' . $e->getMessage(), 500);
        }
    }

    public function delete(int $userId, int $accountId, int $backupId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $backup = $this->backups->findById($backupId);
        if (!$backup) throw new \RuntimeException('Backup not found', 404);
        if ($backup->hostingAccountId !== $accountId) throw new \RuntimeException('Access denied', 403);
        if ($backup->status === 'running') throw new \RuntimeException('Cannot delete running backup', 400);

        // Provider delete (mock, no filesystem deletion)
        $this->provider->deleteBackup($backupId);
        $this->backups->delete($backupId);
        $this->audit->log($userId, 'backup.delete', 'backup', (string)$backupId, 'success', ['hosting'=>$accountId]);
        return ['success'=>true,'message'=>'Backup deleted (metadata)'];
    }

    public function expireOld(): int
    {
        return $this->backups->expireOld();
    }
}
