<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\SslCertificateRepository;
use App\Repositories\HostingAccountRepository;
use App\Services\SslService;
use App\Services\Providers\ProviderFactory;
use App\Services\AuditService;

final class AdminSslController
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
            $rows = $db->fetchAll("SELECT * FROM ssl_certificates WHERE hostname LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?", [$like,$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM ssl_certificates WHERE hostname LIKE ?", [$like]);
        } else {
            $rows = $db->fetchAll("SELECT * FROM ssl_certificates ORDER BY id DESC LIMIT ? OFFSET ?", [$per,$off]);
            $total = (int)$db->fetchColumn("SELECT COUNT(*) FROM ssl_certificates");
        }
        View::render('admin.ssl.index', ['title'=>'SSL Certificates','rows'=>$rows,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM ssl_certificates WHERE id=?", [$id]);
        if (!$row) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        $account = $db->fetch("SELECT * FROM hosting_accounts WHERE id=?", [$row['hosting_account_id']]);
        View::render('admin.ssl.show', ['title'=>'SSL ' . $row['hostname'],'row'=>$row,'account'=>$account]);
    }

    public function updateStatus(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $db = Database::getInstance();
        $service = new SslService($db, new HostingAccountRepository($db), new SslCertificateRepository($db), ProviderFactory::ssl(), new AuditService($db));
        try {
            $service->updateStatus((int)($_SESSION['user_id'] ?? 0), $id, $status);
            $_SESSION['_flash'] = ['success'=>'SSL status updated'];
        } catch (\Throwable $e) {
            try {
                $db->execute("UPDATE ssl_certificates SET status=? WHERE id=?", [$status, $id]);
                $cert = $db->fetch("SELECT hostname FROM ssl_certificates WHERE id=?", [$id]);
                if ($cert) $db->execute("UPDATE subdomains SET ssl_status=? WHERE full_domain=?", [$status, $cert['hostname']]);
                $_SESSION['_flash'] = ['success'=>'Status updated (admin)'];
            } catch (\Throwable $e2) {
                $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            }
        }
        header('Location: /admin/ssl/' . $id, true, 302);
        exit;
    }
}
