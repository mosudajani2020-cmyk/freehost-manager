<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Repositories\SubdomainRepository;
use App\Services\AuditService;
use App\Services\HostingService;
use App\Services\Providers\ProviderFactory;
use App\Validators\HostingAccountValidator;

final class HostingController
{
    private function service(): HostingService
    {
        $db = Database::getInstance();
        return new HostingService(
            $db,
            new HostingAccountRepository($db),
            new HostingPlanRepository($db),
            new SubdomainRepository($db),
            ProviderFactory::provisioner(),
            new AuditService($db)
        );
    }

    public function index(array $params = []): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $db = Database::getInstance();
        $repo = new HostingAccountRepository($db);
        $accounts = $repo->findByUser($userId);
        $plans = (new HostingPlanRepository($db))->active();
        View::render('hosting.index', ['title'=>'My Hosting','accounts'=>$accounts,'plans'=>$plans]);
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $db = Database::getInstance();
        $repo = new HostingAccountRepository($db);
        $account = $repo->findById($id);
        if (!$account) {
            http_response_code(404);
            View::render('errors.404', ['title'=>'Not found']);
            return;
        }
        // Ownership check — IDOR protection
        if ($account->userId !== $userId && !in_array('admin', $_SESSION['user_roles'] ?? [], true)) {
            http_response_code(403);
            View::render('errors.403', ['title'=>'Forbidden']);
            return;
        }
        $subdomains = (new SubdomainRepository($db))->findByHosting($account->id);
        $mainDomain = $this->service()->getMainDomain();
        View::render('hosting.show', ['title'=>'Hosting Account','account'=>$account,'subdomains'=>$subdomains,'mainDomain'=>$mainDomain]);
    }

    public function create(array $params = []): void
    {
        $db = Database::getInstance();
        $plans = (new HostingPlanRepository($db))->active();
        View::render('hosting.create', ['title'=>'Create Hosting','plans'=>$plans,'errors'=>[]]);
    }

    public function store(array $params = []): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $data = [
            'username' => strtolower(trim($_POST['username'] ?? '')),
            'plan_id' => $_POST['plan_id'] ?? '',
        ];
        $errors = HostingAccountValidator::validateCreate($data);
        if ($errors) {
            $db = Database::getInstance();
            $plans = (new HostingPlanRepository($db))->active();
            View::render('hosting.create', ['title'=>'Create Hosting','plans'=>$plans,'errors'=>$errors,'old'=>$data]);
            return;
        }

        $result = $this->service()->createAccount($userId, (int)$data['plan_id'], $data['username']);
        if (!$result['success']) {
            $db = Database::getInstance();
            $plans = (new HostingPlanRepository($db))->active();
            View::render('hosting.create', ['title'=>'Create Hosting','plans'=>$plans,'errors'=>['username'=>$result['message']],'old'=>$data]);
            return;
        }

        $_SESSION['_flash'] = ['success'=>$result['message'] . ' — ID ' . $result['account']->id];
        header('Location: /hosting/' . $result['account']->id, true, 302);
        exit;
    }

    public function createSubdomain(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $sub = trim($_POST['subdomain'] ?? '');

        $errors = HostingAccountValidator::validateSubdomain($sub);
        if ($errors) {
            $_SESSION['_flash'] = ['error'=> $errors['subdomain'] ?? 'Invalid subdomain'];
            header('Location: /hosting/' . $id, true, 302);
            exit;
        }

        $result = $this->service()->createSubdomain($userId, $id, $sub);
        if (!$result['success']) {
            $_SESSION['_flash'] = ['error'=> $result['message']];
        } else {
            $_SESSION['_flash'] = ['success'=> $result['message'] . ': ' . $result['data']['fullDomain']];
        }
        header('Location: /hosting/' . $id, true, 302);
        exit;
    }
}
