<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;

final class AuditService
{
    public function __construct(private readonly Database $db) {}

    public function log(?int $userId, string $action, string $resourceType = '', string $resourceId = '', string $result = 'success', array $metadata = []): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($ua !== null && strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }
        // Never log secrets
        $metadata = $this->scrub($metadata);
        $metaJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null;

        try {
            $this->db->execute(
                "INSERT INTO audit_logs (user_id, action, resource_type, resource_id, ip_address, user_agent, result, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, $action, $resourceType, $resourceId ?: null, $ip, $ua, $result, $metaJson]
            );
        } catch (\Throwable $e) {
            error_log('[Audit] Failed: ' . $e->getMessage());
        }
    }

    private function scrub(array $data): array
    {
        $blocked = ['password', 'password_hash', 'token', 'token_hash', 'app_key'];
        foreach ($blocked as $k) {
            foreach ($data as $key => $val) {
                if (stripos((string) $key, $k) !== false) {
                    $data[$key] = '[REDACTED]';
                }
            }
        }
        return $data;
    }
}
