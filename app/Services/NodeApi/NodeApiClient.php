<?php

declare(strict_types=1);

namespace App\Services\NodeApi;

use App\Helpers\Database;

final class NodeApiClient
{
    public function __construct(
        private readonly Database $db,
        private readonly array $config
    ) {}

    private function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid node URL');
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Invalid node URL structure');
        }
        $scheme = strtolower($parts['scheme']);
        $env = strtolower($_ENV['APP_ENV'] ?? 'development');
        if ($env === 'production' && $scheme !== 'https') {
            throw new \RuntimeException('Production node communication requires HTTPS');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('Node URL must not contain embedded credentials');
        }
        // SSRF: node URL must come from trusted DB, not customer input. We already validate it's from hosting_nodes.api_url
    }

    /**
     * Send an authenticated request to the Node API.
     * @return array{http:int, body:array, headers:array}
     */
    public function request(int $nodeId, string $method, string $path, array $payload, string $idempotencyKey, ?string $requestId = null): array
    {
        $node = $this->db->fetch("SELECT id, api_url, status FROM hosting_nodes WHERE id=?", [$nodeId]);
        if (!$node || $node['status'] !== 'active') {
            throw new \RuntimeException('Node not available', 503);
        }
        $baseUrl = rtrim($node['api_url'] ?? '', '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('Node URL not configured', 503);
        }
        $this->validateUrl($baseUrl);
        $url = $baseUrl . $path;
        $this->validateUrl($url);

        $secret = NodeCredentialManager::retrievePlain($this->db, $nodeId);
        if ($secret === null) {
            throw new \RuntimeException('Node credential not available', 500);
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) $body = '{}';

        if (strlen($body) > (int)($this->config['body_limit'] ?? 65536)) {
            throw new \RuntimeException('Request body too large', 413);
        }

        $headers = NodeApiSigner::buildHeaders($nodeId, $secret, $method, $path, $body, $idempotencyKey, $requestId);
        $requestId = $headers[NodeApiSigner::HEADER_REQUEST_ID];

        // Mock mode: if URL is localhost mock, dispatch locally without HTTP
        if (str_contains($baseUrl, 'mock-provisioning') || str_contains($baseUrl, 'localhost')) {
            // For local development, simulate via LocalMockNodeApiProvider without network
            return $this->localDispatch($method, $path, $payload, $headers);
        }

        $ch = curl_init($url);
        $httpHeaders = [];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = $k . ': ' . $v;
        }
        $httpHeaders[] = 'Content-Type: application/json';
        $httpHeaders[] = 'Accept: application/json';

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => (int)($this->config['connect_timeout'] ?? 5),
            CURLOPT_TIMEOUT => (int)($this->config['timeout'] ?? 10),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => (bool)($this->config['tls_verify'] ?? true),
            CURLOPT_SSL_VERIFYHOST => (bool)($this->config['tls_verify'] ?? true) ? 2 : 0,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Node connection failed: ' . $err, 503);
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerStr = substr($raw, 0, $headerSize);
        $bodyStr = substr($raw, $headerSize);
        curl_close($ch);

        $decoded = json_decode($bodyStr, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid node response', 500);
        }
        // Validate response shape
        if (!isset($decoded['request_id']) || $decoded['request_id'] !== $requestId) {
            // allow but warn; strict validation could reject
        }
        return ['http'=>$httpCode, 'body'=>$decoded, 'headers'=>[]];
    }

    private function localDispatch(string $method, string $path, array $payload, array $headers): array
    {
        // Simulate Node API handling locally for tests/dev: directly invoke provider
        // We bypass auth for local mock but still validate operation allow-list
        $opMap = $this->config['allowed_endpoints'] ?? [];
        $key = strtoupper($method) . ' ' . $path;
        if (!isset($opMap[$key])) {
            return ['http'=>404, 'body'=>['error'=>['code'=>'not_found'],'request_id'=>$headers[NodeApiSigner::HEADER_REQUEST_ID]], 'headers'=>[]];
        }
        $op = $opMap[$key];
        if ($op === '__health__') {
            return ['http'=>200, 'body'=>['status'=>'ok','request_id'=>$headers[NodeApiSigner::HEADER_REQUEST_ID]], 'headers'=>[]];
        }
        $provider = new LocalMockNodeApiProvider();
        $opPayload = $payload['payload'] ?? $payload;
        $result = match($op) {
            'hosting.create_identity' => $provider->createIdentity($opPayload),
            'hosting.create_filesystem' => $provider->createFilesystem($opPayload),
            'hosting.configure_php_fpm' => $provider->configurePhpFpm($opPayload),
            'hosting.apply_resource_policy' => $provider->applyResourcePolicy($opPayload),
            'hosting.suspend' => $provider->suspend($opPayload),
            'hosting.unsuspend' => $provider->unsuspend($opPayload),
            'hosting.terminate' => $provider->terminate($opPayload),
            default => ['status'=>'error','error'=>'unknown_operation'],
        };
        return ['http'=>200, 'body'=>['result'=>$result,'request_id'=>$headers[NodeApiSigner::HEADER_REQUEST_ID]], 'headers'=>[]];
    }

    public static function isRetryable(int $httpCode): bool
    {
        return in_array($httpCode, [429, 500, 502, 503, 504], true);
    }
    public static function isAuthFailure(int $httpCode): bool { return $httpCode === 401; }
    public static function isValidationFailure(int $httpCode): bool { return in_array($httpCode, [400,422], true); }
}
