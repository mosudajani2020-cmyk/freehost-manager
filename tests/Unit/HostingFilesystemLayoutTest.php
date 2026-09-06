<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Isolation\HostingFilesystemLayout;

final class HostingFilesystemLayoutTest extends TestCase
{
    public function testFilesystemLayoutPaths(): void
    {
        $layout = new HostingFilesystemLayout('/srv/freehost/customers');
        $this->assertEquals('/srv/freehost/customers', $layout->getHostingRoot());
        $this->assertEquals('/srv/freehost/customers/fhm_1001', $layout->getCustomerRoot(1001));
        $this->assertEquals('/srv/freehost/customers/fhm_1001/public_html', $layout->getPublicHtml(1001));
        $this->assertEquals('/srv/freehost/customers/fhm_1001/logs', $layout->getLogsDirectory(1001));
        $this->assertEquals('/srv/freehost/customers/fhm_1001/tmp', $layout->getTmpDirectory(1001));
        $this->assertEquals('/srv/freehost/customers/fhm_1001/fpm', $layout->getPhpFpmPoolDirectory(1001));
    }

    public function testPathTraversalBlocked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $layout = new HostingFilesystemLayout('/srv/freehost/customers');
        $layout->getCustomerRoot(-1);
    }

    public function testAccountIsolationDifferentDirectories(): void
    {
        $layout = new HostingFilesystemLayout('/srv/freehost/customers');
        $rootA = $layout->getCustomerRoot(1001);
        $rootB = $layout->getCustomerRoot(1002);
        $this->assertNotEquals($rootA, $rootB);
        $this->assertStringContainsString('fhm_1001', $rootA);
        $this->assertStringContainsString('fhm_1002', $rootB);
    }
}