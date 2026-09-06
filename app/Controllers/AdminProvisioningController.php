<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\ProvisioningJobRepository;
use App\Services\AuditService;
use App\Services\ProvisioningService;
use App\Repositories\HostingNodeRepository;
use App\Services\Providers\ProviderFactory;

final class AdminProvisioningController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $repo = new ProvisioningJobRepository($db);
        $status = trim($_GET['status'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $off = ($page-1)*$per;
        $jobs = $repo->all($per, $off, $status);
        $total = $repo->count($status ?: null);
        View::render('admin.provisioning.index', ['title'=>'Provisioning Jobs','jobs'=>$jobs,'status'=>$status,'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'total'=>$total]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $repo = new ProvisioningJobRepository($db);
        $job = $repo->findById($id);
        if (!$job) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        $account = $db->fetch("SELECT * FROM hosting_accounts WHERE id=?", [$job->hostingAccountId]);
        $node = $job->nodeId ? $db->fetch("SELECT * FROM hosting_nodes WHERE id=?", [$job->nodeId]) : null;
        View::render('admin.provisioning.show', ['title'=>'Job ' . $job->jobUuid,'job'=>$job,'account'=>$account,'node'=>$node]);
    }

    public function retry(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $service = new ProvisioningService($db, new HostingNodeRepository($db), new ProvisioningJobRepository($db), ProviderFactory::provisioner(), new AuditService($db));
        try {
            $job = $service->retryJob($id, (int)($_SESSION['user_id'] ?? 0));
            $_SESSION['_flash'] = ['success'=>'Job retried, status: ' . $job->status];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /admin/provisioning/' . $id, true, 302);
        exit;
    }

    public function fail(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Manual fail');
        $db = Database::getInstance();
        $service = new ProvisioningService($db, new HostingNodeRepository($db), new ProvisioningJobRepository($db), ProviderFactory::provisioner(), new AuditService($db));
        $service->failJob($id, $reason, (int)($_SESSION['user_id'] ?? 0));
        $_SESSION['_flash'] = ['success'=>'Job marked failed'];
        header('Location: /admin/provisioning/' . $id, true, 302);
        exit;
    }
}
