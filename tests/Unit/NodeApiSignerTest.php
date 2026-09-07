<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\NodeApi\NodeApiSigner;

final class NodeApiSignerTest extends TestCase
{
    public function testCanonicalAndSignDeterministic(): void
    {
        $secret = 'test_secret_123';
        $sig1 = NodeApiSigner::sign($secret, 'POST', '/v1/node/hosting/create-identity', '1700000000', str_repeat('a',32), str_repeat('b',16), 'idem-key-1', '{"operation":"hosting.create_identity"}');
        $sig2 = NodeApiSigner::sign($secret, 'POST', '/v1/node/hosting/create-identity', '1700000000', str_repeat('a',32), str_repeat('b',16), 'idem-key-1', '{"operation":"hosting.create_identity"}');
        $this->assertEquals($sig1, $sig2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sig1);
    }

    public function testModifiedBodyRejected(): void
    {
        $secret = 's3cret';
        $sig = NodeApiSigner::sign($secret, 'POST', '/v1/node/hosting/create-identity', '1700000000', str_repeat('a',32), 'req1', 'idem1', '{"a":1}');
        $sig2 = NodeApiSigner::sign($secret, 'POST', '/v1/node/hosting/create-identity', '1700000000', str_repeat('a',32), 'req1', 'idem1', '{"a":2}');
        $this->assertNotEquals($sig, $sig2);
    }

    public function testModifiedPathRejected(): void
    {
        $secret = 's3cret';
        $sig = NodeApiSigner::sign($secret, 'POST', '/v1/node/hosting/create-identity', '1700000000', str_repeat('a',32), 'req1', 'idem1', '{}');
        $sig2 = NodeApiSigner::sign($secret, 'POST', '/v1/node/hosting/suspend', '1700000000', str_repeat('a',32), 'req1', 'idem1', '{}');
        $this->assertNotEquals($sig, $sig2);
    }

    public function testModifiedMethodRejected(): void
    {
        $secret = 's3cret';
        $sig = NodeApiSigner::sign($secret, 'POST', '/v1/node/hosting/create-identity', '1700000000', str_repeat('a',32), 'req1', 'idem1', '{}');
        $sig2 = NodeApiSigner::sign($secret, 'GET', '/v1/node/hosting/create-identity', '1700000000', str_repeat('a',32), 'req1', 'idem1', '{}');
        $this->assertNotEquals($sig, $sig2);
    }

    public function testBuildHeadersContainsAll(): void
    {
        $headers = NodeApiSigner::buildHeaders(1, 'secret', 'POST', '/v1/node/hosting/create-identity', '{}', 'idem-123', 'req-abc');
        $this->assertArrayHasKey(NodeApiSigner::HEADER_NODE_ID, $headers);
        $this->assertArrayHasKey(NodeApiSigner::HEADER_SIGNATURE, $headers);
        $this->assertArrayHasKey(NodeApiSigner::HEADER_TIMESTAMP, $headers);
        $this->assertEquals('idem-123', $headers[NodeApiSigner::HEADER_IDEMPOTENCY_KEY]);
    }
}
