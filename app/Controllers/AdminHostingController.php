<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\SubdomainRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\AuditService;
use App\Services\HostingService;
use App\Services\Provisioning\LocalMockProvisioner;

final class AdminHostingController
{
    private function service(): HostingService
    {
        $db = Database::getInstance();
        return new HostingService(
            $db,
            new HostingAccountRepository($db),
            new HostingPlanRepository($db),
            new SubdomainRepository($db),
            new LocalMockProvisioner(),
            new AuditService($db)
        );
    }

    public function index(array $params = []): void
    {
        $q = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = 20;
        $offset = ($page-1)*$per;

        $db = Database::getInstance();
        $repo = new HostingAccountRepository($db);
        $accounts = $repo->findAll($per, $offset, $q);
        $total = $repo->count(null, $q);
        $pages = max(1, (int)ceil($total/$per));

        // Resolve owners via the existing Database helper (singleton, no direct construction)
        $owners = [];
        if (!empty($accounts)) {
            $ids = array_map(fn($a) => (int) $a->userId, $accounts);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->fetchAll("SELECT id, email, username FROM users WHERE id IN ($placeholders)", $ids);
            foreach ($rows as $r) {
                $owners[(int) $r['id']] = $r;
            }
        }

        View::render('admin.hosting.index', [
            'title' => 'All Hosting Accounts',
            'accounts' => $accounts,
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'owners' => $owners,
        ]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $repo = new HostingAccountRepository($db);
        $account = $repo->findById($id);
        if (!$account) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        $subdomains = (new SubdomainRepository($db))->findByHosting($id);
        $owner = $db->fetch("SELECT * FROM users WHERE id=?", [$account->userId]);
        View::render('admin.hosting.show', ['title'=>'Hosting Details','account'=>$account,'subdomains'=>$subdomains,'owner'=>$owner]);
    }

    public function suspend(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $actor = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->service()->suspendAccount($actor, $id, true);
        $_SESSION['_flash'] = $result['success'] ? ['success'=>$result['message']] : ['error'=>$result['message']];
        header('Location: /admin/hosting/' . $id, true, 302);
        exit;
    }

    public function activate(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $actor = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->service()->activateAccount($actor, $id, true);
        $_SESSION['_flash'] = $result['success'] ? ['success'=>$result['message']] : ['error'=>$result['message']];
        header('Location: /admin/hosting/' . $id, true, 302);
        exit;
    }

    public function terminate(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $actor = (int)($_SESSION['user_id'] ?? 0);
        $result = $this->service()->terminateAccount($actor, $id, true);
        $_SESSION['_flash'] = $result['success'] ? ['success'=>$result['message']] : ['error'=>$result['message']];
        header('Location: /admin/hosting/' . $id, true, 302);
        exit;
    }
}
