<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Services\AuditService;

final class AdminUserController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $off = ($page - 1) * $per;
        if ($q !== '') {
            $like = '%' . $q . '%';
            $rows = $db->fetchAll("SELECT * FROM users WHERE email LIKE ? OR username LIKE ? OR full_name LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?", [$like, $like, $like, $per, $off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE email LIKE ? OR username LIKE ? OR full_name LIKE ?", [$like, $like, $like]);
        } else {
            $rows = $db->fetchAll("SELECT * FROM users ORDER BY id DESC LIMIT ? OFFSET ?", [$per, $off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM users");
        }
        View::render('admin.users.index', ['title'=>'User Management','rows'=>$rows,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE id=?", [$id]);
        if (!$user) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        $roles = $db->fetchAll("SELECT r.* FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?", [$id]);
        $hosting = $db->fetchAll("SELECT * FROM hosting_accounts WHERE user_id=?", [$id]);
        $audit = $db->fetchAll("SELECT * FROM audit_logs WHERE user_id=? ORDER BY id DESC LIMIT 10", [$id]);
        View::render('admin.users.show', ['title'=>'User ' . $user['username'],'user'=>$user,'roles'=>$roles,'hosting'=>$hosting,'audit'=>$audit]);
    }

    public function updateStatus(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $valid = ['active','suspended','banned','pending'];
        if (!in_array($status, $valid, true)) {
            $_SESSION['_flash'] = ['error'=>'Invalid status'];
            header('Location: /admin/users/' . $id, true, 302);
            exit;
        }
        $db = Database::getInstance();
        $db->execute("UPDATE users SET status=? WHERE id=?", [$status, $id]);
        $audit = new AuditService($db);
        $audit->log((int)($_SESSION['user_id'] ?? 0), 'admin.user_status', 'user', (string)$id, 'success', ['status'=>$status]);
        $_SESSION['_flash'] = ['success'=>'User status updated to ' . $status];
        header('Location: /admin/users/' . $id, true, 302);
        exit;
    }
}
