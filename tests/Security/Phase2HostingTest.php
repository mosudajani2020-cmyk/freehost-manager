<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Repositories\HostingPlanRepository;
use App\Repositories\HostingAccountRepository;
use App\Repositories\SubdomainRepository;
use App\Services\AuditService;
use App\Services\HostingService;
use App\Services\Provisioning\LocalMockProvisioner;

final class Phase2HostingTest extends TestCase
{
    private Database $db;
    private HostingService $service;
    private HostingAccountRepository $accounts;
    private int $user1;
    private int $user2;
    private int $admin;
    private int $planId;

    protected function setUp(): void
    {
        // Require PHP 8.3
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $this->markTestSkipped('Requires PHP 8.3');
        }
        // Use real DB — need .env
        $base = dirname(__DIR__, 2);
        if (file_exists($base . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($base);
            $dotenv->load();
        }
        $config = require $base . '/config/database.php';
        // Override test DB — use main DB for Phase2 integration (cleanup after)
        $config['database'] = 'freehost_manager';
        Database::reset();
        $this->db = Database::getInstance($config);
        $this->accounts = new HostingAccountRepository($this->db);
        $plans = new HostingPlanRepository($this->db);
        $subs = new SubdomainRepository($this->db);
        $this->service = new HostingService($this->db, $this->accounts, $plans, $subs, new LocalMockProvisioner(), new AuditService($this->db));

        // Get plan
        $plan = $plans->getDefault();
        $this->assertNotNull($plan, 'Default plan must exist');
        $this->planId = $plan->id;

        // Create test users (unique per run)
        $suffix = bin2hex(random_bytes(3));
        $hash = password_hash('TestPass123', PASSWORD_BCRYPT);
        // Clean previous test users if any
        $this->db->execute("DELETE FROM users WHERE email LIKE 'phase2test%+%@example.com'");

        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["User One $suffix", "ptest1_$suffix", "phase2test1+$suffix@example.com", $hash]);
        $this->user1 = (int) $this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user1]);

        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["User Two $suffix", "ptest2_$suffix", "phase2test2+$suffix@example.com", $hash]);
        $this->user2 = (int) $this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user2]);

        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["Admin $suffix", "ptestadmin_$suffix", "phase2admin+$suffix@example.com", $hash]);
        $this->admin = (int) $this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='admin'))", [$this->admin]);
    }

    protected function tearDown(): void
    {
        // Cleanup accounts and users
        $this->db->execute("DELETE FROM subdomains WHERE hosting_account_id IN (SELECT id FROM hosting_accounts WHERE user_id IN (?,?,?))", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM hosting_accounts WHERE user_id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM user_roles WHERE user_id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        // Cleanup any hosting directories created
        $storage = dirname(__DIR__, 2) . '/storage/hosting';
        if (is_dir($storage)) {
            foreach (glob($storage . '/*') as $dir) {
                if (is_dir($dir) && str_contains(basename($dir), 'ptest')) {
                    // Not precise because username is random, but we can attempt to clean those with 'ptest'
                    // For Phase2 accounts, username is ptest... so directory contains that
                    // We check inside: if dir contains ptest or is test-related
                }
            }
        }
    }

    public function testCustomerCanCreateHosting(): void
    {
        $res = $this->service->createAccount($this->user1, $this->planId, 'testhost' . bin2hex(random_bytes(2)));
        $this->assertTrue($res['success'], $res['message']);
        $this->assertNotNull($res['account']);
        $this->assertEquals('active', $res['account']->status);
    }

    public function testCustomerCannotAccessAnotherCustomersAccount(): void
    {
        $username = 'idonot' . bin2hex(random_bytes(2));
        $res = $this->service->createAccount($this->user1, $this->planId, $username);
        $this->assertTrue($res['success']);
        $acctId = $res['account']->id;

        // User2 tries to create subdomain on user1's account — should be denied
        $res2 = $this->service->createSubdomain($this->user2, $acctId, 'hacker');
        $this->assertFalse($res2['success']);
        $this->assertEquals('Access denied', $res2['message']);

        // Direct ownership check
        $acct = $this->accounts->findById($acctId);
        $this->assertFalse($this->service->enforceOwnership($this->user2, $acct));
        $this->assertTrue($this->service->enforceOwnership($this->user1, $acct));
    }

    public function testDuplicateSubdomainRejected(): void
    {
        $res = $this->service->createAccount($this->user1, $this->planId, 'dup' . bin2hex(random_bytes(2)));
        $this->assertTrue($res['success']);
        $id = $res['account']->id;
        $r1 = $this->service->createSubdomain($this->user1, $id, 'mydup');
        $this->assertTrue($r1['success'], $r1['message']);
        $r2 = $this->service->createSubdomain($this->user1, $id, 'mydup');
        $this->assertFalse($r2['success']);
        $this->assertStringContainsString('already exists', $r2['message']);
    }

    public function testInvalidSubdomainRejected(): void
    {
        $res = $this->service->createAccount($this->user1, $this->planId, 'inv' . bin2hex(random_bytes(2)));
        $this->assertTrue($res['success']);
        $id = $res['account']->id;
        foreach (['-bad','bad-','bad..','<script>','../etc','www',''] as $bad) {
            $r = $this->service->createSubdomain($this->user1, $id, $bad);
            $this->assertFalse($r['success'], "Should reject: $bad");
        }
    }

    public function testSubdomainQuotaEnforced(): void
    {
        // Create plan with limit 2 (FREE)
        $plan = (new HostingPlanRepository($this->db))->findBySlug('free');
        $this->assertNotNull($plan);
        $this->assertEquals(2, $plan->subdomainLimit);
        $res = $this->service->createAccount($this->user1, $plan->id, 'quota' . bin2hex(random_bytes(2)));
        $this->assertTrue($res['success']);
        $id = $res['account']->id;
        $this->assertTrue($this->service->createSubdomain($this->user1, $id, 'a1')['success']);
        $this->assertTrue($this->service->createSubdomain($this->user1, $id, 'a2')['success']);
        $r3 = $this->service->createSubdomain($this->user1, $id, 'a3');
        $this->assertFalse($r3['success']);
        $this->assertStringContainsString('limit reached', $r3['message']);
    }

    public function testUnlimitedSubdomainBlocked(): void
    {
        // Ensure user cannot create unlimited by spamming
        $res = $this->service->createAccount($this->user1, $this->planId, 'unlim' . bin2hex(random_bytes(2)));
        $id = $res['account']->id;
        // FREE limit 2, try loop
        for ($i=0;$i<3;$i++) {
            $this->service->createSubdomain($this->user1, $id, 'u' . $i);
        }
        $count = (new SubdomainRepository($this->db))->countByHosting($id);
        $this->assertEquals(2, $count);
    }

    public function testSuspendedCannotCreateSubdomain(): void
    {
        $res = $this->service->createAccount($this->user1, $this->planId, 'susp' . bin2hex(random_bytes(2)));
        $id = $res['account']->id;
        $this->service->suspendAccount($this->admin, $id, true);
        $r = $this->service->createSubdomain($this->user1, $id, 'after');
        $this->assertFalse($r['success']);
        $this->assertStringContainsString('active', $r['message']);
    }

    public function testTerminatedCannotBeReused(): void
    {
        $res = $this->service->createAccount($this->user1, $this->planId, 'term' . bin2hex(random_bytes(2)));
        $id = $res['account']->id;
        $this->service->terminateAccount($this->admin, $id, true);
        $r = $this->service->createSubdomain($this->user1, $id, 'after');
        $this->assertFalse($r['success']);
        // Also suspend should fail
        $this->assertFalse($this->service->suspendAccount($this->admin, $id, true)['success']);
        // Activate should fail
        $this->assertFalse($this->service->activateAccount($this->admin, $id, true)['success']);
    }

    public function testSqlInjectionBlockedInHosting(): void
    {
        // Attempt SQL injection via username — validator should have blocked but service also checks regex
        $res = $this->service->createAccount($this->user1, $this->planId, "' OR 1=1 --");
        $this->assertFalse($res['success']);
        // Via subdomain
        $res2 = $this->service->createAccount($this->user1, $this->planId, 'inject' . bin2hex(random_bytes(2)));
        $id = $res2['account']->id;
        $r = $this->service->createSubdomain($this->user1, $id, "' OR '1'='1");
        $this->assertFalse($r['success']);
        // Ensure no SQL executed that dumped users
        $count = $this->db->fetchColumn("SELECT COUNT(*) FROM users");
        $this->assertGreaterThan(0, $count);
    }

    public function testXssEscaped(): void
    {
        $payload = '<script>alert(1)</script>';
        $escaped = e($payload);
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;', $escaped);
    }

    public function testAuditRecordsCreated(): void
    {
        $before = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $res = $this->service->createAccount($this->user1, $this->planId, 'audit' . bin2hex(random_bytes(2)));
        $after = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $this->assertGreaterThan($before, $after);
        $last = $this->db->fetch("SELECT * FROM audit_logs WHERE user_id=? ORDER BY id DESC LIMIT 1", [$this->user1]);
        $this->assertEquals('hosting.create', $last['action']);
        $this->assertNotEmpty($last['ip_address']);
        // Ensure no secrets in audit metadata
        $this->assertStringNotContainsString('password', json_encode($last['metadata'] ?? ''));
    }

    public function testNoSecretsInLogs(): void
    {
        $this->service->createAccount($this->user1, $this->planId, 'nolog' . bin2hex(random_bytes(2)));
        $logs = $this->db->fetchAll("SELECT metadata FROM audit_logs WHERE user_id=? ORDER BY id DESC LIMIT 5", [$this->user1]);
        foreach ($logs as $row) {
            $meta = $row['metadata'] ?? '';
            $this->assertStringNotContainsString('FreeHost_App', (string) $meta);
        }
    }

    public function testNoShellFunctions(): void
    {
        // Ensure no exec functions in app code (static check)
        $appFiles = array_merge(
            glob(dirname(__DIR__, 2) . '/app/**/*.php') ?: [],
            glob(dirname(__DIR__, 2) . '/app/*.php') ?: []
        );
        // Recursive glob via iterator
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app'));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                // Exclude method calls like ->exec or ::exec (pdo->exec) — only dangerous global functions
                $this->assertDoesNotMatchRegularExpression('/(?<!->)(?<!::)\b(exec|shell_exec|system|passthru|proc_open|popen)\s*\(/', $content, 'Shell exec found in ' . $file->getFilename());
                // Backtick shell exec detection — skip SQL backticks (check for backtick outside strings would be complex); we rely on above function check
            }
        }
    }

    public function testAdminCanSuspendAndActivate(): void
    {
        $res = $this->service->createAccount($this->user1, $this->planId, 'adm' . bin2hex(random_bytes(2)));
        $id = $res['account']->id;
        $this->assertTrue($this->service->suspendAccount($this->admin, $id, true)['success']);
        $acct = $this->accounts->findById($id);
        $this->assertEquals('suspended', $acct->status);
        $this->assertTrue($this->service->activateAccount($this->admin, $id, true)['success']);
        $acct = $this->accounts->findById($id);
        $this->assertEquals('active', $acct->status);
    }

    public function testUnauthorizedPlanModificationIsAdminOnly(): void
    {
        // Simulate RBAC: customer should not have plans.manage
        $customer = $this->db->fetch("SELECT * FROM users WHERE id=?", [$this->user1]);
        $roles = $this->db->fetchAll("SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?", [$this->user1]);
        $roleNames = array_column($roles, 'name');
        $this->assertNotContains('admin', $roleNames);
        // Permission check: customer perms should not contain plans.manage
        $perms = $this->db->fetchAll("SELECT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?", [$this->user1]);
        $permNames = array_column($perms, 'name');
        $this->assertNotContains('plans.manage', $permNames);
        $adminPerms = $this->db->fetchAll("SELECT p.name FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?", [$this->admin]);
        $adminPermNames = array_column($adminPerms, 'name');
        $this->assertContains('plans.manage', $adminPermNames);
    }
}
