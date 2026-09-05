<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;

final class AdminAuditController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 30;
        $off = ($page - 1) * $per;
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $rows = $db->fetchAll("SELECT * FROM audit_logs WHERE action LIKE ? OR resource_type LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?", [$like,$like,$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE action LIKE ? OR resource_type LIKE ?", [$like,$like]);
        } else {
            $rows = $db->fetchAll("SELECT * FROM audit_logs ORDER BY id DESC LIMIT ? OFFSET ?", [$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM audit_logs");
        }
        View::render('admin.audit.index', ['title'=>'Audit Logs','rows'=>$rows,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }
}
