<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Isolation\CustomerSystemIdentity;
use App\Services\Isolation\HostingFilesystemLayout;
use App\Services\Isolation\ResourceIsolationPolicy;
use App\Services\Isolation\PhpFpmPoolConfigGenerator;

final class PhpFpmPoolConfigGeneratorTest extends TestCase
{
    public function testPhpFpmPoolConfigGeneration(): void
    {
        $identity = new CustomerSystemIdentity(1001);
        $layout = new HostingFilesystemLayout('/srv/freehost/customers');
        $policy = ResourceIsolationPolicy::defaultPolicy();

        $config = str_replace("\r\n", "\n", PhpFpmPoolConfigGenerator::generate($identity, $layout, $policy));

        $this->assertStringContainsString('[fhm_1001]', $config);
        $this->assertStringContainsString('user = fhm_1001', $config);
        $this->assertStringContainsString('group = fhm_1001', $config);
        $this->assertStringContainsString('listen = /run/php/php8.3-fpm-fhm_1001.sock', $config);
        $this->assertStringContainsString('php_admin_value[open_basedir] = /srv/freehost/customers/fhm_1001/:/tmp/', $config);
        $this->assertStringContainsString('clear_env = yes', $config);
    }
}