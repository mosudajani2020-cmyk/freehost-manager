<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\PasswordResetService;

final class PasswordResetController
{
    public function showForgot(array $params = []): void
    {
        View::render('auth.forgot', ['title' => 'Forgot Password']);
    }

    public function forgot(array $params = []): void
    {
        $email = trim($_POST['email'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $db = Database::getInstance();
        $audit = new AuditService($db);
        $service = new PasswordResetService($db, $audit);
        $service->request($email, $ip);
        // Generic response
        $_SESSION['_flash'] = ['success' => 'If an account exists with that email, a reset link has been sent. (Dev: see storage/logs/mail.log)'];
        header('Location: /forgot-password', true, 302);
        exit;
    }

    public function showReset(array $params = []): void
    {
        $token = $_GET['token'] ?? '';
        if ($token === '') {
            $_SESSION['_flash'] = ['error' => 'Invalid reset link.'];
            header('Location: /forgot-password', true, 302);
            exit;
        }
        View::render('auth.reset', ['title' => 'Reset Password', 'token' => $token]);
    }

    public function reset(array $params = []): void
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        $errors = [];
        if ($token === '') {
            $errors['token'] = 'Missing token.';
        }
        $minLen = (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8);
        if (strlen($password) < $minLen) {
            $errors['password'] = "Password must be at least {$minLen} characters.";
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain uppercase, lowercase and digit.';
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if ($errors) {
            $_SESSION['_flash'] = ['errors' => $errors, 'error' => 'Please correct errors.'];
            header('Location: /reset-password?token=' . urlencode($token), true, 302);
            exit;
        }

        $db = Database::getInstance();
        $audit = new AuditService($db);
        $users = new UserRepository($db);
        $auth = new AuthService($db, $users, $audit);
        $service = new PasswordResetService($db, $audit);

        $ok = $service->resetPassword($token, $password, $audit, $auth);
        if (!$ok) {
            $_SESSION['_flash'] = ['error' => 'Invalid or expired token.'];
            header('Location: /forgot-password', true, 302);
            exit;
        }

        $_SESSION['_flash'] = ['success' => 'Password reset successful. You may now login.'];
        header('Location: /login', true, 302);
        exit;
    }
}
