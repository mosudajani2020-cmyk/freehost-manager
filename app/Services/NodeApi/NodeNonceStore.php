<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

use App\Helpers\Database;

final class NodeNonceStore
{
    public function __construct(private readonly Database $db) {}

    /**
     * Attempt to claim a nonce. Returns true if new, false if replay (already exists).
     * Uses unique constraint (node_id, nonce) for race-safe protection.
     */
    public function claim(int $nodeId, string $nonce, int $ttlSeconds): bool
    {
        $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));
        try {
            $this->db->execute(
                "INSERT INTO node_api_nonces (node_id, nonce, expires_at) VALUES (?,?,?)",
                [$nodeId, $nonce, $expiresAt]
            );
            return true;
        } catch (\Throwable $e) {
            // duplicate key => replay
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'duplicate')) {
                return false;
            }
            throw $e;
        }
    }

    public function exists(int $nodeId, string $nonce): bool
    {
        $row = $this->db->fetch("SELECT 1 FROM node_api_nonces WHERE node_id=? AND nonce=? LIMIT 1", [$nodeId, $nonce]);
        return $row !== null;
    }

    public function cleanup(): int
    {
        return $this->db->execute("DELETE FROM node_api_nonces WHERE expires_at < NOW()");
    }
}
