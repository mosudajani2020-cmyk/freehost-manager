<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Isolation\CustomerSystemIdentity;

final class CustomerSystemIdentityTest extends TestCase
{
    public function testDeterministicIdentityMapping(): void
    {
        $id1 = new CustomerSystemIdentity(1842);
        $this->assertEquals('fhm_1842', $id1->username());
        $this->assertEquals('fhm_1842', $id1->groupName());
        $this->assertEquals(21842, $id1->uid());
        $this->assertEquals(21842, $id1->gid());
        $this->assertEquals(1842, $id1->accountId());
    }

    public function testInvalidAccountIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomerSystemIdentity(0);
    }

    public function testUsernameValidation(): void
    {
        $this->assertTrue(CustomerSystemIdentity::validateUsername('fhm_1001'));
        $this->assertTrue(CustomerSystemIdentity::validateUsername('fhm_99999'));
        $this->assertFalse(CustomerSystemIdentity::validateUsername('root'));
        $this->assertFalse(CustomerSystemIdentity::validateUsername('fhm_0'));
        $this->assertFalse(CustomerSystemIdentity::validateUsername('fhm_1001; rm -rf /'));
        $this->assertFalse(CustomerSystemIdentity::validateUsername('user$1'));
    }
}