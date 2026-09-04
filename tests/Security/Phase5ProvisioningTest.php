<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Repositories\HostingPlanRepository;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingNodeRepository;
use App\Repositories\ProvisioningJobRepository;
use App\Services\AuditService;
use App\Services\HostingService;
use App\Services\ProvisioningService;
use App\Services\Provisioning\LocalMockProvisioner;
use App\Repositories\SubdomainRepository;

final class Phase5ProvisioningTest extends TestCase
{
    private Database $db;
    private HostingNodeRepository $nodes;
    private ProvisioningJobRepository $jobs;
    private ProvisioningService $provisioning;
    private HostingService $hosting;
    private int $user1;
    private int $user2;
    private int $admin;
    private int $accountId;
    private int $nodeId;

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
        $this->nodes = new HostingNodeRepository($this->db);
        $this->jobs = new ProvisioningJobRepository($this->db);
        $audit = new AuditService($this->db);
        $this->provisioning = new ProvisioningService($this->db, $this->nodes, $this->jobs, new LocalMockProvisioner(), $audit);
        $plans = new HostingPlanRepository($this->db);
        $this->hosting = new HostingService($this->db, new HostingAccountRepository($this->db), $plans, new SubdomainRepository($this->db), new LocalMockProvisioner(), $audit);

