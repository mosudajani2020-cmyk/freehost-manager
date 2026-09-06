<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\Isolation\CustomerSystemIdentity;
use App\Services\Isolation\HostingFilesystemLayout;
use App\Services\Isolation\ResourceIsolationPolicy;
use App\Services\Isolation\LocalMockIsolationProvider;
use App\Services\Isolation\LinuxIsolationProvider;

final class HostingNodeIsolationTest extends TestCase
{
    public function testLocalMockIsolationProviderSucceeds(): void
    {
        $provider = new LocalMockIsolationProvider();
        $identity = new CustomerSystemIdentity(5001);
        $layout = new HostingFilesystemLayout(sys_get_temp_dir() . '/fhm_test_hosting');
        $policy = ResourceIsolationPolicy::defaultPolicy();

        $this->assertTrue($provider->createCustomerIdentity($identity));
        $this->assertTrue($provider->createCustomerFilesystem($identity, $layout));
        $this->assertTrue($provider->applyFilesystemPermissions($identity, $layout));
        $this->assertTrue($provider->createPhpFpmPool($identity, $layout, $policy));
        $this->assertTrue($provider->applyResourcePolicy($identity, $policy));
        $this->assertTrue($provider->removePhpFpmPool($identity));
        $this->assertTrue($provider->removeCustomerIdentity($identity));
    }

    public function testLinuxIsolationProviderThrowsOnNonLinux(): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $this->markTestSkipped('Skipped on Linux runtime.');
        }

        $provider = new LinuxIsolationProvider();
        $identity = new CustomerSystemIdentity(5001);
        $layout = new HostingFilesystemLayout('/srv/freehost/customers');
        $policy = ResourceIsolationPolicy::defaultPolicy();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LinuxIsolationProvider can only be executed on a Linux hosting node runtime.');
        $provider->createCustomerIdentity($identity);
    }
}