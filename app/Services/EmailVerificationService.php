<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Security\RateLimiter;

final class EmailVerificationService
{
    public function __construct(private readonly Database $db, private readonly AuditService $audit) {}

    public function createToken(int $userId): string
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() + 86400); // 24h
        $this->db->execute("DELETE FROM email_verifications WHERE user_id = ?", [$userId]);
        $this->db->execute(
            "INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
            [$userId, $hash, $expires]
        );
        // Log mail
        $emailRow = $this->db->fetch("SELECT email FROM users WHERE id = ?", [$userId]);
        $email = $emailRow['email'] ?? 'unknown';
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        $link = rtrim($appUrl, '/') . '/verify-email?token=' . $raw;
        $logFile = storage_path('logs/mail.log');
        @file_put_contents($logFile, sprintf("[%s] To: %s | Verify link: %s%s", date('Y-m-d H:i:s'), $email, $link, PHP_EOL), FILE_APPEND | LOCK_EX);
        return $raw;
    }

    public function verify(string $rawToken): bool
    {
        $hash = hash('sha256', $rawToken);
        $row = $this->db->fetch(
            "SELECT * FROM email_verifications WHERE token_hash = ? AND verified_at IS NULL AND expires_at > NOW() LIMIT 1",
            [$hash]
        );
        if ($row === null) {
            return false;
        }
        $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE users SET email_verified_at = NOW(), status = 'active' WHERE id = ?", [$row['user_id']]);
            $this->db->execute("UPDATE email_verifications SET verified_at = NOW() WHERE id = ?", [$row['id']]);
            $this->db->commit();
            $this->audit->log((int) $row['user_id'], 'email.verified', 'user', (string) $row['user_id'], 'success');
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('[EmailVerify] Failed: ' . $e->getMessage());
            return false;
        }
    }

    public function resend(int $userId, string $ip): bool
    {
        $key = 'verify:ip:' . $ip;
        if (!RateLimiter::attempt($key, 3, 3600)) {
            return false;
        }
        $userKey = 'verify:user:' . $userId;
        if (!RateLimiter::attempt($userKey, 3, 3600)) {
            return false;
        }
        $this->createToken($userId);
        $this->audit->log($userId, 'email.verification_resent', 'user', (string) $userId, 'success');
        return true;
    }
}
