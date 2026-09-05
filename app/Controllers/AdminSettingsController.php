<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Services\AuditService;

final class AdminSettingsController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT * FROM system_settings WHERE is_secret=0 ORDER BY `key` ASC");
        View::render('admin.settings.index', ['title'=>'System Settings','rows'=>$rows]);
    }

    public function update(array $params = []): void
    {
        $db = Database::getInstance();
        $audit = new AuditService($db);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        foreach ($_POST['settings'] ?? [] as $key => $value) {
            $key = trim($key);
            $value = trim((string)$value);
            // Only allow non-secret settings
            $row = $db->fetch("SELECT * FROM system_settings WHERE `key`=? AND is_secret=0", [$key]);
            if (!$row) continue;
            $db->execute("UPDATE system_settings SET value=?, updated_by=? WHERE `key`=?", [$value, $userId, $key]);
            $audit->log($userId, 'settings.update', 'system_settings', $key, 'success', ['key'=>$key]);
        }
        $_SESSION['_flash'] = ['success'=>'Settings updated'];
        header('Location: /admin/settings', true, 302);
        exit;
    }
}
