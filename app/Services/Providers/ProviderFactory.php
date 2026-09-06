<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Helpers\Database;
use App\Services\Provisioning\HostingProvisionerInterface;
use App\Services\Provisioning\LocalMockProvisioner;

/**
 * ProviderFactory — environment/config-driven provider selection (P1).
 *
 * Removes hard-coded LocalMock selections from services and controllers. Each
 * provider kind reads its own env var and defaulting to 'local_mock' for local
 * development, so no real infrastructure credentials are required to run the
 * application or the test suite.
 *
 * Configuration (in web .env):
 *   PROVISIONING_PROVIDER, DNS_PROVIDER, SSL_PROVIDER, BACKUP_PROVIDER,
 *   PAYMENT_PROVIDER, USAGE_PROVIDER, MONITORING_PROVIDER, DOMAIN_PROVIDER
 *
 * An unsupported/unknown provider name fails loudly — the factory never silently
 * falls back to an unexpected provider.
 */
final class ProviderFactory
{
    private const REGISTRY = [
        'provisioner' => ['local_mock' => LocalMockProvisioner::class],
        'dns'         => ['local_mock' => LocalMockDnsProvider::class],
        'ssl'         => ['local_mock' => LocalMockCertificateProvider::class],
        'backup'      => ['local_mock' => LocalMockBackupProvider::class],
        'payment'     => ['local_mock' => LocalMockPaymentGateway::class],
        'usage'       => ['local_mock' => LocalMockUsageCollector::class],
        'monitoring'  => ['local_mock' => LocalMockMonitoringProvider::class],
        'domain'      => ['local_mock' => LocalMockDomainProvider::class],
    ];

    private const ENV_VARS = [
        'provisioner' => 'PROVISIONING_PROVIDER',
        'dns'         => 'DNS_PROVIDER',
        'ssl'         => 'SSL_PROVIDER',
        'backup'      => 'BACKUP_PROVIDER',
        'payment'     => 'PAYMENT_PROVIDER',
        'usage'       => 'USAGE_PROVIDER',
        'monitoring'  => 'MONITORING_PROVIDER',
        'domain'      => 'DOMAIN_PROVIDER',
    ];

    private const NEEDS_DB = ['usage', 'monitoring'];

    public static function provisioner(): HostingProvisionerInterface
    {
        return self::create('provisioner');
    }

    public static function dns(): DnsProviderInterface
    {
        return self::create('dns');
    }

    public static function ssl(): CertificateProviderInterface
    {
        return self::create('ssl');
    }

    public static function backup(): BackupProviderInterface
    {
        return self::create('backup');
    }

    public static function payment(): PaymentGatewayInterface
    {
        return self::create('payment');
    }

    public static function usage(): UsageCollectorInterface
    {
        return self::create('usage');
    }

    public static function monitoring(): MonitoringProviderInterface
    {
        return self::create('monitoring');
    }

    public static function domain(): DomainProviderInterface
    {
        return self::create('domain');
    }

    /**
     * Instantiate the configured provider for a kind.
     *
     * @throws \RuntimeException when the configured provider is not supported.
     */
    public static function create(string $kind, ?Database $db = null): object
    {
        if (!isset(self::REGISTRY[$kind], self::ENV_VARS[$kind])) {
            throw new \InvalidArgumentException('Unknown provider kind: ' . $kind);
        }

        $name = strtolower(trim((string)($_ENV[self::ENV_VARS[$kind]] ?? 'local_mock')));
        if ($name === '') {
            $name = 'local_mock';
        }

        $class = self::REGISTRY[$kind][$name] ?? null;
        if ($class === null) {
            throw new \RuntimeException(
                'Unsupported ' . $kind . ' provider "' . $name . '". ' .
                'Configure a supported provider in .env (see ProviderFactory).'
            );
        }

        if (in_array($kind, self::NEEDS_DB, true)) {
            return new $class($db ?? Database::getInstance());
        }
        return new $class();
    }

    /** The provider id currently resolved for a kind (display/logging only). */
    public static function resolvedId(string $kind): string
    {
        if (!isset(self::ENV_VARS[$kind])) {
            throw new \InvalidArgumentException('Unknown provider kind: ' . $kind);
        }
        $name = strtolower(trim((string)($_ENV[self::ENV_VARS[$kind]] ?? 'local_mock')));
        return $name === '' ? 'local_mock' : $name;
    }
}