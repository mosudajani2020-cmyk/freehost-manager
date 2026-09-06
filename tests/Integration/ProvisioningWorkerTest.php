<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Repositories\HostingPlanRepository;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingNodeRepository;
use App\Repositories\ProvisioningJobRepository;
use App\Services\AuditService;
use App\Services\HostingService;
use App\Services\ProvisioningService;
use App\Services\Provisioning\HostingProvisionerInterface;
use App\Services\Provisioning\ProvisionResult;
use App\Services\Provisioning\LocalMockProvisioner;
use App\Services\Worker\ProvisioningWorker;
use App\Repositories\SubdomainRepository;

/**
 * P1 — provisioning queue claim / worker foundation.
 *
 * These tests exercise the atomic queue claim (SELECT ... FOR UPDATE SKIP LOCKED
 * in a short transaction — the row lock is never held during the operation),
 * worker lease ownership, retry/backoff/max-attempts, and stale-lease recovery.
 *
 * Each test uses its own worker and job so no cross-test interleaving is possible.
 */
final class ProvisioningWorkerTest extends TestCase
{
    private Database $db;
    private HostingNodeRepository $nodes;
    private ProvisioningJobRepository $jobs;
    private ProvisioningService $provisioning;
    private HostingService $hosting;
    private int $user;
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
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["W One $suffix","wu1_$suffix","p1workertest1+$suffix@example.com",$hash]);
        $this->user = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["W Admin $suffix","wadmin_$suffix","p1workertesta+$suffix@example.com",$hash]);
        $this->admin = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='admin'))", [$this->admin]);

        $plan = $plans->getDefault();
        $res = $this->hosting->createAccount($this->user, $plan->id, 'p1workhost' . $suffix);
        $this->assertTrue($res['success']);
        $this->accountId = $res['account']->id;

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
        $this->db->execute("DELETE FROM user_roles WHERE user_id IN (?,?)", [$this->user,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE id IN (?,?)", [$this->user,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p1workertest%+%@example.com'");
        $this->db->execute("DELETE FROM hosting_nodes WHERE name LIKE 'p1worknode%'");
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    private function makeJob(string $operation, array $payload = [], string $status = 'pending', int $attempts = 0): \App\Models\ProvisioningJob
    {
        return $this->jobs->create([
            'hosting_account_id'=>$this->accountId,
            'node_id'=>$this->nodeId,
            'operation'=>$operation,
            'payload'=>$payload,
            'status'=>$status,
            'attempts'=>$attempts,
            'max_attempts'=>3,
            'idempotency_key'=>'w-' . bin2hex(random_bytes(8)),
            'requested_by'=>$this->admin,
        ]);
    }

    private function worker(string $id, array $options = []): ProvisioningWorker
    {
        return new ProvisioningWorker($this->db, $this->jobs, $this->provisioning, array_merge(['once'=>true, 'log_file'=>null], $options), $id);
    }

    public function testClaimIsExclusiveAndSetsLease(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $j = $this->jobs->claimNext('worker-A', 120);
        $this->assertNotNull($j);
        $this->assertEquals($job->id, $j->id);
        $this->assertEquals('provisioning', $j->status);
        $this->assertEquals('worker-A', $j->workerId);
        $this->assertEquals(1, $j->attempts);
        $this->assertNotNull($j->claimedAt);
        $this->assertNotNull($j->heartbeatAt);

        // Second claim must not return the same (already provisioning) job.
        $this->assertNull($this->jobs->claimNext('worker-B', 120));
    }

    public function testCompletedJobNotClaimable(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com'], 'active', 1);
        $this->assertNull($this->jobs->claimNext('worker-A', 120));
        $this->assertNotNull($job);
    }

    public function testFreshClaimNotStaleRecovered(): void
    {
        $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $this->jobs->claimNext('worker-A', 120);
        // Fresh claim has not exceeded lease, so recovery must reclaim nothing.
        $this->assertEquals(0, $this->jobs->recoverStale('worker-B', 120));
    }

    public function testStaleClaimIsRecoveredAndReclaimed(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $this->jobs->claimNext('worker-A', 120);
        // Force the claim to look stale.
        $this->db->execute("UPDATE provisioning_jobs SET claimed_at=NOW() - INTERVAL 600 SECOND, heartbeat_at=NOW() - INTERVAL 600 SECOND WHERE id=?", [$job->id]);
        $this->assertEquals(1, $this->jobs->recoverStale('worker-B', 120));
        $claimed = $this->jobs->findById($job->id);
        $this->assertEquals('provisioning', $claimed->status);
        $this->assertEquals('worker-B', $claimed->workerId);
    }

    public function testRecoveryDoesNotDoubleExecute(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $this->jobs->claimNext('worker-A', 120);
        $this->db->execute("UPDATE provisioning_jobs SET claimed_at=NOW() - INTERVAL 600 SECOND WHERE id=?", [$job->id]);
        $this->assertEquals(1, $this->jobs->recoverStale('worker-B', 120));
        // A second recovery pass must find nothing (already reclaimed by B).
        $this->assertEquals(0, $this->jobs->recoverStale('worker-C', 120));
    }

    public function testOwnershipEnforcedOnCompleteFailRequeue(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $this->jobs->claimNext('worker-A', 120);

        // Another worker must not be able to act on a job it does not own.
        $this->jobs->completeClaimed($job->id, 'worker-B'); // no-op (WHERE worker_id mismatch)
        $still = $this->jobs->findById($job->id);
        $this->assertEquals('provisioning', $still->status);
        $this->assertEquals('worker-A', $still->workerId);

        // executeClaimedJob from a non-owner must throw.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not claimed');
        $this->provisioning->executeClaimedJob($job->id, 'worker-B');
    }

    public function testClaimIncrementsAttemptsOnlyOncePerClaim(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $this->jobs->claimNext('worker-A', 120);
        $claimed = $this->jobs->findById($job->id);
        $this->assertEquals(1, $claimed->attempts);

        // Recovering a stale job increments again (new execution).
        $this->db->execute("UPDATE provisioning_jobs SET claimed_at=NOW() - INTERVAL 600 SECOND WHERE id=?", [$job->id]);
        $this->jobs->recoverStale('worker-B', 120);
        $recovered = $this->jobs->findById($job->id);
        $this->assertEquals(2, $recovered->attempts);
    }

    public function testExecuteClaimedJobCompletesActive(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'ok','fullDomain'=>'ok.example.com']);
        $this->jobs->claimNext('worker-A', 120);
        $result = $this->provisioning->executeClaimedJob($job->id, 'worker-A');
        $this->assertEquals('active', $result->status);
        $this->assertNull($result->workerId);
        $this->assertNotNull($result->completedAt);
    }

    public function testUnexpectedExecutionFailureRequeuesForRetry(): void
    {
        // A failing provisioner surfaces a retryable failure.
        $failing = new FailingProvisioner();
        $service = new ProvisioningService($this->db, $this->nodes, $this->jobs, $failing, new AuditService($this->db));
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'fail','fullDomain'=>'fail.example.com']);
        $this->jobs->claimNext('worker-A', 120);
        $result = $service->executeClaimedJob($job->id, 'worker-A', 5, 300);
        $this->assertEquals('retrying', $result->status);
        $this->assertNull($result->workerId);
        $this->assertNotNull($result->nextAttemptAt, 'backoff gate should be set');
        $this->assertStringContainsString('boom', (string)$result->lastError);
        // Attempts reflect one claim.
        $this->assertEquals(1, $result->attempts);
    }

    public function testFinalFailureAfterMaxAttempts(): void
    {
        $failing = new FailingProvisioner();
        $service = new ProvisioningService($this->db, $this->nodes, $this->jobs, $failing, new AuditService($this->db));
        $job = $this->jobs->create([
            'hosting_account_id'=>$this->accountId,
            'node_id'=>$this->nodeId,
            'operation'=>'createSubdomain',
            'payload'=>['subdomain'=>'fail','fullDomain'=>'fail.example.com'],
            'status'=>'pending',
            'attempts'=>2, // starting near the cap so this claim reaches max
            'max_attempts'=>3,
            'idempotency_key'=>'w-max-' . bin2hex(random_bytes(8)),
            'requested_by'=>$this->admin,
        ]);
        $this->jobs->claimNext('worker-A', 120); // attempts -> 3
        $result = $service->executeClaimedJob($job->id, 'worker-A', 5, 300);
        $this->assertEquals('failed', $result->status);
        $this->assertNull($result->workerId);
        $this->assertNotNull($result->completedAt);
    }

    public function testWorkerOnceRunsSingleJob(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $worker = $this->worker('worker-once');
        $this->assertEquals(0, $worker->run());
        $this->assertEquals('active', $this->jobs->findById($job->id)->status);
    }

    public function testWorkerOnceWithNoJobsExitsCleanly(): void
    {
        $worker = $this->worker('worker-idle');
        $this->assertEquals(0, $worker->run());
    }

    public function testHeartbeatRefreshesLease(): void
    {
        $job = $this->makeJob('createSubdomain', ['subdomain'=>'a','fullDomain'=>'a.example.com']);
        $this->jobs->claimNext('worker-A', 120);
        $this->db->execute("UPDATE provisioning_jobs SET heartbeat_at=NOW() - INTERVAL 500 SECOND WHERE id=?", [$job->id]);
        $this->jobs->heartbeat($job->id, 'worker-A');
        $row = $this->jobs->findById($job->id);
        $fresh = $this->db->fetchColumn("SELECT heartbeat_at > NOW() - INTERVAL 100 SECOND FROM provisioning_jobs WHERE id=?", [$job->id]);
        $this->assertEquals(1, (int)$fresh);
    }

    public function testSanitizeErrorRedactsSecrets(): void
    {
        $dirty = 'connect failed: password=supersecret api_key=abc123 token=xyz Bearer tok';
        $clean = ProvisioningService::sanitizeError($dirty);
        $this->assertStringNotContainsString('supersecret', $clean);
        $this->assertStringNotContainsString('abc123', $clean);
        $this->assertStringNotContainsString('xyz', $clean);
        $this->assertStringNotContainsString('Bearer tok', $clean);
        $this->assertStringContainsString('[REDACTED]', $clean);
    }
}

final class FailingProvisioner implements HostingProvisionerInterface
{
    public function createHostingAccount(\App\Models\HostingAccount $account): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function suspendHostingAccount(\App\Models\HostingAccount $account): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function activateHostingAccount(\App\Models\HostingAccount $account): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function terminateHostingAccount(\App\Models\HostingAccount $account): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function createSubdomain(\App\Models\HostingAccount $account, string $subdomain, string $fullDomain): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function deleteSubdomain(\App\Models\HostingAccount $account, string $fullDomain): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function createDatabase(\App\Models\HostingAccount $account, string $dbName): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function deleteDatabase(\App\Models\HostingAccount $account, string $dbName): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function createDatabaseUser(\App\Models\HostingAccount $account, string $username, string $password): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
    public function deleteDatabaseUser(\App\Models\HostingAccount $account, string $username): ProvisionResult
    {
        return ProvisionResult::fail('boom simulated');
    }
}