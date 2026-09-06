<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\SslCertificateRepository;
use App\Services\AuditService;
use App\Services\SslService;
use App\Services\Providers\ProviderFactory;

final class SslController
{
    private function service(): SslService
    {
        $db = Database::getInstance();
        return new SslService($db, new HostingAccountRepository($db), new SslCertificateRepository($db), ProviderFactory::ssl(), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $data = $this->service()->list($userId, $id);
            View::render('ssl.index', array_merge($data, ['title'=>'SSL — ' . $data['account']->username, 'accountId'=>$id]));
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 403);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id, true, 302);
            exit;
        }
    }

    public function request(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $hostname = trim($_POST['hostname'] ?? '');
        try {
            $res = $this->service()->request($userId, $id, $hostname);
            $_SESSION['_flash'] = ['success'=>$res['message'] . ': ' . $hostname];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/ssl', true, 302);
        exit;
    }

    public function renew(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $certId = (int)($_POST['cert_id'] ?? 0);
        try {
            $res = $this->service()->renew($userId, $id, $certId);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/ssl', true, 302);
        exit;
    }

    public function revoke(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $certId = (int)($_POST['cert_id'] ?? 0);
        try {
            $res = $this->service()->revoke($userId, $id, $certId);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/ssl', true, 302);
        exit;
    }
}
