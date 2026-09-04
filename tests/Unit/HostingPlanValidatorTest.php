<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\HostingPlanValidator;
use App\Validators\HostingAccountValidator;
use PHPUnit\Framework\TestCase;

final class HostingPlanValidatorTest extends TestCase
{
    public function testValidPlan(): void
    {
        $errors = HostingPlanValidator::validate([
            'name' => 'Test',
            'slug' => 'test-plan',
            'storage_limit_mb' => 500,
            'bandwidth_limit_mb' => 5120,
            'database_limit' => 2,
            'domain_limit' => 1,
            'subdomain_limit' => 2,
        ]);
        $this->assertEmpty($errors);
    }

    public function testNegativeQuotaRejected(): void
    {
        $errors = HostingPlanValidator::validate([
            'name' => 'Test',
            'slug' => 'test',
            'storage_limit_mb' => -1,
            'bandwidth_limit_mb' => -5,
            'database_limit' => -1,
            'domain_limit' => -1,
            'subdomain_limit' => -1,
        ]);
        $this->assertArrayHasKey('storage_limit_mb', $errors);
        $this->assertArrayHasKey('bandwidth_limit_mb', $errors);
        $this->assertArrayHasKey('database_limit', $errors);
    }

    public function testInvalidSlug(): void
    {
        $errors = HostingPlanValidator::validate([
            'name' => 'Test',
            'slug' => 'INVALID SLUG!',
            'storage_limit_mb' => 500,
            'bandwidth_limit_mb' => 5120,
            'database_limit' => 2,
            'domain_limit' => 1,
            'subdomain_limit' => 2,
        ]);
        $this->assertArrayHasKey('slug', $errors);
    }

    public function testUsernameValidation(): void
    {
        $this->assertEmpty(HostingAccountValidator::validateCreate(['username'=>'abc123','plan_id'=>1]));
        $errors = HostingAccountValidator::validateCreate(['username'=>'AB','plan_id'=>1]);
        $this->assertArrayHasKey('username', $errors);
        $errors = HostingAccountValidator::validateCreate(['username'=>'bad-user!','plan_id'=>1]);
        $this->assertArrayHasKey('username', $errors);
        // SQL injection attempt
        $errors = HostingAccountValidator::validateCreate(['username'=>"' OR 1=1 --",'plan_id'=>1]);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testSubdomainValidation(): void
    {
        $this->assertEmpty(HostingAccountValidator::validateSubdomain('my-site1'));
        $errors = HostingAccountValidator::validateSubdomain('-bad');
        $this->assertArrayHasKey('subdomain', $errors);
        $errors = HostingAccountValidator::validateSubdomain('bad-');
        $this->assertArrayHasKey('subdomain', $errors);
        $errors = HostingAccountValidator::validateSubdomain('www');
        $this->assertArrayHasKey('subdomain', $errors);
        $errors = HostingAccountValidator::validateSubdomain('sub..domain');
        $this->assertNotEmpty($errors);
        // XSS
        $errors = HostingAccountValidator::validateSubdomain('<script>');
        $this->assertArrayHasKey('subdomain', $errors);
        // Traversal
        $errors = HostingAccountValidator::validateSubdomain('../etc');
        $this->assertArrayHasKey('subdomain', $errors);
    }
}
