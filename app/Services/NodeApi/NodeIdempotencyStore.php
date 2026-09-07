<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

use App\Helpers\Database;

final class NodeIdempotencyStore
{
    public function __construct(private readonly Database $db) {}

    /**
     * Try to reserve idempotency key. Returns null if new reservation, or existing row if already present.
     * Race-safe via unique constraint.
     */
    public function reserve(int $nodeId, string $key, string $operation, ?string $requestId): ?array
    {
        $existing = $this->db->fetch("SELECT * FROM node_api_idempotency WHERE node_id=? AND idempotency_key=?", [$nodeId, $key]);
        if ($existing !== null) {
            return $existing;
        }
        try {
            $this->db->execute(
                "INSERT INTO node_api_idempotency (node_id, idempotency_key, operation, request_id, status_code, response_body) VALUES (?,?,?,?,?,?)",
                [$nodeId, $key, $operation, $requestId, 202, null]
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'duplicate')) {
                return $this->db->fetch("SELECT * FROM node_api_idempotency WHERE node_id=? AND idempotency_key=?", [$nodeId, $key]);
            }
            throw $e;
        }
        return null;
    }

    public function complete(int $nodeId, string $key, int $statusCode, ?array $responseBody): void
    {
        $json = $responseBody !== null ? json_encode($responseBody, JSON_UNESCAPED_SLASHES) : null;
        $this->db->execute(
            "UPDATE node_api_idempotency SET status_code=?, response_body=?, updated_at=NOW() WHERE node_id=? AND idempotency_key=?",
            [$statusCode, $json, $nodeId, $key]
        );
    }

    public function fetch(int $nodeId, string $key): ?array
    {
        return $this->db->fetch("SELECT * FROM node_api_idempotency WHERE node_id=? AND idempotency_key=?", [$nodeId, $key]);
    }
}
