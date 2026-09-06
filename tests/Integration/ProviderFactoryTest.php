<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\Providers\ProviderFactory;

/**
 * P1 — ProviderFactory: environment/config-driven provider selection.
 *
 * The factory reads per-kind env vars (defaulting to 'local_mock') and fails
 * loudly for unsupported providers — it never silently falls back to an
 * unexpected provider.
 */
final class ProviderFactoryTest extends TestCase
{
    private const KEYS = [
        'PROVISIONING_PROVIDER', 'DNS_PROVIDER', 'SSL_PROVIDER', 'BACKUP_PROVIDER',
        'PAYMENT_PROVIDER', 'USAGE_PROVIDER', 'MONITORING_PROVIDER', 'DOMAIN_PROVIDER',
    ];

    protected function setUp(): void
    {
        // Every test starts from a clean default environment regardless of the
        // order phpunit executes tests (executionOrder includes 'defects').
        foreach (self::KEYS as $key) {
            unset($_ENV[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::KEYS as $key) {
            unset($_ENV[$key]);
        }
    }

    public function testDefaultProvidersResolveToLocalMock(): void
    {
        $this->assertInstanceOf(\App\Services\Provisioning\LocalMockProvisioner::class, ProviderFactory::provisioner());
        $this->assertInstanceOf(\App\Services\Providers\LocalMockDnsProvider::class, ProviderFactory::dns());
        $this->assertInstanceOf(\App\Services\Providers\LocalMockCertificateProvider::class, ProviderFactory::ssl());
        $this->assertInstanceOf(\App\Services\Providers\LocalMockBackupProvider::class, ProviderFactory::backup());
        $this->assertInstanceOf(\App\Services\Providers\LocalMockPaymentGateway::class, ProviderFactory::payment());
        $this->assertInstanceOf(\App\Services\Providers\LocalMockDomainProvider::class, ProviderFactory::domain());
    }

    public function testConfiguredNameSelected(): void
    {
        $_ENV['DNS_PROVIDER'] = 'local_mock';
        $this->assertInstanceOf(\App\Services\Providers\LocalMockDnsProvider::class, ProviderFactory::dns());
    }

    public function testUnsupportedProviderFailsLoudly(): void
    {
        $_ENV['PROVISIONING_PROVIDER'] = 'does_not_exist';
        try {
            ProviderFactory::provisioner();
            $this->fail('Expected RuntimeException for unsupported provider');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unsupported', $e->getMessage());
        }
    }

    public function testResolvedIdReflectsEnvironment(): void
    {
        $_ENV['PROVISIONING_PROVIDER'] = 'local_mock';
        $this->assertEquals('local_mock', ProviderFactory::resolvedId('provisioner'));
    }
}