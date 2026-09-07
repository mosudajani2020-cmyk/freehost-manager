<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Services\NodeApi\NodeApiAuthenticator;
use App\Services\NodeApi\NodeNonceStore;
use App\Services\NodeApi\NodeIdempotencyStore;
use App\Services\NodeApi\NodeRateLimiter;
use App\Services\NodeApi\NodeRequestValidator;
use App\Services\NodeApi\LocalMockNodeApiProvider;

final class NodeApiController
{
    private function config(): array
    {
        return require dirname(__DIR__,2) . '/config/node-api.php';
    }

    private function db(): Database
    {
        return Database::getInstance();
    }

    private function readBody(): string
    {
        $body = file_get_contents('php://input') ?: '';
        // Also support test injection via global
        if ($body === '' && isset($GLOBALS['_test_node_body'])) {
            $body = $GLOBALS['_test_node_body'];
        }
        return $body;
    }

    private function headers(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', substr($k, 5));
                // Normalize to X-FHM-* case
                $out[$name] = $v;
            }
        }
        // Also allow direct X-FHM headers via getallheaders fallback
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                $out[$k] = $v;
                $out[strtoupper($k)] = $v;
            }
        }
        // Test injection
        if (isset($GLOBALS['_test_node_headers'])) {
            foreach ($GLOBALS['_test_node_headers'] as $k => $v) $out[$k] = $v;
        }
        return $out;
    }

    private function jsonResponse(int $code, array $body, string $requestId): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        $body['request_id'] = $requestId;
        echo json_encode($body, JSON_UNESCAPED_SLASHES);
    }

    private function auditLog(Database $db, string $requestId, ?int $nodeId, ?string $op, ?string $idemKey, string $result, int $code, int $durationMs, ?string $failureCode): void
    {
        try {
            $db->execute(
                "INSERT INTO node_api_audit (request_id, node_id, operation, idempotency_key, result, status_code, duration_ms, actor, failure_code) VALUES (?,?,?,?,?,?,?,?,?)",
                [$requestId, $nodeId, $op, $idemKey, $result, $code, $durationMs, 'worker', $failureCode]
            );
        } catch (\Throwable $e) { error_log('[NodeAPI] audit failed: '.$e->getMessage()); }
    }

    public function health(array $params = []): void
    {
        $requestId = bin2hex(random_bytes(8));
        $this->jsonResponse(200, ['status'=>'ok','version'=> $this->config()['version'] ?? 'v1'], $requestId);
    }

    private function handle(string $expectedOp, array $params = []): void
    {
        $start = microtime(true);
        $config = $this->config();
        $db = $this->db();
        $body = $this->readBody();
        $headers = $this->headers();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        // Strip base prefix
        $basePrefix = '/freehost-manager/public';
        if (str_starts_with($path, $basePrefix)) $path = substr($path, strlen($basePrefix)) ?: '/';

        // Body size
        if (strlen($body) > (int)($config['body_limit'] ?? 65536)) {
            $rid = $headers['X-FHM-Request-ID'] ?? $headers['X-FHM-REQUEST-ID'] ?? bin2hex(random_bytes(8));
            $this->auditLog($db, $rid, null, $expectedOp, null, 'failure', 413, 0, 'request_too_large');
            $this->jsonResponse(413, ['error'=>['code'=>'request_too_large']], $rid); return;
        }

        // Rate limiting on auth failures: check generic limit
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Authenticate
        $auth = new NodeApiAuthenticator($db, new NodeNonceStore($db), new NodeIdempotencyStore($db), $config);
        $result = $auth->authenticate($method, $path, $body, $headers);
        $requestId = $result['request_id'] ?? bin2hex(random_bytes(8));
        $nodeId = $result['node_id'] ?? null;
        $idemKey = $result['idempotency_key'] ?? $headers['X-FHM-Idempotency-Key'] ?? $headers['X-FHM-IDEMPOTENCY-KEY'] ?? null;

        if (($result['ok'] ?? false) === false) {
            $code = $result['http'] ?? 401;
            $failCode = $result['code'] ?? 'authentication_failed';
            // Rate limit auth failures
            $key = (string)($nodeId ?? $ip);
            if (!NodeRateLimiter::checkAuthFailure($key, $config)) {
                $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 429, 0, 'rate_limited');
                $this->jsonResponse(429, ['error'=>['code'=>'rate_limited']], $requestId); return;
            }
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', $code, $duration, $failCode);
            // Generic error for auth failures
            $publicCode = in_array($failCode, ['replay_detected','request_too_large','node_not_active'], true) ? $failCode : 'authentication_failed';
            if ($code === 400) $publicCode = $failCode;
            $this->jsonResponse($code, ['error'=>['code'=>$publicCode]], $requestId); return;
        }

        // Idempotent replay: return stored response
        if (!empty($result['idempotent_replay'])) {
            $storedBody = $result['stored_body'];
            $storedCode = $result['stored_status'] ?? 200;
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'success', $storedCode, $duration, null);
            $this->jsonResponse($storedCode, $storedBody ?? ['status'=>'ok'], $requestId); return;
        }

        // Operation allow-list: expectedOp must be in allowed_operations and match endpoint mapping
        $allowedOps = $config['allowed_operations'] ?? [];
        if (!in_array($expectedOp, $allowedOps, true)) {
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 403, $duration, 'operation_not_allowed');
            $this->jsonResponse(403, ['error'=>['code'=>'operation_not_allowed']], $requestId); return;
        }
        // Verify endpoint mapping matches operation
        $allowedEndpoints = $config['allowed_endpoints'] ?? [];
        $endpointKey = strtoupper($method) . ' ' . $path;
        // Normalize path for lookup
        $normalizedPath = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        $endpointKeyNorm = strtoupper($method) . ' ' . $normalizedPath;
        $mappedOp = $allowedEndpoints[$endpointKey] ?? $allowedEndpoints[$endpointKeyNorm] ?? null;
        if ($mappedOp !== $expectedOp) {
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 404, $duration, 'endpoint_not_found');
            $this->jsonResponse(404, ['error'=>['code'=>'not_found']], $requestId); return;
        }

        // Content-type
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if ($body !== '' && $ct !== '' && !str_contains(strtolower($ct), 'application/json')) {
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 400, $duration, 'invalid_content_type');
            $this->jsonResponse(400, ['error'=>['code'=>'invalid_content_type']], $requestId); return;
        }

        // Parse body
        $data = [];
        if ($body !== '') {
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $duration = (int)((microtime(true)-$start)*1000);
                $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 400, $duration, 'invalid_json');
                $this->jsonResponse(400, ['error'=>['code'=>'invalid_json']], $requestId); return;
            }
            // depth check: json_decode depth limited; we check nested depth manually shallow
            if ($this->jsonDepth($data) > 5) {
                $duration = (int)((microtime(true)-$start)*1000);
                $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 400, $duration, 'json_too_deep');
                $this->jsonResponse(400, ['error'=>['code'=>'json_too_deep']], $requestId); return;
            }
        }

        // Payload validation: extract payload wrapper
        $payload = $data['payload'] ?? $data;
        if (!is_array($payload)) $payload = [];
        $valErrors = NodeRequestValidator::validatePayload($expectedOp, $payload);
        if (!empty($valErrors)) {
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 422, $duration, 'validation_failed');
            $this->jsonResponse(422, ['error'=>['code'=>'validation_failed','details'=>$valErrors]], $requestId); return;
        }

        // Reserve idempotency (race-safe)
        $idemStore = new NodeIdempotencyStore($db);
        $existing = $idemStore->reserve($nodeId, $idemKey, $expectedOp, $requestId);
        if ($existing !== null && $existing['response_body'] !== null) {
            $stored = json_decode($existing['response_body'], true);
            $storedCode = (int)$existing['status_code'];
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'success', $storedCode, $duration, null);
            $this->jsonResponse($storedCode, $stored ?? ['status'=>'ok'], $requestId); return;
        }
        // If reserve found existing with null body (concurrent), treat as conflict
        if ($existing !== null && $existing['response_body'] === null) {
            // Another request is processing; return 409
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 409, $duration, 'idempotency_conflict');
            $this->jsonResponse(409, ['error'=>['code'=>'idempotency_conflict']], $requestId); return;
        }

        // Execute typed operation via LocalMock (P3 foundation; real Linux deferred)
        $provider = new LocalMockNodeApiProvider();
        try {
            $opResult = match($expectedOp) {
                'hosting.create_identity' => $provider->createIdentity($payload),
                'hosting.create_filesystem' => $provider->createFilesystem($payload),
                'hosting.configure_php_fpm' => $provider->configurePhpFpm($payload),
                'hosting.apply_resource_policy' => $provider->applyResourcePolicy($payload),
                'hosting.suspend' => $provider->suspend($payload),
                'hosting.unsuspend' => $provider->unsuspend($payload),
                'hosting.terminate' => $provider->terminate($payload),
                default => throw new \RuntimeException('unknown operation'),
            };
        } catch (\Throwable $e) {
            $duration = (int)((microtime(true)-$start)*1000);
            $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'failure', 500, $duration, 'operation_failed');
            $this->jsonResponse(500, ['error'=>['code'=>'internal_error']], $requestId); return;
        }

        $responseBody = ['status'=>'ok','operation'=>$expectedOp,'result'=>$opResult];
        $idemStore->complete($nodeId, $idemKey, 200, $responseBody);
        $duration = (int)((microtime(true)-$start)*1000);
        $this->auditLog($db, $requestId, $nodeId, $expectedOp, $idemKey, 'success', 200, $duration, null);
        $this->jsonResponse(200, $responseBody, $requestId);
    }

    private function jsonDepth(array $arr, int $depth = 0): int
    {
        $max = $depth;
        foreach ($arr as $v) {
            if (is_array($v)) {
                $d = $this->jsonDepth($v, $depth+1);
                if ($d > $max) $max = $d;
            }
        }
        return $max;
    }

    // Explicit endpoint handlers
    public function createIdentity(array $p=[]): void { $this->handle('hosting.create_identity', $p); }
    public function createFilesystem(array $p=[]): void { $this->handle('hosting.create_filesystem', $p); }
    public function configurePhpFpm(array $p=[]): void { $this->handle('hosting.configure_php_fpm', $p); }
    public function applyResourcePolicy(array $p=[]): void { $this->handle('hosting.apply_resource_policy', $p); }
    public function suspend(array $p=[]): void { $this->handle('hosting.suspend', $p); }
    public function unsuspend(array $p=[]): void { $this->handle('hosting.unsuspend', $p); }
    public function terminate(array $p=[]): void { $this->handle('hosting.terminate', $p); }
}
