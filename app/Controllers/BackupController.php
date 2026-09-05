<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\BackupRepository;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\Providers\LocalMockBackupProvider;

final class BackupController
{
    private function service(): BackupService
    {
        $db = Database::getInstance();
        return new BackupService($db, new HostingAccountRepository($db), new BackupRepository($db), new LocalMockBackupProvider(), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $data = $this->service()->list($userId, $id);
            View::render('backups.index', array_merge($data, ['title'=>'Backups — ' . $data['account']->username, 'accountId'=>$id]));
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 403);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id, true, 302);
            exit;
        }
    }

    public function create(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $type = trim($_POST['type'] ?? 'full');
        $retention = (int)($_POST['retention_days'] ?? 7);
        try {
            $res = $this->service()->create($userId, $id, $type, $retention);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/backups', true, 302);
        exit;
    }

    public function delete(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $backupId = (int)($_POST['backup_id'] ?? 0);
        try {
            $res = $this->service()->delete($userId, $id, $backupId);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/backups', true, 302);
        exit;
    }
}
