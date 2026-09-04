<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\AuditService;
use App\Services\FileService;

final class FileController
{
    private function fileService(): FileService
    {
        $db = Database::getInstance();
        return new FileService($db, new HostingAccountRepository($db), new HostingPlanRepository($db), new AuditService($db));
    }

    public function index(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_GET['path'] ?? '';
        // Normalize path: must not contain null bytes etc. FileService will validate via PathGuard
        try {
            $data = $this->fileService()->list($userId, $id, $path);
            View::render('files.index', array_merge($data, ['title'=>'Files — ' . $data['account']->username, 'accountId'=>$id]));
        } catch (\Throwable $e) {
            $code = $e->getCode() === 403 ? 403 : ($e->getCode() === 404 ? 404 : 400);
            http_response_code($code);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            // If 403, show error page; if 404, redirect to root
            if ($code === 403) {
                View::render('errors.403', ['title'=>'Forbidden']);
                return;
            }
            if ($code === 404) {
                header('Location: /hosting/' . $id . '/files', true, 302);
                exit;
            }
            // Otherwise show list at root with error
            try {
                $data = $this->fileService()->list($userId, $id, '');
                View::render('files.index', array_merge($data, ['title'=>'Files','accountId'=>$id]));
            } catch (\Throwable $e2) {
                View::render('errors.500', ['title'=>'Error']);
            }
        }
    }

    public function mkdir(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_POST['path'] ?? '';
        $name = trim($_POST['dirname'] ?? '');
        try {
            $this->fileService()->mkdir($userId, $id, $path, $name);
            $_SESSION['_flash'] = ['success'=>'Directory created: ' . e($name)];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        $q = $path !== '' ? '?path=' . urlencode($path) : '';
        header('Location: /hosting/' . $id . '/files' . $q, true, 302);
        exit;
    }

    public function upload(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_POST['path'] ?? '';

        if (!isset($_FILES['file'])) {
            $_SESSION['_flash'] = ['error'=>'No file uploaded'];
            header('Location: /hosting/' . $id . '/files' . ($path ? '?path=' . urlencode($path) : ''), true, 302);
            exit;
        }

        try {
            $res = $this->fileService()->upload($userId, $id, $path, $_FILES['file']);
            $_SESSION['_flash'] = ['success'=>$res['message']];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        header('Location: /hosting/' . $id . '/files' . ($path ? '?path=' . urlencode($path) : ''), true, 302);
        exit;
    }

    public function download(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_GET['path'] ?? '';

        if ($path === '') {
            http_response_code(400);
            echo 'Missing path';
            exit;
        }

        try {
            $info = $this->fileService()->download($userId, $id, $path);
            $abs = $info['absolute'];
            $filename = $info['filename'];
            $mime = mime_content_type($abs) ?: 'application/octet-stream';
            // Force download for safety, not inline for php
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . str_replace('"','', $filename) . '"');
            header('Content-Length: ' . $info['size']);
            header('X-Content-Type-Options: nosniff');
            readfile($abs);
            exit;
        } catch (\Throwable $e) {
            http_response_code($e->getCode() ?: 400);
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id . '/files', true, 302);
            exit;
        }
    }

    public function delete(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_POST['path'] ?? '';
        $current = $_POST['current_path'] ?? '';

        try {
            $this->fileService()->delete($userId, $id, $path);
            $_SESSION['_flash'] = ['success'=>'Deleted: ' . e(basename($path))];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        $q = $current !== '' ? '?path=' . urlencode($current) : '';
        header('Location: /hosting/' . $id . '/files' . $q, true, 302);
        exit;
    }

    public function rename(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_POST['path'] ?? '';
        $newName = trim($_POST['new_name'] ?? '');
        $current = $_POST['current_path'] ?? '';

        try {
            $this->fileService()->rename($userId, $id, $path, $newName);
            $_SESSION['_flash'] = ['success'=>'Renamed to ' . e($newName)];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
        }
        $q = $current !== '' ? '?path=' . urlencode($current) : '';
        header('Location: /hosting/' . $id . '/files' . $q, true, 302);
        exit;
    }

    public function createForm(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_GET['path'] ?? '';
        // Verify account active
        try {
            $fs = $this->fileService();
            $info = $fs->list($userId, $id, $path);
            View::render('files.create', ['title'=>'Create File','accountId'=>$id,'currentPath'=>$path,'breadcrumbs'=>$info['breadcrumbs']]);
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            header('Location: /hosting/' . $id . '/files', true, 302);
            exit;
        }
    }

    public function create(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_POST['path'] ?? '';
        $filename = trim($_POST['filename'] ?? '');
        $content = $_POST['content'] ?? '';

        try {
            $this->fileService()->createTextFile($userId, $id, $path, $filename, $content);
            $_SESSION['_flash'] = ['success'=>'File created: ' . e($filename)];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            View::render('files.create', ['title'=>'Create File','accountId'=>$id,'currentPath'=>$path,'breadcrumbs'=>[['name'=>'/','path'=>'']],'error'=>$e->getMessage(),'old'=>['filename'=>$filename,'content'=>$content]]);
            return;
        }
        header('Location: /hosting/' . $id . '/files' . ($path ? '?path=' . urlencode($path) : ''), true, 302);
        exit;
    }

    public function edit(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_GET['path'] ?? '';

        if ($path === '') {
            $_SESSION['_flash'] = ['error'=>'Missing path'];
            header('Location: /hosting/' . $id . '/files', true, 302);
            exit;
        }

        try {
            $data = $this->fileService()->readText($userId, $id, $path);
            $breadcrumbs = $this->fileService()->list($userId, $id, dirname($path) === '.' ? '' : dirname($path))['breadcrumbs'];
            View::render('files.edit', ['title'=>'Edit ' . basename($path),'accountId'=>$id,'path'=>$path,'content'=>$data['content'],'breadcrumbs'=>$breadcrumbs]);
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            $dir = dirname($path);
            $q = ($dir && $dir !== '.' ) ? '?path=' . urlencode($dir) : '';
            header('Location: /hosting/' . $id . '/files' . $q, true, 302);
            exit;
        }
    }

    public function saveEdit(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $path = $_POST['path'] ?? '';
        $content = $_POST['content'] ?? '';

        try {
            $this->fileService()->writeText($userId, $id, $path, $content);
            $_SESSION['_flash'] = ['success'=>'File saved: ' . e(basename($path))];
        } catch (\Throwable $e) {
            $_SESSION['_flash'] = ['error'=>$e->getMessage()];
            // Re-render editor with old content
            try {
                $breadcrumbs = $this->fileService()->list($userId, $id, dirname($path) === '.' ? '' : dirname($path))['breadcrumbs'];
                View::render('files.edit', ['title'=>'Edit ' . basename($path),'accountId'=>$id,'path'=>$path,'content'=>$content,'breadcrumbs'=>$breadcrumbs,'error'=>$e->getMessage()]);
                return;
            } catch (\Throwable $e2) {
                header('Location: /hosting/' . $id . '/files', true, 302);
                exit;
            }
        }
        $dir = dirname($path);
        $q = ($dir && $dir !== '.' && $dir !== '/' ) ? '?path=' . urlencode($dir) : '';
        header('Location: /hosting/' . $id . '/files' . $q, true, 302);
        exit;
    }
}
