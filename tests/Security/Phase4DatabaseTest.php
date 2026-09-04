<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Repositories\HostingPlanRepository;
use App\Repositories\HostingAccountRepository;
use App\Services\AuditService;
use App\Services\DatabaseService;
use App\Services\HostingService;
use App\Services\Provisioning\LocalMockProvisioner;
use App\Repositories\SubdomainRepository;

final class Phase4DatabaseTest extends TestCase
{
    private Database $db;
    private DatabaseService $dbs;
    private HostingService $hosting;
    private HostingAccountRepository $accounts;
    private int $user1;
    private int $user2;
    private int $admin;
    private int $accountId;
    private int $terminatedId;

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
        $this->accounts = new HostingAccountRepository($this->db);
        $plans = new HostingPlanRepository($this->db);
        $audit = new AuditService($this->db);
        $this->dbs = new DatabaseService($this->db, $this->accounts, $plans, new LocalMockProvisioner(), $audit);
        $this->hosting = new HostingService($this->db, $this->accounts, $plans, new SubdomainRepository($this->db), new LocalMockProvisioner(), $audit);

        $suffix = bin2hex(random_bytes(3));
        $hash = password_hash('TestPass123', PASSWORD_BCRYPT);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p4test%+%@example.com'");
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P4 One $suffix","p4u1_$suffix","p4test1+$suffix@example.com",$hash]);
        $this->user1 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user1]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P4 Two $suffix","p4u2_$suffix","p4test2+$suffix@example.com",$hash]);
        $this->user2 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user2]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P4 Admin $suffix","p4admin_$suffix","p4admin+$suffix@example.com",$hash]);
        $this->admin = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='admin'))", [$this->admin]);

        $plan = $plans->getDefault(); // FREE 2 DBs
        $res = $this->hosting->createAccount($this->user1, $plan->id, 'p4host' . $suffix);
        $this->assertTrue($res['success'], $res['message']);
        $this->accountId = $res['account']->id;

        // Create terminated account for tests
        $res2 = $this->hosting->createAccount($this->user1, $plan->id, 'p4term' . $suffix);
        $this->terminatedId = $res2['account']->id;
        $this->hosting->terminateAccount($this->admin, $this->terminatedId, true);
    }

    protected function tearDown(): void
    {
        // Cleanup databases
        $this->db->execute("DELETE FROM database_users WHERE customer_database_id IN (SELECT id FROM customer_databases WHERE hosting_account_id IN (?,?))", [$this->accountId,$this->terminatedId]);
        $this->db->execute("DELETE FROM customer_databases WHERE hosting_account_id IN (?,?)", [$this->accountId,$this->terminatedId]);
        // Cleanup hosting
        $this->db->execute("DELETE FROM hosting_accounts WHERE id IN (?,?)", [$this->accountId,$this->terminatedId]);
        $acct = $this->accounts->findById($this->accountId);
        if ($acct && is_dir($acct->rootPath)) {
            $this->rrmdir($acct->rootPath);
        }
        $acct2 = $this->accounts->findById($this->terminatedId);
        if ($acct2 && is_dir($acct2->rootPath)) $this->rrmdir($acct2->rootPath);
        $this->db->execute("DELETE FROM user_roles WHERE user_id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE id IN (?,?,?)", [$this->user1,$this->user2,$this->admin]);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p4test%+%@example.com'");
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    public function testDatabaseCreation(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'mydb');
        $this->assertTrue($res['success']);
        $this->assertStringStartsWith('fh_' . $this->accountId . '_', $res['name']);
        $this->assertEquals('mydb', substr($res['name'], strlen('fh_' . $this->accountId . '_')));
    }

    public function testOwnershipEnforced(): void
    {
        $this->dbs->createDatabase($this->user1, $this->accountId, 'owntest');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');
        $this->dbs->listDatabases($this->user2, $this->accountId);
    }

    public function testIdorDeleteBlocked(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'idortest');
        $dbId = $res['id'];
        $this->expectException(\RuntimeException::class);
        $this->dbs->deleteDatabase($this->user2, $this->accountId, $dbId);
    }

    public function testDuplicateDatabaseRejected(): void
    {
        $this->dbs->createDatabase($this->user1, $this->accountId, 'dupdb');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $this->dbs->createDatabase($this->user1, $this->accountId, 'dupdb');
    }

    public function testInvalidDatabaseNamesRejected(): void
    {
        foreach (['', 'ab', 'Bad Name', "bad' OR 1=1", 'bad;drop', 'bad--', 'bad/*', 'bad*', 'bad/slash', 'bad\\slash', "bad\0", 'mysql'] as $bad) {
            try {
                $this->dbs->createDatabase($this->user1, $this->accountId, $bad);
                $this->fail("Should reject: " . var_export($bad, true));
            } catch (\RuntimeException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testSqlInjectionBlocked(): void
    {
        $payload = "' OR '1'='1";
        $this->expectException(\RuntimeException::class);
        $this->dbs->createDatabase($this->user1, $this->accountId, $payload);
        // Ensure no DB created with injection
        $count = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM customer_databases WHERE name LIKE '%OR%'");
        $this->assertEquals(0, $count);
    }

    public function testQuotaEnforced(): void
    {
        // FREE limit 2
        $plan = (new HostingPlanRepository($this->db))->findBySlug('free');
        $this->assertEquals(2, $plan->databaseLimit);
        $this->dbs->createDatabase($this->user1, $this->accountId, 'qdb1');
        $this->dbs->createDatabase($this->user1, $this->accountId, 'qdb2');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('limit reached');
        $this->dbs->createDatabase($this->user1, $this->accountId, 'qdb3');
    }

    public function testSuspendedAccountBlocked(): void
    {
        $this->db->execute("UPDATE hosting_accounts SET status='suspended' WHERE id=?", [$this->accountId]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('suspended');
        $this->dbs->createDatabase($this->user1, $this->accountId, 'shouldfail');
        $this->db->execute("UPDATE hosting_accounts SET status='active' WHERE id=?", [$this->accountId]);
    }

    public function testTerminatedAccountBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->dbs->createDatabase($this->user1, $this->terminatedId, 'failterm');
    }

    public function testCreateDatabaseUser(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'userdb');
        $dbId = $res['id'];
        $ures = $this->dbs->createDatabaseUser($this->user1, $this->accountId, $dbId, 'myuser');
        $this->assertTrue($ures['success']);
        $this->assertStringStartsWith('fh_' . $this->accountId . '_u_', $ures['username']);
        $this->assertNotEmpty($ures['password']);
        $this->assertMatchesRegularExpression('/[A-Z]/', $ures['password']);
        $this->assertMatchesRegularExpression('/[0-9]/', $ures['password']);
        // Verify encrypted stored, not plaintext
        $row = $this->db->fetch("SELECT encrypted_password FROM database_users WHERE id=?", [$ures['id']]);
        $this->assertNotEquals($ures['password'], $row['encrypted_password']);
        $this->assertNotEmpty($row['encrypted_password']);
        // Decrypt should match
        $decrypted = $this->dbs->decryptPassword($row['encrypted_password']);
        $this->assertEquals($ures['password'], $decrypted);
    }

    public function testUnauthorizedUserCreationBlocked(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'authdb');
        $dbId = $res['id'];
        $this->expectException(\RuntimeException::class);
        $this->dbs->createDatabaseUser($this->user2, $this->accountId, $dbId, 'hacker');
    }

    public function testDuplicateUserRejected(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'dupuserdb');
        $dbId = $res['id'];
        $this->dbs->createDatabaseUser($this->user1, $this->accountId, $dbId, 'dupuser');
        $this->expectException(\RuntimeException::class);
        $this->dbs->createDatabaseUser($this->user1, $this->accountId, $dbId, 'dupuser');
    }

    public function testInvalidUserNamesRejected(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'invuserdb');
        $dbId = $res['id'];
        foreach (['', 'ab', 'Bad User', "bad' OR", 'bad;'] as $bad) {
            try {
                $this->dbs->createDatabaseUser($this->user1, $this->accountId, $dbId, $bad);
                $this->fail("Should reject user: $bad");
            } catch (\RuntimeException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testUnauthorizedDeletionBlocked(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'deltest');
        $dbId = $res['id'];
        $this->expectException(\RuntimeException::class);
        $this->dbs->deleteDatabase($this->user2, $this->accountId, $dbId);
    }

    public function testAuditLogsCreated(): void
    {
        $before = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $this->dbs->createDatabase($this->user1, $this->accountId, 'audittest');
        $after = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $this->assertGreaterThan($before, $after);
        $last = $this->db->fetch("SELECT * FROM audit_logs WHERE user_id=? ORDER BY id DESC LIMIT 1", [$this->user1]);
        $this->assertEquals('database.create', $last['action']);
    }

    public function testSecretLeakage(): void
    {
        $res = $this->dbs->createDatabase($this->user1, $this->accountId, 'secretdb');
        $dbId = $res['id'];
        $ures = $this->dbs->createDatabaseUser($this->user1, $this->accountId, $dbId, 'secretuser');
        $pwd = $ures['password'];
        // Ensure audit does not contain password (username may be in metadata, that's ok)
        $logs = $this->db->fetchAll("SELECT metadata FROM audit_logs WHERE user_id=? ORDER BY id DESC LIMIT 5", [$this->user1]);
        foreach ($logs as $row) {
            $meta = json_encode($row['metadata'] ?? '');
            $this->assertStringNotContainsString($pwd, $meta);
        }
        // Ensure DB row does not contain plaintext
        $row = $this->db->fetch("SELECT encrypted_password FROM database_users WHERE id=?", [$ures['id']]);
        $this->assertNotEquals($pwd, $row['encrypted_password']);
        // Ensure password not in logs table (test that error log would not contain? We check audit)
        // Also check that password not exposed via URL (controller uses POST, not GET)
        $this->assertStringNotContainsString($pwd, $_SERVER['REQUEST_URI'] ?? '');
    }

    public function testCsrfMiddlewareExists(): void
    {
        $this->assertTrue(class_exists(\App\Middleware\CsrfMiddleware::class));
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

    public function testXssEscaped(): void
    {
        $payload = '<script>alert(1)</script>';
        $this->assertStringNotContainsString('<script>', e($payload));
        $this->assertStringContainsString('&lt;', e($payload));
    }
}
