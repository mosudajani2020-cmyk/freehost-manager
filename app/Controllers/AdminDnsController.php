<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\DnsRecordRepository;
use App\Services\DnsService;
use App\Services\Providers\ProviderFactory;
use App\Repositories\HostingAccountRepository;
use App\Services\AuditService;

final class AdminDnsController
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
            $rows = $db->fetchAll("SELECT * FROM dns_records WHERE hostname LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?", [$like,$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM dns_records WHERE hostname LIKE ?", [$like]);
        } else {
            $rows = $db->fetchAll("SELECT * FROM dns_records ORDER BY id DESC LIMIT ? OFFSET ?", [$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM dns_records");
        }
        View::render('admin.dns.index', ['title'=>'DNS Records','rows'=>$rows,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM dns_records WHERE id=?", [$id]);
        if (!$row) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        $account = $db->fetch("SELECT * FROM hosting_accounts WHERE id=?", [$row['hosting_account_id']]);
        View::render('admin.dns.show', ['title'=>'DNS ' . $row['hostname'],'row'=>$row,'account'=>$account]);
    }

    public function updateStatus(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $db = Database::getInstance();
        $service = new DnsService($db, new HostingAccountRepository($db), new DnsRecordRepository($db), ProviderFactory::dns(), new AuditService($db));
        try {
            // Admin: allow any status, bypass ownership check via admin role (service will check admin via session)
            $service->updateStatus((int)($_SESSION['user_id'] ?? 0), $db->fetchColumn("SELECT hosting_account_id FROM dns_records WHERE id=?", [$id]) ?: 0, $id, $status);
            $_SESSION['_flash'] = ['success'=>'DNS status updated to ' . $status];
        } catch (\Throwable $e) {
            // Fallback direct update for admin without ownership
            try {
                $db->execute("UPDATE dns_records SET status=? WHERE id=?", [$status, $id]);
                $db->execute("UPDATE subdomains SET dns_status=? WHERE full_domain=(SELECT hostname FROM dns_records WHERE id=?)", [$status, $id]);
                $_SESSION['_flash'] = ['success'=>'Status updated (admin override)'];
            } catch (\Throwable $e2) {
                $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            }
        }
        header('Location: /admin/dns/' . $id, true, 302);
        exit;
    }
}
