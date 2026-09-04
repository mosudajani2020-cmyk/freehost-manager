<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\EmailVerificationService;
use App\Validators\RegistrationValidator;
use App\Validators\LoginValidator;
use App\Security\RateLimiter;

final class AuthController
{
    private Database $db;
    private UserRepository $users;
    private AuditService $audit;
    private AuthService $auth;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->users = new UserRepository($this->db);
        $this->audit = new AuditService($this->db);
        $this->auth = new AuthService($this->db, $this->users, $this->audit);
    }

    public function showRegister(array $params = []): void
    {
        \App\Middleware\AuthMiddleware::guest();
        View::render('auth.register', ['title' => 'Register']);
    }

    public function register(array $params = []): void
    {
        \App\Middleware\AuthMiddleware::guest();

        // Rate limit registration per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::attempt('register:ip:' . $ip, 3, 3600)) {
            $_SESSION['_flash'] = ['error' => 'Too many registration attempts. Try again later.'];
            header('Location: /register', true, 302);
            exit;
        }

        $data = [
            'full_name' => $_POST['full_name'] ?? '',
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ];

        $errors = RegistrationValidator::validate($data);
        if ($errors) {
            $_SESSION['_old'] = $data;
            $_SESSION['_flash'] = ['errors' => $errors, 'error' => 'Please correct the errors below.'];
            header('Location: /register', true, 302);
            exit;
        }

        // Check duplicates
        if ($this->users->existsByEmail($data['email'])) {
            $errors['email'] = 'Email already registered.';
        }
        if ($this->users->existsByUsername($data['username'])) {
            $errors['username'] = 'Username already taken.';
        }
        if ($errors) {
            $_SESSION['_old'] = $data;
            $_SESSION['_flash'] = ['errors' => $errors, 'error' => 'Registration failed.'];
            header('Location: /register', true, 302);
            exit;
        }

        // Check registration enabled
        $regEnabled = filter_var($_ENV['REGISTRATION_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (!$regEnabled) {
            $_SESSION['_flash'] = ['error' => 'Registration is currently disabled.'];
            header('Location: /register', true, 302);
            exit;
        }

        $hash = $this->auth->hashPassword($data['password']);
        $emailVerificationRequired = filter_var($_ENV['EMAIL_VERIFICATION_REQUIRED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $status = $emailVerificationRequired ? 'pending' : 'active';

        $user = $this->users->create([
            'full_name' => trim($data['full_name']),
            'username' => strtolower(trim($data['username'])),
            'email' => strtolower(trim($data['email'])),
            'password_hash' => $hash,
            'status' => $status,
        ]);

        $this->audit->log($user->id, 'registration', 'user', (string) $user->id, 'success');

        if ($emailVerificationRequired) {
            $verif = new EmailVerificationService($this->db, $this->audit);
            $verif->createToken($user->id);
            $_SESSION['_flash'] = ['success' => 'Registration successful. Please check your email to verify your account. (Dev: see storage/logs/mail.log)'];
            header('Location: /login', true, 302);
            exit;
        }

        // Auto-login inactive? No — require login
        $_SESSION['_flash'] = ['success' => 'Registration successful. You may now login.'];
        header('Location: /login', true, 302);
        exit;
    }

    public function showLogin(array $params = []): void
    {
        \App\Middleware\AuthMiddleware::guest();
        View::render('auth.login', ['title' => 'Login']);
    }

    public function login(array $params = []): void
    {
        \App\Middleware\AuthMiddleware::guest();

        $data = [
            'login' => $_POST['login'] ?? '',
            'password' => $_POST['password'] ?? '',
        ];

        $errors = LoginValidator::validate($data);
        if ($errors) {
            $_SESSION['_old'] = $data;
            $_SESSION['_flash'] = ['errors' => $errors, 'error' => 'Please fill required fields.'];
            header('Location: /login', true, 302);
            exit;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user = $this->auth->attempt($data['login'], $data['password'], $ip);

        if ($user === null) {
            // Generic failure — avoid enumeration timing
            $_SESSION['_flash'] = ['error' => 'Invalid credentials or account inactive.'];
            header('Location: /login', true, 302);
            exit;
        }

        // Check email verification if required?
        // Pending status already blocked in attempt() — so if we reach here, active

        $this->auth->login($user);

        $intended = $_SESSION['intended'] ?? null;
        unset($_SESSION['intended']);

        if ($intended && str_starts_with($intended, '/') && !str_contains($intended, '//')) {
            header('Location: ' . $intended, true, 302);
            exit;
        }

        $target = $user->isAdmin() ? '/admin/dashboard' : '/dashboard';
        header('Location: ' . $target, true, 302);
        exit;
    }

    public function logout(array $params = []): void
    {
        $this->auth->logout();
        $_SESSION['_flash'] = ['success' => 'Logged out successfully.'];
        header('Location: /login', true, 302);
        exit;
    }
}
