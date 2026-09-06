<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Isolation\ResourceIsolationPolicy;

final class ResourceIsolationPolicyTest extends TestCase
{
    public function testDefaultPolicyValues(): void
    {
        $policy = ResourceIsolationPolicy::defaultPolicy();
        $this->assertEquals(256, $policy->memoryLimitMb);
        $this->assertEquals(100, $policy->cpuLimitPercent);
        $this->assertEquals(30, $policy->processLimit);
        $this->assertEquals(1024, $policy->diskQuotaMb);
    }

    public function testPolicyToArray(): void
    {
        $policy = ResourceIsolationPolicy::defaultPolicy();
        $arr = $policy->toArray();
        $this->assertArrayHasKey('memory_limit_mb', $arr);
        $this->assertArrayHasKey('disk_quota_mb', $arr);
        $this->assertEquals(256, $arr['memory_limit_mb']);
    }
}