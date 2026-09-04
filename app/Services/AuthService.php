<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\UserRepository;
use App\Security\RateLimiter;
use App\Models\User;

final class AuthService
{
    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly AuditService $audit,
    ) {}

    public function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $options = $algo === PASSWORD_ARGON2ID ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2] : ['cost' => 12];
        return password_hash($password, $algo, $options);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_needs_rehash($hash, $algo);
    }

    /**
     * Attempt login — returns User on success, null on failure (generic).
     * Handles lockout, rate limiting, audit.
     */
    public function attempt(string $login, string $password, string $ip): ?User
    {
        // Rate limit by IP and by login
        $ipKey = 'login:ip:' . $ip;
        $loginKey = 'login:user:' . strtolower(trim($login));

        if (!RateLimiter::attempt($ipKey, 5, 900)) { // 5 per 15m
            $this->audit->log(null, 'login.blocked', 'user', $login, 'failure', ['reason' => 'rate_limit_ip']);
            return null;
        }
        if (!RateLimiter::attempt($loginKey, 5, 900)) {
            $this->audit->log(null, 'login.blocked', 'user', $login, 'failure', ['reason' => 'rate_limit_user']);
            return null;
        }

        $user = $this->users->findByLogin($login);
        if ($user === null) {
            // Generic failure — do not reveal enumeration, but still audit
            $this->audit->log(null, 'login.failed', 'user', $login, 'failure', ['reason' => 'not_found']);
            // Timings: dummy verify to mitigate timing side-channel
            password_verify($password, '$2y$12$usesomesillystringfore2y12$usesome');
            return null;
        }

        // Check status
        if ($user->status !== 'active') {
            $this->audit->log($user->id, 'login.failed', 'user', (string) $user->id, 'failure', ['reason' => 'inactive_status:' . $user->status]);
            return null;
        }

        // Check lockout
        $row = $this->db->fetch("SELECT lockout_until FROM users WHERE id = ?", [$user->id]);
        if ($row && $row['lockout_until'] !== null && strtotime($row['lockout_until']) > time()) {
            $this->audit->log($user->id, 'login.failed', 'user', (string) $user->id, 'failure', ['reason' => 'locked']);
            return null;
        }

        if (!$this->verifyPassword($password, $user->passwordHash)) {
            $this->users->incrementFailedLogin($user->id);
            // Lockout after 5 failures: 15 min
            $failRow = $this->db->fetch("SELECT failed_login_count FROM users WHERE id = ?", [$user->id]);
            $fails = (int) ($failRow['failed_login_count'] ?? 0);
            if ($fails >= 5) {
                $until = date('Y-m-d H:i:s', time() + 900);
                $this->users->setLockout($user->id, $until);
            }
            $this->audit->log($user->id, 'login.failed', 'user', (string) $user->id, 'failure', ['reason' => 'bad_password']);
            return null;
        }

        // Success — rehash if needed
        if ($this->needsRehash($user->passwordHash)) {
            $newHash = $this->hashPassword($password);
            $this->users->updatePassword($user->id, $newHash);
        }

        $this->users->updateLastLogin($user->id);
        $this->audit->log($user->id, 'login.success', 'user', (string) $user->id, 'success');
        // Clear rate limits on success
        RateLimiter::clear($loginKey);
        RateLimiter::clear($ipKey);

        // Refresh user to get updated fields
        return $this->users->findById($user->id);
    }

    public function login(User $user): void
    {
        // Session fixation protection
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_roles'] = $user->roles;
        $_SESSION['user_permissions'] = $user->permissions;
        $_SESSION['last_activity'] = time();
        // Anomaly detection: store UA hash (not strict binding)
        $_SESSION['user_agent_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    }

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $this->audit->log((int) $userId, 'logout', 'user', (string) $userId, 'success');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        // Start fresh for flash messages
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
    }

    public function currentUser(): ?User
    {
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }
        return $this->users->findById((int) $id);
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }
}
