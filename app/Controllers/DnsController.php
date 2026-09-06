<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\DnsRecordRepository;
use App\Services\AuditService;
use App\Services\DnsService;
use App\Services\Providers\ProviderFactory;

final class DnsController
{
    private function service(): DnsService
    {
        $db = Database::getInstance();
        return new DnsService($db, new HostingAccountRepository($db), new DnsRecordRepository($db), ProviderFactory::dns(), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $data = $this->service()->list($userId, $id);
            View::render('dns.index', array_merge($data, ['title'=>'DNS — ' . $data['account']->username, 'accountId'=>$id]));
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 403);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id, true, 302);
            exit;
        }
    }

    public function create(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $hostname = trim($_POST['hostname'] ?? '');
        $type = trim($_POST['type'] ?? 'A');
        $value = trim($_POST['value'] ?? '127.0.0.1');
        $ttl = (int)($_POST['ttl'] ?? 3600);
        try {
            $res = $this->service()->create($userId, $id, $hostname, $type, $value, $ttl);
            $_SESSION['_flash'] = ['success'=>$res['message'] . ': ' . $hostname];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/dns', true, 302);
        exit;
    }

    public function delete(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $recordId = (int)($_POST['record_id'] ?? 0);
        try {
            $res = $this->service()->remove($userId, $id, $recordId);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/dns', true, 302);
        exit;
    }
}
