<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PasswordResetController;
use App\Controllers\VerificationController;
use App\Controllers\HostingController;
use App\Controllers\PlanController;
use App\Controllers\AdminHostingController;
use App\Controllers\FileController;
use App\Controllers\DatabaseController;
use App\Controllers\AdminDatabaseController;
use App\Controllers\AdminNodeController;
use App\Controllers\AdminProvisioningController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RbacMiddleware;

// Public
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
$router->get('/verify', [VerificationController::class, 'verify']);

// Authenticated
$router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// Customer Hosting
$router->get('/hosting', [HostingController::class, 'index'], [AuthMiddleware::class]);
$router->get('/hosting/create', [HostingController::class, 'create'], [AuthMiddleware::class]);
$router->post('/hosting', [HostingController::class, 'store'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/hosting/{id}', [HostingController::class, 'show'], [AuthMiddleware::class]);
$router->post('/hosting/{id}/subdomain', [HostingController::class, 'createSubdomain'], [CsrfMiddleware::class, AuthMiddleware::class]);

// Admin
$router->get('/admin/dashboard', [DashboardController::class, 'admin'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);

// Admin Plans — plans.manage
$router->get('/admin/plans', [PlanController::class, 'index'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/plans/create', [PlanController::class, 'create'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/plans', [PlanController::class, 'store'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/plans/{id}/edit', [PlanController::class, 'edit'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/plans/{id}', [PlanController::class, 'update'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);

// Admin Hosting Management
$router->get('/admin/hosting', [AdminHostingController::class, 'index'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/hosting/{id}', [AdminHostingController::class, 'show'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/hosting/{id}/suspend', [AdminHostingController::class, 'suspend'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/hosting/{id}/activate', [AdminHostingController::class, 'activate'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/hosting/{id}/terminate', [AdminHostingController::class, 'terminate'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);

// File Manager — customer only, ownership enforced in service
$router->get('/hosting/{id}/files', [FileController::class, 'index'], [AuthMiddleware::class]);
$router->post('/hosting/{id}/files/mkdir', [FileController::class, 'mkdir'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->post('/hosting/{id}/files/upload', [FileController::class, 'upload'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/hosting/{id}/files/download', [FileController::class, 'download'], [AuthMiddleware::class]);
$router->post('/hosting/{id}/files/delete', [FileController::class, 'delete'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->post('/hosting/{id}/files/rename', [FileController::class, 'rename'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/hosting/{id}/files/create', [FileController::class, 'createForm'], [AuthMiddleware::class]);
$router->post('/hosting/{id}/files/create', [FileController::class, 'create'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/hosting/{id}/files/edit', [FileController::class, 'edit'], [AuthMiddleware::class]);
$router->post('/hosting/{id}/files/edit', [FileController::class, 'saveEdit'], [CsrfMiddleware::class, AuthMiddleware::class]);

// Database Hosting — customer
$router->get('/hosting/{id}/databases', [DatabaseController::class, 'index'], [AuthMiddleware::class]);
$router->post('/hosting/{id}/databases', [DatabaseController::class, 'create'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->post('/hosting/{id}/databases/delete', [DatabaseController::class, 'delete'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/hosting/{id}/databases/{dbId}', [DatabaseController::class, 'show'], [AuthMiddleware::class]);
$router->post('/hosting/{id}/databases/{dbId}/users', [DatabaseController::class, 'createUser'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->post('/hosting/{id}/databases/users/delete', [DatabaseController::class, 'deleteUser'], [CsrfMiddleware::class, AuthMiddleware::class]);

// Admin Database Hosting
$router->get('/admin/databases', [AdminDatabaseController::class, 'index'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/databases/{id}', [AdminDatabaseController::class, 'show'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);

// Admin Nodes & Provisioning (Phase 5)
$router->get('/admin/nodes', [AdminNodeController::class, 'index'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/nodes/create', [AdminNodeController::class, 'create'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/nodes', [AdminNodeController::class, 'store'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/nodes/{id}', [AdminNodeController::class, 'show'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/provisioning', [AdminProvisioningController::class, 'index'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->get('/admin/provisioning/{id}', [AdminProvisioningController::class, 'show'], [AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/provisioning/{id}/retry', [AdminProvisioningController::class, 'retry'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
$router->post('/admin/provisioning/{id}/fail', [AdminProvisioningController::class, 'fail'], [CsrfMiddleware::class, AuthMiddleware::class, new RbacMiddleware(roles: 'admin')]);
