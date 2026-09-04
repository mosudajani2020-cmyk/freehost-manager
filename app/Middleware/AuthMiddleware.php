<?php

declare(strict_types=1);

namespace App\Middleware;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            $_SESSION['_flash'] = ['error' => 'Please login to continue.'];
            // Save intended URL
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/dashboard';
            header('Location: /login', true, 302);
            exit;
        }
        // Anomaly detection: UA change warning (do not lock out)
        $currentUaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        if (isset($_SESSION['user_agent_hash']) && $_SESSION['user_agent_hash'] !== $currentUaHash) {
            // Log anomaly but allow — could indicate session hijack attempt
            error_log('[Auth] UA change for user ' . ($_SESSION['user_id'] ?? '?'));
            // Optionally rotate token? For now just log
        }
    }

    public static function guest(): void
    {
        if (!empty($_SESSION['user_id'])) {
            // Already authenticated — redirect to dashboard
            $roles = $_SESSION['user_roles'] ?? [];
            $target = in_array('admin', $roles, true) ? '/admin/dashboard' : '/dashboard';
            header('Location: ' . $target, true, 302);
            exit;
        }
    }
}
