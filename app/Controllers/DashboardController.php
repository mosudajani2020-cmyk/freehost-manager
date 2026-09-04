<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;

final class DashboardController
{
    public function index(array $params = []): void
    {
        $db = Database::getInstance();
        $users = new UserRepository($db);
        $audit = new AuditService($db);
        $auth = new AuthService($db, $users, $audit);
        $user = $auth->currentUser();
        if ($user === null) {
            header('Location: /login', true, 302);
            exit;
        }

        // Gather stats + hosting details
        $hostingCount = 0;
        $dbCount = 0;
        $domainCount = 0;
        $hostingAccounts = [];
        try {
            $hostingCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts WHERE user_id = ?", [$user->id]);
            $dbCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM customer_databases WHERE hosting_account_id IN (SELECT id FROM hosting_accounts WHERE user_id = ?)", [$user->id]);
            $domainCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM domains WHERE hosting_account_id IN (SELECT id FROM hosting_accounts WHERE user_id = ?)", [$user->id]);
            // Fetch accounts with plan info
            $rows = $db->fetchAll("SELECT ha.*, hp.name as plan_name, hp.storage_limit_mb, hp.bandwidth_limit_mb, hp.database_limit, hp.domain_limit, hp.subdomain_limit FROM hosting_accounts ha JOIN hosting_plans hp ON hp.id=ha.plan_id WHERE ha.user_id=? ORDER BY ha.id DESC", [$user->id]);
            foreach ($rows as $r) {
                $subCount = (int) $db->fetchColumn("SELECT COUNT(*) FROM subdomains WHERE hosting_account_id=?", [$r['id']]);
                $r['subdomain_count'] = $subCount;
                $hostingAccounts[] = $r;
            }
        } catch (\Throwable) {}

        $notifications = [];
        try {
            $notifications = $db->fetchAll("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$user->id]);
        } catch (\Throwable) {}

        $auditLogs = [];
        try {
            $auditLogs = $db->fetchAll("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$user->id]);
        } catch (\Throwable) {}

        View::render('dashboard.customer', [
            'title' => 'Dashboard',
            'user' => $user,
            'hostingCount' => $hostingCount,
            'dbCount' => $dbCount,
            'domainCount' => $domainCount,
            'hostingAccounts' => $hostingAccounts,
            'notifications' => $notifications,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function admin(array $params = []): void
    {
        $db = Database::getInstance();
        $users = new UserRepository($db);
        $audit = new AuditService($db);
        $auth = new AuthService($db, $users, $audit);
        $user = $auth->currentUser();
        if ($user === null || !$user->isAdmin()) {
            http_response_code(403);
            View::render('errors.403', ['title' => 'Forbidden']);
            return;
        }

        $stats = [
            'total_users' => 0,
            'active_users' => 0,
            'suspended_users' => 0,
            'hosting_accounts' => 0,
            'active_hosting' => 0,
            'suspended_hosting' => 0,
            'databases' => 0,
        ];
        try {
            $stats['total_users'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM users");
            $stats['active_users'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE status = 'active'");
            $stats['suspended_users'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM users WHERE status = 'suspended'");
            $stats['hosting_accounts'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts");
            $stats['active_hosting'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts WHERE status = 'active'");
            $stats['suspended_hosting'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts WHERE status = 'suspended'");
            $stats['databases'] = (int) $db->fetchColumn("SELECT COUNT(*) FROM customer_databases");
        } catch (\Throwable) {}

        $recentUsers = [];
        $recentLogs = [];
        try {
            $recentUsers = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
            $recentLogs = $db->fetchAll("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10");
        } catch (\Throwable) {}

        View::render('dashboard.admin', [
            'title' => 'Admin Dashboard',
            'user' => $user,
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'recentLogs' => $recentLogs,
        ]);
    }
}
