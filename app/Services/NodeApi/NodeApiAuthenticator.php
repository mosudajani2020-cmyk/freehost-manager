<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

use App\Helpers\Database;

final class NodeApiAuthenticator
{
    public function __construct(
        private readonly Database $db,
        private readonly NodeNonceStore $nonces,
        private readonly NodeIdempotencyStore $idempotency,
        private readonly array $config
    ) {}

    /**
     * Authenticate and authorize a Node API request.
     * Returns structured result or throws with secure error.
     * @param array<string,string> $headers lower-cased keys optional
     */
    public function authenticate(string $method, string $path, string $body, array $headers): array
    {
        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower($k)] = $v;
        }
        $nodeId = trim($h[strtolower(NodeApiSigner::HEADER_NODE_ID)] ?? '');
        $timestamp = trim($h[strtolower(NodeApiSigner::HEADER_TIMESTAMP)] ?? '');
        $nonce = trim($h[strtolower(NodeApiSigner::HEADER_NONCE)] ?? '');
        $requestId = trim($h[strtolower(NodeApiSigner::HEADER_REQUEST_ID)] ?? '');
        $idempotencyKey = trim($h[strtolower(NodeApiSigner::HEADER_IDEMPOTENCY_KEY)] ?? '');
        $signature = trim($h[strtolower(NodeApiSigner::HEADER_SIGNATURE)] ?? '');

        // Validate presence
        if ($nodeId === '' || $timestamp === '' || $nonce === '' || $requestId === '' || $idempotencyKey === '' || $signature === '') {
            return $this->fail('missing_auth_headers', 401, $requestId ?: bin2hex(random_bytes(8)));
        }
        if (!ctype_digit($nodeId)) {
            return $this->fail('invalid_node_id', 401, $requestId);
        }
        $nodeIdInt = (int)$nodeId;

        // Validate formats
        if (!ctype_digit($timestamp)) {
            return $this->fail('invalid_timestamp', 401, $requestId);
        }
        $ts = (int)$timestamp;
        $now = time();
        $tolerance = (int)($this->config['timestamp_tolerance'] ?? 300);
        if (abs($now - $ts) > $tolerance) {
            return $this->fail('timestamp_out_of_window', 401, $requestId);
        }
        if (!preg_match('/^[a-f0-9]{16,128}$/i', $nonce)) {
            return $this->fail('invalid_nonce', 401, $requestId);
        }
        if (!preg_match('/^[a-f0-9\-]{8,64}$/i', $requestId)) {
            return $this->fail('invalid_request_id', 400, $requestId);
        }
        if (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 100 || !preg_match('/^[A-Za-z0-9_\-]+$/', $idempotencyKey)) {
            return $this->fail('invalid_idempotency_key', 400, $requestId);
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return $this->fail('invalid_signature_format', 401, $requestId);
        }
        if (strlen($body) > (int)($this->config['body_limit'] ?? 65536)) {
            return $this->fail('request_too_large', 413, $requestId);
        }

        // Node existence and status
        $node = $this->db->fetch("SELECT id, status FROM hosting_nodes WHERE id=?", [$nodeIdInt]);
        if (!$node) {
            return $this->fail('authentication_failed', 401, $requestId);
        }
        if ($node['status'] !== 'active') {
            return $this->fail('node_not_active', 403, $requestId);
        }

        // Retrieve secret for signature verification
        $secret = NodeCredentialManager::retrievePlain($this->db, $nodeIdInt);
        if ($secret === null) {
            // fallback for nodes without encrypted secret: try hash-based lookup not possible; fail generic
            return $this->fail('authentication_failed', 401, $requestId);
        }

        // Verify signature constant-time
        $expected = NodeApiSigner::sign($secret, $method, $path, $timestamp, $nonce, $requestId, $idempotencyKey, $body);
        if (!hash_equals($expected, strtolower($signature))) {
            return $this->fail('authentication_failed', 401, $requestId);
        }

        // Nonce replay protection (persistent, race-safe)
        $ttl = (int)($this->config['nonce_ttl'] ?? 600);
        if (!$this->nonces->claim($nodeIdInt, $nonce, $ttl)) {
            return $this->fail('replay_detected', 409, $requestId);
        }

        // Idempotency check (return stored if exists)
        $existing = $this->idempotency->fetch($nodeIdInt, $idempotencyKey);
        if ($existing !== null && $existing['response_body'] !== null) {
            $stored = json_decode($existing['response_body'], true);
            return [
                'ok' => true,
                'idempotent_replay' => true,
                'node_id' => $nodeIdInt,
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
                'stored_status' => (int)$existing['status_code'],
                'stored_body' => $stored,
            ];
        }

        return [
            'ok' => true,
            'idempotent_replay' => false,
            'node_id' => $nodeIdInt,
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
            'timestamp' => $ts,
            'nonce' => $nonce,
        ];
    }

    private function fail(string $code, int $http, string $requestId): array
    {
        return ['ok' => false, 'code' => $code, 'http' => $http, 'request_id' => $requestId];
    }
}
