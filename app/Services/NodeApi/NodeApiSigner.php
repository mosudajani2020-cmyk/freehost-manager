<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

/**
 * NodeApiSigner — HMAC canonical request signing (P3).
 *
 * Canonical form: METHOD\nPATH\nTIMESTAMP\nNONCE\nREQUEST_ID\nIDEMPOTENCY_KEY\nBODY_HASH
 * where BODY_HASH = hex(sha256(body)) and signature = hex(hmac-sha256(secret, canonical)).
 */
final class NodeApiSigner
{
    public const HEADER_NODE_ID = 'X-FHM-Node-ID';
    public const HEADER_TIMESTAMP = 'X-FHM-Timestamp';
    public const HEADER_NONCE = 'X-FHM-Nonce';
    public const HEADER_REQUEST_ID = 'X-FHM-Request-ID';
    public const HEADER_IDEMPOTENCY_KEY = 'X-FHM-Idempotency-Key';
    public const HEADER_SIGNATURE = 'X-FHM-Signature';

    public static function canonical(string $method, string $path, string $timestamp, string $nonce, string $requestId, string $idempotencyKey, string $body): string
    {
        $bodyHash = hash('sha256', $body);
        // Normalize path: ensure leading slash, no trailing slash except root, no query
        $path = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $requestId,
            $idempotencyKey,
            $bodyHash,
        ]);
    }

    public static function sign(string $secret, string $method, string $path, string $timestamp, string $nonce, string $requestId, string $idempotencyKey, string $body): string
    {
        $canonical = self::canonical($method, $path, $timestamp, $nonce, $requestId, $idempotencyKey, $body);
        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Build authentication headers for an outbound request.
     * @return array<string,string>
     */
    public static function buildHeaders(int $nodeId, string $secret, string $method, string $path, string $body, ?string $idempotencyKey = null, ?string $requestId = null): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $requestId = $requestId ?? bin2hex(random_bytes(8));
        $idempotencyKey = $idempotencyKey ?? bin2hex(random_bytes(16));
        $signature = self::sign($secret, $method, $path, $timestamp, $nonce, $requestId, $idempotencyKey, $body);
        return [
            self::HEADER_NODE_ID => (string) $nodeId,
            self::HEADER_TIMESTAMP => $timestamp,
            self::HEADER_NONCE => $nonce,
            self::HEADER_REQUEST_ID => $requestId,
            self::HEADER_IDEMPOTENCY_KEY => $idempotencyKey,
            self::HEADER_SIGNATURE => $signature,
        ];
    }

    public static function sanitizeForLog(string $message): string
    {
        return preg_replace('/(signature|secret|token|api[_-]?key)[=:]\s*\S+/i', '$1=[REDACTED]', $message) ?? $message;
    }
}
