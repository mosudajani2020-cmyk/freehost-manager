<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\BackupRepository;
use App\Services\BackupService;
use App\Services\Providers\LocalMockBackupProvider;
use App\Repositories\HostingAccountRepository;
use App\Services\AuditService;

final class AdminBackupController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $off = ($page-1)*$per;
        $repo = new BackupRepository($db);
        if ($q !== '') {
            $like = '%' . $q . '%';
            $rows = $db->fetchAll("SELECT b.*, ha.username as hosting_user FROM backups b JOIN hosting_accounts ha ON ha.id=b.hosting_account_id WHERE b.file_path LIKE ? OR ha.username LIKE ? ORDER BY b.id DESC LIMIT ? OFFSET ?", [$like,$like,$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM backups b JOIN hosting_accounts ha ON ha.id=b.hosting_account_id WHERE b.file_path LIKE ? OR ha.username LIKE ?", [$like,$like]);
        } else {
            $rows = $db->fetchAll("SELECT b.*, ha.username as hosting_user FROM backups b JOIN hosting_accounts ha ON ha.id=b.hosting_account_id ORDER BY b.id DESC LIMIT ? OFFSET ?", [$per,$off]);
            $total = $repo->count();
        }
        View::render('admin.backups.index', ['title'=>'All Backups','rows'=>$rows,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $row = $db->fetch("SELECT b.*, ha.username as hosting_user, ha.status as hosting_status FROM backups b JOIN hosting_accounts ha ON ha.id=b.hosting_account_id WHERE b.id=?", [$id]);
        if (!$row) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        View::render('admin.backups.show', ['title'=>'Backup ' . $row['id'],'row'=>$row]);
    }

    public function expire(array $params = []): void
    {
        $db = Database::getInstance();
        $service = new BackupService($db, new HostingAccountRepository($db), new BackupRepository($db), new LocalMockBackupProvider(), new AuditService($db));
        $count = $service->expireOld();
        $_SESSION['_flash'] = ['success'=>"Expired $count old backups"];
        header('Location: /admin/backups', true, 302);
        exit;
    }
}
