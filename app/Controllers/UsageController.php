<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\AuditService;
use App\Services\UsageService;
use App\Services\Providers\ProviderFactory;

final class UsageController
{
    private function service(): UsageService
    {
        $db = Database::getInstance();
        return new UsageService($db, new HostingAccountRepository($db), new HostingPlanRepository($db), ProviderFactory::usage(), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $data = $this->service()->getUsage($userId, $id);
            View::render('usage.index', array_merge($data, ['title'=>'Usage — ' . $data['account']->username, 'accountId'=>$id]));
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 403);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id, true, 302);
            exit;
        }
    }

    public function collect(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $this->service()->collectAndRecord($id, $userId);
            $_SESSION['_flash'] = ['success'=>'Usage collected'];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/usage', true, 302);
        exit;
    }
}
