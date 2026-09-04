<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Services\AuditService;
use App\Services\EmailVerificationService;

final class VerificationController
{
    public function verify(array $params = []): void
    {
        $token = $_GET['token'] ?? '';
        if ($token === '') {
            $_SESSION['_flash'] = ['error' => 'Invalid verification link.'];
            header('Location: /login', true, 302);
            exit;
        }
        $db = Database::getInstance();
        $audit = new AuditService($db);
        $service = new EmailVerificationService($db, $audit);
        $ok = $service->verify($token);
        if ($ok) {
            $_SESSION['_flash'] = ['success' => 'Email verified. You may now login.'];
            header('Location: /login', true, 302);
            exit;
        }
        $_SESSION['_flash'] = ['error' => 'Invalid or expired verification token.'];
        header('Location: /login', true, 302);
        exit;
    }

    public function showVerifyInfo(array $params = []): void
    {
        View::render('auth.verify', ['title' => 'Verify Email']);
    }

    public function resend(array $params = []): void
    {
        $email = trim($_POST['email'] ?? '');
        if ($email === '') {
            $_SESSION['_flash'] = ['error' => 'Email is required.'];
            header('Location: /verify-email', true, 302);
            exit;
        }
        $db = Database::getInstance();
        $row = $db->fetch("SELECT id FROM users WHERE email = ? LIMIT 1", [strtolower($email)]);
        if ($row === null) {
            // Generic
            $_SESSION['_flash'] = ['success' => 'If an account exists, verification email has been resent. (Dev: mail.log)'];
            header('Location: /verify-email', true, 302);
            exit;
        }
        $audit = new AuditService($db);
        $service = new EmailVerificationService($db, $audit);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $service->resend((int) $row['id'], $ip);
        $_SESSION['_flash'] = ['success' => 'If an account exists, verification email has been resent. (Dev: mail.log)'];
        header('Location: /verify-email', true, 302);
        exit;
    }
}
