<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

use App\Helpers\Database;

/**
 * NodeCredentialManager — generation, encryption, storage and retrieval of node API secrets (P3).
 *
 * Secrets are never stored plaintext. The database holds hash (for lookup) and
 * authenticated-encryption ciphertext (for worker signing). The encryption key
 * is APP_KEY (base64 decoded), never committed.
 */
final class NodeCredentialManager
{
    public static function generateSecret(): string
    {
        return 'fhm_' . bin2hex(random_bytes(24));
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function preview(string $plain): string
    {
        return substr($plain, 0, 8) . '...';
    }

    private static function encryptionKey(): string
    {
        $key = $_ENV['APP_KEY'] ?? '';
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false && strlen($decoded) >= 32) {
                return substr($decoded, 0, 32);
            }
        }
        // fallback deterministic for tests (uses raw key padded)
        return hash('sha256', $key !== '' ? $key : 'freehost-default-key-32bytes!!', true);
    }

    public static function encrypt(string $plain): string
    {
        $key = self::encryptionKey();
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $ciphertext): string
    {
        $key = self::encryptionKey();
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('Invalid ciphertext');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Decryption failed');
        }
        return $plain;
    }

    public static function store(Database $db, int $nodeId, string $plain): void
    {
        $hash = self::hash($plain);
        $preview = self::preview($plain);
        $enc = self::encrypt($plain);
        $db->execute(
            "UPDATE hosting_nodes SET api_key_hash=?, api_key_preview=?, api_secret_encrypted=?, secret_rotated_at=NOW() WHERE id=?",
            [$hash, $preview, $enc, $nodeId]
        );
    }

    public static function retrievePlain(Database $db, int $nodeId): ?string
    {
        $row = $db->fetch("SELECT api_secret_encrypted FROM hosting_nodes WHERE id=?", [$nodeId]);
        if (!$row || empty($row['api_secret_encrypted'])) {
            return null;
        }
        try {
            return self::decrypt($row['api_secret_encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function verifyHash(Database $db, int $nodeId, string $plain): bool
    {
        $row = $db->fetch("SELECT api_key_hash FROM hosting_nodes WHERE id=?", [$nodeId]);
        if (!$row || empty($row['api_key_hash'])) {
            return false;
        }
        return hash_equals($row['api_key_hash'], self::hash($plain));
    }
}
