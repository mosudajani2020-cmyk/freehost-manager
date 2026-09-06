<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\AuditService;
use App\Services\DatabaseService;
use App\Services\Providers\ProviderFactory;

final class DatabaseController
{
    private function service(): DatabaseService
    {
        $db = Database::getInstance();
        return new DatabaseService($db, new HostingAccountRepository($db), new HostingPlanRepository($db), ProviderFactory::provisioner(), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $data = $this->service()->listDatabases($userId, $id);
            View::render('databases.index', array_merge($data, ['title'=>'Databases','accountId'=>$id]));
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 403);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id, true, 302);
            exit;
        }
    }

    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $dbId = (int)($params['dbId'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        try {
            $db = $this->service()->getDatabase($userId, $id, $dbId);
            if (!$db) {
                http_response_code(404);
                View::render('errors.404', ['title'=>'Not found']);
                return;
            }
            View::render('databases.show', ['title'=>'Database ' . $db['name'],'accountId'=>$id,'db'=>$db]);
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 400);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id . '/databases', true, 302);
            exit;
        }
    }

    public function create(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        try {
            $res = $this->service()->createDatabase($userId, $id, $name);
            $_SESSION['_flash'] = ['success'=>$res['message'] . ': ' . $res['name']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/databases', true, 302);
        exit;
    }

    public function delete(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $dbId = (int)($_POST['db_id'] ?? 0);

        try {
            $res = $this->service()->deleteDatabase($userId, $id, $dbId);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/databases', true, 302);
        exit;
    }

    public function createUser(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $dbId = (int)($_POST['db_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');

        try {
            $res = $this->service()->createDatabaseUser($userId, $id, $dbId, $username);
            // Show password once via flash (not logs)
            $_SESSION['_flash'] = ['success'=>'User ' . $res['username'] . ' created. Password (show once): ' . $res['password'], 'password_once'=>$res['password']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/databases/' . $dbId, true, 302);
        exit;
    }

    public function deleteUser(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $userDbId = (int)($_POST['user_id'] ?? 0);
        $dbId = (int)($_POST['db_id'] ?? 0);

        try {
            $res = $this->service()->deleteDatabaseUser($userId, $id, $userDbId);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/databases/' . $dbId, true, 302);
        exit;
    }
}
