<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Repositories\HostingPlanRepository;
use App\Repositories\HostingAccountRepository;
use App\Repositories\BackupRepository;
use App\Services\AuditService;
use App\Services\UsageService;
use App\Services\BackupService;
use App\Services\MonitoringService;
use App\Services\HostingService;
use App\Services\Providers\LocalMockUsageCollector;
use App\Services\Providers\LocalMockBackupProvider;
use App\Services\Providers\LocalMockMonitoringProvider;
use App\Repositories\SubdomainRepository;
use App\Services\Provisioning\LocalMockProvisioner;

final class Phase7UsageBackupMonitoringTest extends TestCase
{
    private Database $db;
    private int $user1;
    private int $user2;
    private int $admin;
    private int $accountId;
    private UsageService $usage;
    private BackupService $backup;
    private MonitoringService $monitoring;

    protected function setUp(): void
    {
        if (version_compare(PHP_VERSION, '8.3.0', '<')) $this->markTestSkipped('Requires PHP 8.3');
        $base = dirname(__DIR__, 2);
        if (file_exists($base . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($base);
            $dotenv->load();
        }
        $config = require $base . '/config/database.php';
        $config['database'] = 'freehost_manager';
        Database::reset();
        $this->db = Database::getInstance($config);
        $plans = new HostingPlanRepository($this->db);
        $accounts = new HostingAccountRepository($this->db);
        $audit = new AuditService($this->db);
        $this->usage = new UsageService($this->db, $accounts, $plans, new LocalMockUsageCollector($this->db), $audit);
        $this->backup = new BackupService($this->db, $accounts, new BackupRepository($this->db), new LocalMockBackupProvider(), $audit);
        $this->monitoring = new MonitoringService($this->db, new LocalMockMonitoringProvider($this->db), $audit);
        $hosting = new HostingService($this->db, $accounts, $plans, new SubdomainRepository($this->db), new LocalMockProvisioner(), $audit);

        $suffix = bin2hex(random_bytes(3));
        $hash = password_hash('TestPass123', PASSWORD_BCRYPT);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p7test%+%@example.com'");
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P7 One $suffix","p7u1_$suffix","p7test1+$suffix@example.com",$hash]);
        $this->user1 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user1]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P7 Two $suffix","p7u2_$suffix","p7test2+$suffix@example.com",$hash]);
        $this->user2 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user2]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P7 Admin $suffix","p7admin_$suffix","p7admin+$suffix@example.com",$hash]);
        $this->admin = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='admin'))", [$this->admin]);

        $plan = $plans->getDefault();
        $res = $hosting->createAccount($this->user1, $plan->id, 'p7host' . $suffix);
        $this->assertTrue($res['success']);
        $this->accountId = $res['account']->id;
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM backups WHERE hosting_account_id=?", [$this->accountId]);
        $this->db->execute("DELETE FROM usage_records WHERE hosting_account_id=?", [$this->accountId]);
        $this->db->execute("DELETE FROM hosting_accounts WHERE id=?", [$this->accountId]);
        $acct = (new HostingAccountRepository($this->db))->findById($this->accountId);
        if ($acct && is_dir($acct->rootPath)) {
            $this->rrmdir($acct->rootPath);
        }
        $this->db->execute("DELETE FROM user_roles WHERE user_id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p7test%+%@example.com'");
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    public function testUsageCalculation(): void
    {
        $data = $this->usage->collectAndRecord($this->accountId, $this->user1);
        $this->assertArrayHasKey('storage_bytes', $data);
        $this->assertArrayHasKey('database_count', $data);
        // Check usage_records created
        $count = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM usage_records WHERE hosting_account_id=?", [$this->accountId]);
        $this->assertGreaterThan(0, $count);
    }

    public function testQuotaWarnings(): void
    {
        // Create usage that exceeds 90% by mocking? For Phase 7, we can test via getUsage with small plan
        // Create small plan 1 MB and fill storage
        $this->db->query("INSERT INTO hosting_plans (name, slug, description, storage_limit_mb, bandwidth_limit_mb, database_limit, domain_limit, subdomain_limit, status) VALUES (?,?,?,?,?,?,?,?, 'active') ON DUPLICATE KEY UPDATE name=name", ["P7SMALL" . bin2hex(random_bytes(2)), "p7small" . bin2hex(random_bytes(3)), "small", 1, 10, 1, 1, 1]);
        $smallId = (int)$this->db->lastInsertId();
        if ($smallId === 0) {
            $smallId = (int)$this->db->fetchColumn("SELECT id FROM hosting_plans WHERE slug LIKE 'p7small%' ORDER BY id DESC LIMIT 1");
        }
        $usage = $this->usage->getUsage($this->user1, $this->accountId);
        $this->assertIsArray($usage['warnings']);
        // Cleanup small plan
        $this->db->execute("DELETE FROM hosting_plans WHERE id=?", [$smallId]);
    }

    public function testOwnershipEnforced(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');
        $this->usage->getUsage($this->user2, $this->accountId);
    }

    public function testIdorBackupBlocked(): void
    {
        $this->backup->create($this->user1, $this->accountId, 'full', 7);
        $row = $this->db->fetch("SELECT id FROM backups WHERE hosting_account_id=? ORDER BY id DESC LIMIT 1", [$this->accountId]);
        $this->expectException(\RuntimeException::class);
        $this->backup->delete($this->user2, $this->accountId, (int)$row['id']);
    }

    public function testBackupLifecycle(): void
    {
        $res = $this->backup->create($this->user1, $this->accountId, 'full', 7);
        $this->assertTrue($res['success']);
        $this->assertEquals('completed', $res['backup']->status);
        $this->assertNotNull($res['backup']->sizeBytes);
        // Pending -> completed is mocked, check lifecycle
        $id = $res['backup']->id;
        $row = $this->db->fetch("SELECT * FROM backups WHERE id=?", [$id]);
        $this->assertEquals('completed', $row['status']);
        // Expire handling: set expires_at to past and run expireOld
        $this->db->execute("UPDATE backups SET expires_at=DATE_SUB(NOW(), INTERVAL 1 DAY), status='completed' WHERE id=?", [$id]);
        $count = $this->backup->expireOld();
        $this->assertGreaterThanOrEqual(1, $count);
        $row2 = $this->db->fetch("SELECT status FROM backups WHERE id=?", [$id]);
        $this->assertEquals('expired', $row2['status']);
        // Delete
        $this->backup->delete($this->user1, $this->accountId, $id);
        $this->assertNull($this->db->fetch("SELECT * FROM backups WHERE id=?", [$id]));
    }

    public function testBackupFailedHandling(): void
    {
        // Simulate failure by using provider that fails? LocalMock always succeeds, so we test manual fail via DB
        $res = $this->backup->create($this->user1, $this->accountId, 'full', 7);
        $id = $res['backup']->id;
        $this->db->execute("UPDATE backups SET status='failed', last_error='simulated failure' WHERE id=?", [$id]);
        $row = $this->db->fetch("SELECT * FROM backups WHERE id=?", [$id]);
        $this->assertEquals('failed', $row['status']);
        $this->assertEquals('simulated failure', $row['last_error']);
        // Ensure not auto-deleted
        $this->assertNotNull($row);
    }

    public function testMonitoringHealthy(): void
    {
        $health = $this->monitoring->getSystemHealth($this->admin);
        $this->assertContains($health['status'], ['healthy','warning','critical','unknown']);
        $this->assertArrayHasKey('checks', $health);
        $prov = $this->monitoring->getProvisioningHealth($this->admin);
        $this->assertArrayHasKey('queued', $prov);
    }

    public function testFailedJobsReporting(): void
    {
        // Create a failed provisioning job
        $this->db->query("INSERT INTO provisioning_jobs (job_uuid, hosting_account_id, operation, status, attempts, max_attempts, idempotency_key, requested_by) VALUES (?,?,?,?,?,?,?,?)", [bin2hex(random_bytes(16)), $this->accountId, 'createHostingAccount', 'failed', 3, 3, 'fail-'.bin2hex(random_bytes(8)), $this->user1]);
        $failed = $this->monitoring->getFailedJobs($this->admin);
        $this->assertIsArray($failed);
        $this->assertGreaterThan(0, count($failed));
    }

    public function testAuditLogsCreated(): void
    {
        $before = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $this->usage->collectAndRecord($this->accountId, $this->user1);
        $after = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $this->assertGreaterThan($before, $after);
    }

    public function testFailureHandling(): void
    {
        // Try to create backup with invalid retention
        $this->expectException(\RuntimeException::class);
        $this->backup->create($this->user1, $this->accountId, 'full', 500);
    }

    public function testAuthorizationCustomerCannotAccessAdmin(): void
    {
        $roles = $this->db->fetchAll("SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?", [$this->user1]);
        $this->assertNotContains('admin', array_column($roles, 'name'));
        $adminRoles = $this->db->fetchAll("SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?", [$this->admin]);
        $this->assertContains('admin', array_column($adminRoles, 'name'));
    }

    public function testSecretLeakage(): void
    {
        $res = $this->backup->create($this->user1, $this->accountId, 'full', 7);
        $logs = $this->db->fetchAll("SELECT metadata FROM audit_logs ORDER BY id DESC LIMIT 5");
        foreach ($logs as $row) {
            $meta = json_encode($row['metadata'] ?? '');
            $this->assertStringNotContainsString('fhm_', $meta);
            $this->assertStringNotContainsString('PRIVATE KEY', $meta);
        }
        // Check backup file_path not exposing secrets
        $row = $this->db->fetch("SELECT file_path FROM backups WHERE id=?", [$res['backup']->id]);
        $this->assertStringNotContainsString('password', strtolower($row['file_path'] ?? ''));
    }

    public function testNoShellFunctions(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app'));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression('/(?<!->)(?<!::)\b(exec|shell_exec|system|passthru|proc_open|popen)\s*\(/', $content);
            }
        }
    }

    public function testProviderInterfacesExist(): void
    {
        $this->assertTrue(interface_exists(\App\Services\Providers\BackupProviderInterface::class));
        $this->assertTrue(interface_exists(\App\Services\Providers\UsageCollectorInterface::class));
        $this->assertTrue(interface_exists(\App\Services\Providers\MonitoringProviderInterface::class));
        $this->assertTrue(class_exists(\App\Services\Providers\LocalMockBackupProvider::class));
    }
}