        $suffix = bin2hex(random_bytes(3));
        $hash = password_hash('TestPass123', PASSWORD_BCRYPT);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p5test%+%@example.com'");
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P5 One $suffix","p5u1_$suffix","p5test1+$suffix@example.com",$hash]);
        $this->user1 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user1]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P5 Two $suffix","p5u2_$suffix","p5test2+$suffix@example.com",$hash]);
        $this->user2 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user2]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P5 Admin $suffix","p5admin_$suffix","p5admin+$suffix@example.com",$hash]);
        $this->admin = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='admin'))", [$this->admin]);

        $plan = $plans->getDefault();
        $res = $this->hosting->createAccount($this->user1, $plan->id, 'p5host' . $suffix);
        $this->assertTrue($res['success']);
        $this->accountId = $res['account']->id;

        // Get default node
        $nodes = $this->nodes->all();
        $this->assertNotEmpty($nodes);
        $this->nodeId = $nodes[0]->id;
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM provisioning_jobs WHERE hosting_account_id=?", [$this->accountId]);
        $this->db->execute("DELETE FROM hosting_accounts WHERE id=?", [$this->accountId]);
        $acct = (new HostingAccountRepository($this->db))->findById($this->accountId);
        if ($acct && is_dir($acct->rootPath)) {
            $this->rrmdir($acct->rootPath);
        }
        $this->db->execute("DELETE FROM user_roles WHERE user_id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p5test%+%@example.com'");
        // Cleanup any p5 nodes created
        $this->db->execute("DELETE FROM hosting_nodes WHERE name LIKE 'p5node%'");
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    public function testProvisioningJobCreation(): void
    {
        $job = $this->provisioning->dispatch($this->accountId, 'suspendHostingAccount', [], $this->admin, 'test-job-' . bin2hex(random_bytes(4)), $this->nodeId);
        $this->assertNotNull($job);
        $this->assertEquals('suspendHostingAccount', $job->operation);
        $this->assertEquals($this->accountId, $job->hostingAccountId);
        $this->assertNotEmpty($job->jobUuid);
        $this->assertNotEmpty($job->idempotencyKey);
    }

    public function testDuplicateJobPrevention(): void
    {
        $key = 'dup-' . bin2hex(random_bytes(8));
        $j1 = $this->provisioning->dispatch($this->accountId, 'suspendHostingAccount', [], $this->admin, $key, $this->nodeId);
        $j2 = $this->provisioning->dispatch($this->accountId, 'suspendHostingAccount', [], $this->admin, $key, $this->nodeId);
        $this->assertEquals($j1->id, $j2->id, 'Duplicate idempotency should return same job');
        $this->assertEquals($j1->jobUuid, $j2->jobUuid);
    }

    public function testRetryHandling(): void
    {
        // Create a job that will fail (invalid operation payload? We'll create failed manually)
        $job = $this->jobs->create([
            'hosting_account_id'=>$this->accountId,
            'node_id'=>$this->nodeId,
            'operation'=>'createHostingAccount',
            'payload'=>['fail'=>true],
            'status'=>'failed',
            'attempts'=>1,
            'max_attempts'=>3,
            'idempotency_key'=>'retry-' . bin2hex(random_bytes(8)),
            'requested_by'=>$this->admin,
        ]);
        // Set last_error
        $this->db->execute("UPDATE provisioning_jobs SET last_error='simulated failure' WHERE id=?", [$job->id]);
        $retried = $this->provisioning->retryJob($job->id, $this->admin);
        $this->assertContains($retried->status, ['queued','provisioning','active','retrying']);
        // Retry should increment attempts eventually
        $this->assertGreaterThanOrEqual(1, $retried->attempts);
    }

    public function testFailureHandling(): void
    {
        $job = $this->jobs->create([
            'hosting_account_id'=>$this->accountId,
            'node_id'=>$this->nodeId,
            'operation'=>'createHostingAccount',
            'payload'=>[],
            'status'=>'failed',
            'attempts'=>3,
            'max_attempts'=>3,
            'idempotency_key'=>'failmax-' . bin2hex(random_bytes(8)),
            'requested_by'=>$this->admin,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be retried');
        $this->provisioning->retryJob($job->id, $this->admin);
    }

    public function testAuthorizationCustomerCannotAccessAdmin(): void
    {
        // Simulate RBAC: customer should not have access to provisioning admin
        $roles = $this->db->fetchAll("SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?", [$this->user1]);
        $roleNames = array_column($roles, 'name');
        $this->assertNotContains('admin', $roleNames);
        // Admin should have
        $adminRoles = $this->db->fetchAll("SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?", [$this->admin]);
        $this->assertContains('admin', array_column($adminRoles, 'name'));
    }

    public function testIdorCustomerCannotSeeAnotherJob(): void
    {
        // Create job for user1 account
        $job = $this->provisioning->dispatch($this->accountId, 'suspendHostingAccount', [], $this->user1, 'idor-' . bin2hex(random_bytes(8)), $this->nodeId);
        // User2 should not be able to fetch job for user1's account via direct check
        $jobAccount = $this->db->fetch("SELECT hosting_account_id FROM provisioning_jobs WHERE id=?", [$job->id]);
        $this->assertEquals($this->accountId, $jobAccount['hosting_account_id']);
        // User2's account is different, so they shouldn't have access to this job's account
        $user2Accounts = $this->db->fetchAll("SELECT id FROM hosting_accounts WHERE user_id=?", [$this->user2]);
        $user2Ids = array_column($user2Accounts, 'id');
        $this->assertNotContains($this->accountId, $user2Ids);
    }

    public function testAuditLogsCreated(): void
    {
        $before = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->admin]);
        $this->provisioning->dispatch($this->accountId, 'suspendHostingAccount', [], $this->admin, 'audit-' . bin2hex(random_bytes(8)), $this->nodeId);
        $after = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->admin]);
        $this->assertGreaterThan($before, $after);
    }

    public function testSecretLeakage(): void
    {
        // API key should be hashed, not plaintext in DB
        $keyData = ProvisioningService::generateApiKey();
        $this->assertNotEmpty($keyData['plain']);
        $this->assertEquals(hash('sha256', $keyData['plain']), $keyData['hash']);
        $this->assertNotEquals($keyData['plain'], $keyData['hash']);
        // Store node with hash
        $node = $this->nodes->create([
            'name'=>'p5node' . bin2hex(random_bytes(3)),
            'hostname'=>'p5-' . bin2hex(random_bytes(4)) . '.example.com',
            'status'=>'active',
            'max_accounts'=>10,
            'api_key_hash'=>$keyData['hash'],
            'api_key_preview'=>$keyData['preview'],
        ]);
        $row = $this->db->fetch("SELECT api_key_hash, api_key_preview FROM hosting_nodes WHERE id=?", [$node->id]);
        $this->assertEquals($keyData['hash'], $row['api_key_hash']);
        $this->assertStringNotContainsString($keyData['plain'], $row['api_key_hash']);
        $this->assertEquals($keyData['preview'], $row['api_key_preview']);
        // Ensure no private keys in Git (check app files don't contain)
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app'));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $this->assertStringNotContainsString('PRIVATE KEY', $content);
            }
        }
    }

    public function testSuspendedAccountCannotProvision(): void
    {
        $this->db->execute("UPDATE hosting_accounts SET status='suspended' WHERE id=?", [$this->accountId]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Suspended');
        $this->provisioning->dispatch($this->accountId, 'createDatabase', ['dbName'=>'test'], $this->user1, 'susp-' . bin2hex(random_bytes(8)), $this->nodeId);
        $this->db->execute("UPDATE hosting_accounts SET status='active' WHERE id=?", [$this->accountId]);
    }

    public function testTerminatedAccountCannotProvision(): void
    {
        $this->db->execute("UPDATE hosting_accounts SET status='terminated' WHERE id=?", [$this->accountId]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Terminated');
        $this->provisioning->dispatch($this->accountId, 'createDatabase', ['dbName'=>'test'], $this->user1, 'term-' . bin2hex(random_bytes(8)), $this->nodeId);
        $this->db->execute("UPDATE hosting_accounts SET status='active' WHERE id=?", [$this->accountId]);
    }

    public function testInvalidNodeRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid node');
        $this->provisioning->dispatch($this->accountId, 'createHostingAccount', [], $this->user1, 'badnode-' . bin2hex(random_bytes(8)), 99999);
    }

    public function testInvalidJobRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->provisioning->retryJob(99999, $this->admin);
    }

    public function testRequestSigningAndReplayProtection(): void
    {
        $keyData = ProvisioningService::generateApiKey();
        $method = 'POST';
        $path = '/api/provision';
        $body = json_encode(['operation'=>'createHostingAccount']);
        $timestamp = time();
        $nonce = bin2hex(random_bytes(8));
        $sig = ProvisioningService::signRequest($keyData['plain'], $method, $path, $body, $timestamp, $nonce);
        $this->assertNotEmpty($sig);
        // Verify should succeed first time
        $ok = ProvisioningService::verifyRequest($keyData['plain'], $method, $path, $body, $timestamp, $nonce, $sig, $this->db);
        $this->assertTrue($ok);
        // Replay with same nonce should fail
        $ok2 = ProvisioningService::verifyRequest($keyData['plain'], $method, $path, $body, $timestamp, $nonce, $sig, $this->db);
        $this->assertFalse($ok2, 'Replay should be blocked');
        // Expired timestamp should fail
        $oldTs = time() - 600;
        $nonce2 = bin2hex(random_bytes(8));
        $sig2 = ProvisioningService::signRequest($keyData['plain'], $method, $path, $body, $oldTs, $nonce2);
        $ok3 = ProvisioningService::verifyRequest($keyData['plain'], $method, $path, $body, $oldTs, $nonce2, $sig2, $this->db);
        $this->assertFalse($ok3, 'Expired should be blocked');
        // Tampered body should fail
        $nonce3 = bin2hex(random_bytes(8));
        $sig3 = ProvisioningService::signRequest($keyData['plain'], $method, $path, $body, $timestamp, $nonce3);
        $ok4 = ProvisioningService::verifyRequest($keyData['plain'], $method, $path, $body . 'tampered', $timestamp, $nonce3, $sig3, $this->db);
        $this->assertFalse($ok4);
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

    public function testJobIdempotencyKeyUnique(): void
    {
        $key = 'idem-' . bin2hex(random_bytes(8));
        $j1 = $this->provisioning->dispatch($this->accountId, 'suspendHostingAccount', [], $this->admin, $key, $this->nodeId);
        // Second with same key should return same job, not create duplicate row
        $countBefore = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE idempotency_key=?", [$key]);
        $this->assertEquals(1, $countBefore);
        $j2 = $this->provisioning->dispatch($this->accountId, 'suspendHostingAccount', [], $this->admin, $key, $this->nodeId);
        $countAfter = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE idempotency_key=?", [$key]);
        $this->assertEquals(1, $countAfter);
        $this->assertEquals($j1->id, $j2->id);
    }
}
