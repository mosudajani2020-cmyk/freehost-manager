<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Security\RateLimiter;

final class PasswordResetService
{
    public function __construct(private readonly Database $db, private readonly AuditService $audit) {}

    /**
     * Request reset — always returns success generic to avoid enumeration.
     */
    public function request(string $email, string $ip): bool
    {
        $key = 'pwdreset:ip:' . $ip;
        if (!RateLimiter::attempt($key, 3, 3600)) {
            return true; // generic
        }
        $email = strtolower(trim($email));
        $user = $this->db->fetch("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        if ($user === null) {
            // Generic — do not reveal
            $this->audit->log(null, 'password_reset.requested', 'user', $email, 'success', ['reason' => 'not_found_generic']);
            return true;
        }
        $userId = (int) $user['id'];

        // Rate limit per user
        $userKey = 'pwdreset:user:' . $userId;
        if (!RateLimiter::attempt($userKey, 3, 3600)) {
            return true;
        }

        // Invalidate previous unused tokens
        $this->db->execute("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL", [$userId]);

        $rawToken = bin2hex(random_bytes(32)); // 64 hex chars
        $hash = hash('sha256', $rawToken);
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1h

        $this->db->execute(
            "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
            [$userId, $hash, $expires]
        );

        // Mail abstraction — log driver for Phase 1
        $this->sendMail($email, $rawToken);

        $this->audit->log($userId, 'password_reset.requested', 'user', (string) $userId, 'success');
        return true;
    }

    private function sendMail(string $email, string $token): void
    {
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $link = rtrim($appUrl, '/') . '/reset-password?token=' . $token;
        $logFile = storage_path('logs/mail.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = sprintf("[%s] To: %s | Reset link: %s%s", date('Y-m-d H:i:s'), $email, $link, PHP_EOL);
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        // Also error_log for visibility
        error_log('[Mail] Password reset for ' . $email . ' — logged to mail.log');
    }

    public function validateToken(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $row = $this->db->fetch(
            "SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1",
            [$hash]
        );
        return $row ?: null;
    }

    public function resetPassword(string $rawToken, string $newPassword, AuditService $audit, \App\Services\AuthService $auth): bool
    {
        $row = $this->validateToken($rawToken);
        if ($row === null) {
            return false;
        }
        $userId = (int) $row['user_id'];
        $hash = $auth->hashPassword($newPassword);
        $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $userId]);
            $this->db->execute("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [$row['id']]);
            // Invalidate all other tokens
            $this->db->execute("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL AND id != ?", [$userId, $row['id']]);
            $this->db->commit();
            $audit->log($userId, 'password_reset.completed', 'user', (string) $userId, 'success');
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[PasswordReset] Failed: ' . $e->getMessage());
            return false;
        }
    }
}
