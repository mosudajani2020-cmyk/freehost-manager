<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;

final class AdminDatabaseController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $off = ($page-1)*$per;

        if ($q !== '') {
            $like = '%' . $q . '%';
            $rows = $db->fetchAll("SELECT cd.*, ha.username as hosting_user, ha.user_id FROM customer_databases cd JOIN hosting_accounts ha ON ha.id=cd.hosting_account_id WHERE cd.name LIKE ? OR ha.username LIKE ? ORDER BY cd.id DESC LIMIT ? OFFSET ?", [$like,$like,$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM customer_databases cd JOIN hosting_accounts ha ON ha.id=cd.hosting_account_id WHERE cd.name LIKE ? OR ha.username LIKE ?", [$like,$like]);
        } else {
            $rows = $db->fetchAll("SELECT cd.*, ha.username as hosting_user, ha.user_id FROM customer_databases cd JOIN hosting_accounts ha ON ha.id=cd.hosting_account_id ORDER BY cd.id DESC LIMIT ? OFFSET ?", [$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM customer_databases");
        }
        // Attach users count
        foreach ($rows as &$r) {
            $r['user_count'] = (int)$db->fetchColumn("SELECT COUNT(*) FROM database_users WHERE customer_database_id=?", [$r['id']]);
        }

        View::render('admin.databases.index', ['title'=>'All Databases','rows'=>$rows,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $row = $db->fetch("SELECT cd.*, ha.username as hosting_user, ha.user_id, ha.status as hosting_status FROM customer_databases cd JOIN hosting_accounts ha ON ha.id=cd.hosting_account_id WHERE cd.id=?", [$id]);
        if (!$row) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        $users = $db->fetchAll("SELECT * FROM database_users WHERE customer_database_id=?", [$id]);
        $owner = $db->fetch("SELECT * FROM users WHERE id=?", [$row['user_id']]);
        View::render('admin.databases.show', ['title'=>'Database ' . $row['name'],'row'=>$row,'users'=>$users,'owner'=>$owner]);
    }
}
