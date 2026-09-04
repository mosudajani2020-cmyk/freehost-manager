<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PasswordResetController;
use App\Controllers\VerificationController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

// Public routes
$router->get('/', function() {
    if (!empty($_SESSION['user_id'])) {
        $roles = $_SESSION['user_roles'] ?? [];
        $target = in_array('admin', $roles, true) ? '/admin/dashboard' : '/dashboard';
        header('Location: ' . $target, true, 302);
        exit;
    }
    header('Location: /login', true, 302);
    exit;
});

$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register'], [CsrfMiddleware::class]);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);

$router->get('/forgot-password', [PasswordResetController::class, 'showForgot']);
$router->post('/forgot-password', [PasswordResetController::class, 'forgot'], [CsrfMiddleware::class]);

$router->get('/reset-password', [PasswordResetController::class, 'showReset']);
$router->post('/reset-password', [PasswordResetController::class, 'reset'], [CsrfMiddleware::class]);

$router->get('/verify-email', [VerificationController::class, 'showVerifyInfo']);
$router->get('/verify-email/verify', [VerificationController::class, 'verify']);
$router->post('/verify-email/resend', [VerificationController::class, 'resend'], [CsrfMiddleware::class]);
// Also support token as query on /verify-email
$router->get('/verify', [VerificationController::class, 'verify']);

// Authenticated
$router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]); // allow GET for simplicity, but POST preferred

$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// Admin
$router->get('/admin/dashboard', [DashboardController::class, 'admin'], [AuthMiddleware::class, new \App\Middleware\RbacMiddleware(roles: 'admin')]);
