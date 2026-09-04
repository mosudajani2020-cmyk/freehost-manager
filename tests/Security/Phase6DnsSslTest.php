<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Helpers\Database;
use App\Repositories\HostingPlanRepository;
use App\Repositories\HostingAccountRepository;
use App\Repositories\DnsRecordRepository;
use App\Repositories\SslCertificateRepository;
use App\Services\AuditService;
use App\Services\DnsService;
use App\Services\SslService;
use App\Services\HostingService;
use App\Services\Provisioning\LocalMockProvisioner;
use App\Repositories\SubdomainRepository;
use App\Services\Providers\LocalMockDnsProvider;
use App\Services\Providers\LocalMockCertificateProvider;

final class Phase6DnsSslTest extends TestCase
{
    private Database $db;
    private DnsService $dns;
    private SslService $ssl;
    private HostingService $hosting;
    private int $user1;
    private int $user2;
    private int $accountId;
    private string $mainDomain;

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
        $audit = new AuditService($this->db);
        $accounts = new HostingAccountRepository($this->db);
        $this->dns = new DnsService($this->db, $accounts, new DnsRecordRepository($this->db), new LocalMockDnsProvider(), $audit);
        $this->ssl = new SslService($this->db, $accounts, new SslCertificateRepository($this->db), new LocalMockCertificateProvider(), $audit);
        $this->hosting = new HostingService($this->db, $accounts, $plans, new SubdomainRepository($this->db), new LocalMockProvisioner(), $audit);

        $suffix = bin2hex(random_bytes(3));
        $hash = password_hash('TestPass123', PASSWORD_BCRYPT);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p6test%+%@example.com'");
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P6 One $suffix","p6u1_$suffix","p6test1+$suffix@example.com",$hash]);
        $this->user1 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user1]);
        $this->db->query("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?,?,?,?, 'active', NOW())", ["P6 Two $suffix","p6u2_$suffix","p6test2+$suffix@example.com",$hash]);
        $this->user2 = (int)$this->db->lastInsertId();
        $this->db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, (SELECT id FROM roles WHERE name='customer'))", [$this->user2]);

        $plan = $plans->getDefault();
        $res = $this->hosting->createAccount($this->user1, $plan->id, 'p6host' . $suffix);
        $this->assertTrue($res['success']);
        $this->accountId = $res['account']->id;
        $this->mainDomain = strtolower(trim($_ENV['APP_DOMAIN'] ?? $this->db->fetchColumn("SELECT value FROM system_settings WHERE `key`='main_domain'") ?? 'freehost.example'));
        // Create a subdomain for testing DNS/SSL association
        $this->hosting->createSubdomain($this->user1, $this->accountId, 'p6sub' . $suffix);
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM dns_records WHERE hosting_account_id=?", [$this->accountId]);
        $this->db->execute("DELETE FROM ssl_certificates WHERE hosting_account_id=?", [$this->accountId]);
        $this->db->execute("DELETE FROM subdomains WHERE hosting_account_id=?", [$this->accountId]);
        $this->db->execute("DELETE FROM hosting_accounts WHERE id=?", [$this->accountId]);
        $acct = (new HostingAccountRepository($this->db))->findById($this->accountId);
        if ($acct && is_dir($acct->rootPath)) {
            $this->rrmdir($acct->rootPath);
        }
        $this->db->execute("DELETE FROM user_roles WHERE user_id IN (?,?)", [$this->user1,$this->user2]);
        $this->db->execute("DELETE FROM users WHERE id IN (?,?)", [$this->user1,$this->user2]);
        $this->db->execute("DELETE FROM users WHERE email LIKE 'p6test%+%@example.com'");
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    public function testHostnameValidation(): void
    {
        $valid = DnsService::validateHostname('test.' . $this->mainDomain);
        $this->assertTrue($valid['valid']);
        $this->assertEquals('test.' . $this->mainDomain, $valid['hostname']);

        $cases = [
            '' => false,
            'a' => false, // no dot
            str_repeat('a', 64) . '.' . $this->mainDomain => false, // label too long
            'bad..' . $this->mainDomain => false,
            'bad ' . $this->mainDomain => false,
            "bad'" . $this->mainDomain => false,
            'bad;drop.' . $this->mainDomain => false,
            'bad*.'. $this->mainDomain => false,
            '-bad.' . $this->mainDomain => false,
            'bad-.' . $this->mainDomain => false,
            str_repeat('a', 250) . '.com' => false, // too long
        ];
        foreach ($cases as $host => $shouldPass) {
            $res = DnsService::validateHostname($host);
            $this->assertEquals($shouldPass, $res['valid'], "Host $host should be " . ($shouldPass?'valid':'invalid'));
        }
    }

    public function testIdorBlocked(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied');
        $this->dns->create($this->user2, $this->accountId, 'hacker.' . $this->mainDomain, 'A', '1.2.3.4');
    }

    public function testDuplicateHostnameRejected(): void
    {
        $host = 'dup-' . bin2hex(random_bytes(3)) . '.' . $this->mainDomain;
        $res = $this->dns->create($this->user1, $this->accountId, $host, 'A', '1.1.1.1');
        $this->assertTrue($res['success']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $this->dns->create($this->user1, $this->accountId, $host, 'A', '2.2.2.2');
    }

    public function testUnauthorizedDnsChangesBlockedForSuspended(): void
    {
        $this->db->execute("UPDATE hosting_accounts SET status='suspended' WHERE id=?", [$this->accountId]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('suspended');
        $this->dns->create($this->user1, $this->accountId, 'susp.' . $this->mainDomain, 'A', '1.1.1.1');
        $this->db->execute("UPDATE hosting_accounts SET status='active' WHERE id=?", [$this->accountId]);
    }

    public function testUnauthorizedDnsChangesBlockedForTerminated(): void
    {
        $this->db->execute("UPDATE hosting_accounts SET status='terminated' WHERE id=?", [$this->accountId]);
        $this->expectException(\RuntimeException::class);
        $this->dns->create($this->user1, $this->accountId, 'term.' . $this->mainDomain, 'A', '1.1.1.1');
        $this->db->execute("UPDATE hosting_accounts SET status='active' WHERE id=?", [$this->accountId]);
    }

    public function testCsrfMiddlewareExists(): void
    {
        $this->assertTrue(class_exists(\App\Middleware\CsrfMiddleware::class));
    }

    public function testOwnershipEnforcedForList(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->dns->list($this->user2, $this->accountId);
    }

    public function testSslLifecycle(): void
    {
        // Create subdomain to own hostname
        $sub = 'ssltest' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $sub);
        $host = $sub . '.' . $this->mainDomain;
        // HostingService auto-creates cert, so delete to test fresh request
        $this->db->execute("DELETE FROM ssl_certificates WHERE hostname=?", [$host]);
        $this->db->execute("UPDATE subdomains SET ssl_status='pending', ssl_expires_at=NULL WHERE full_domain=?", [$host]);

        // Request cert -> active
        $res = $this->ssl->request($this->user1, $this->accountId, $host);
        $this->assertTrue($res['success']);
        $certId = $res['certificate']->id;
        $cert = $this->db->fetch("SELECT * FROM ssl_certificates WHERE id=?", [$certId]);
        $this->assertEquals('active', $cert['status']);
        $this->assertNotEmpty($cert['expires_at']);

        // Renew
        $renew = $this->ssl->renew($this->user1, $this->accountId, $certId);
        $this->assertTrue($renew['success']);
        $cert2 = $this->db->fetch("SELECT * FROM ssl_certificates WHERE id=?", [$certId]);
        $this->assertEquals('active', $cert2['status']);

        // Revoke
        $revoke = $this->ssl->revoke($this->user1, $this->accountId, $certId);
        $this->assertTrue($revoke['success']);
        $cert3 = $this->db->fetch("SELECT * FROM ssl_certificates WHERE id=?", [$certId]);
        $this->assertEquals('revoked', $cert3['status']);

        // Verify subdomain ssl_status updated
        $subRow = $this->db->fetch("SELECT ssl_status FROM subdomains WHERE full_domain=?", [$host]);
        $this->assertEquals('revoked', $subRow['ssl_status']);
    }

    public function testProviderFailure(): void
    {
        // Hostname containing 'fail' triggers mock failure
        $subFail = 'failcert' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $subFail);
        $hostFail = 'fail-' . $subFail . '.' . $this->mainDomain;
        // Create subdomain for hostFail to own it, then delete auto cert to test provider failure
        $this->hosting->createSubdomain($this->user1, $this->accountId, 'fail-' . $subFail);
        $this->db->execute("DELETE FROM ssl_certificates WHERE hostname=?", [$hostFail]);
        $this->db->execute("UPDATE subdomains SET ssl_status='pending' WHERE full_domain=?", [$hostFail]);
        $this->expectException(\RuntimeException::class);
        $this->ssl->request($this->user1, $this->accountId, $hostFail);
        // Check that failed cert was stored with status failed
        $cert = $this->db->fetch("SELECT * FROM ssl_certificates WHERE hostname=?", [$hostFail]);
        if ($cert) $this->assertEquals('failed', $cert['status']);
    }

    public function testAuditLogsCreated(): void
    {
        $before = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $host = 'audit-' . bin2hex(random_bytes(3)) . '.' . $this->mainDomain;
        // Need to own hostname: create via dns create will require ownership check via subdomain; create subdomain first
        $sub = 'audit' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $sub);
        $host = $sub . '.' . $this->mainDomain . '.audit'; // Actually need to create DNS for a new host, but we can just create DNS for a new subdomain of main
        $host2 = 'audit2-' . bin2hex(random_bytes(3)) . '.' . $this->mainDomain;
        // Create subdomain for host2
        $sub2 = 'audit2' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $sub2);
        $host2 = $sub2 . '.' . $this->mainDomain;
        // Now create DNS via service (needs to be own subdomain, so use host2 which is not yet in dns_records but is owned via subdomain)
        // Actually DnsService create requires hostname to be owned via subdomain or main, so host2 is owned (we just created subdomain)
        // But DNS record for host2 already exists via HostingService mock? HostingService creates dns_records for subdomain, so host2 already has DNS. Let's create a new unique host
        $host3 = 'audit3-' . bin2hex(random_bytes(3)) . '.' . $this->mainDomain;
        $sub3 = 'audit3' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $sub3);
        $host3 = $sub3 . '.' . $this->mainDomain;
        // Remove existing DNS to test audit
        $this->db->execute("DELETE FROM dns_records WHERE hostname=?", [$host3]);
        $this->db->execute("UPDATE subdomains SET dns_status='pending' WHERE full_domain=?", [$host3]);
        $this->dns->create($this->user1, $this->accountId, $host3, 'A', '1.2.3.4');
        $after = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE user_id=?", [$this->user1]);
        $this->assertGreaterThan($before, $after);
    }

    public function testSecretLeakage(): void
    {
        // Ensure no API keys in logs or DB plaintext
        $logs = $this->db->fetchAll("SELECT metadata FROM audit_logs ORDER BY id DESC LIMIT 5");
        foreach ($logs as $row) {
            $meta = json_encode($row['metadata'] ?? '');
            $this->assertStringNotContainsString('fhm_', $meta);
            $this->assertStringNotContainsString('PRIVATE KEY', $meta);
        }
        // Check hosting_nodes api_key_hash not plaintext
        $nodes = $this->db->fetchAll("SELECT api_key_hash FROM hosting_nodes");
        foreach ($nodes as $n) {
            if ($n['api_key_hash']) {
                $this->assertStringNotContainsString('fhm_', $n['api_key_hash']);
                $this->assertEquals(64, strlen($n['api_key_hash'])); // SHA256 hex
            }
        }
    }

    public function testDomainTakeoverPrevented(): void
    {
        // Create a subdomain for user1
        $sub = 'takeover' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $sub);
        $host = $sub . '.' . $this->mainDomain;
        // User2 tries to create DNS for same hostname — should fail duplicate
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        // Need to create hosting for user2 first to have accountId for user2
        $plan = (new HostingPlanRepository($this->db))->getDefault();
        $res = $this->hosting->createAccount($this->user2, $plan->id, 'p6take' . bin2hex(random_bytes(2)));
        $account2 = $res['account']->id;
        $this->dns->create($this->user2, $account2, $host, 'A', '5.5.5.5');
    }

    public function testInvalidHostnameBlocked(): void
    {
        foreach (['', 'bad', 'bad..example.com', 'bad example.com', "bad'example.com", 'bad;example.com', str_repeat('a', 64) . '.com'] as $bad) {
            try {
                $this->dns->create($this->user1, $this->accountId, $bad, 'A', '1.1.1.1');
                $this->fail("Should reject $bad");
            } catch (\RuntimeException $e) {
                $this->assertTrue(true);
            }
        }
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
    }

    public function testDnsLifecycle(): void
    {
        $host = 'life-' . bin2hex(random_bytes(3)) . '.' . $this->mainDomain;
        $sub = 'life' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $sub);
        $host = $sub . '.' . $this->mainDomain;
        // DNS already active via subdomain creation, so delete and recreate to test lifecycle
        $this->db->execute("DELETE FROM dns_records WHERE hostname=?", [$host]);
        $this->db->execute("UPDATE subdomains SET dns_status='pending' WHERE full_domain=?", [$host]);
        $res = $this->dns->create($this->user1, $this->accountId, $host, 'A', '1.1.1.1');
        $this->assertEquals('active', $res['record']->status);
        // Update to suspended via admin
        $this->dns->updateStatus($this->user1, $this->accountId, $res['record']->id, 'suspended');
        $row = $this->db->fetch("SELECT status FROM dns_records WHERE id=?", [$res['record']->id]);
        $this->assertEquals('suspended', $row['status']);
        // Delete
        $this->dns->remove($this->user1, $this->accountId, $res['record']->id);
        $this->assertNull($this->db->fetch("SELECT * FROM dns_records WHERE id=?", [$res['record']->id]));
    }

    public function testSslLifecycleFull(): void
    {
        $sub = 'ssllife' . bin2hex(random_bytes(2));
        $this->hosting->createSubdomain($this->user1, $this->accountId, $sub);
        $host = $sub . '.' . $this->mainDomain;
        // SSL already active, delete and recreate to test pending->issuing->active
        $this->db->execute("DELETE FROM ssl_certificates WHERE hostname=?", [$host]);
        $this->db->execute("UPDATE subdomains SET ssl_status='pending', ssl_expires_at=NULL WHERE full_domain=?", [$host]);
        $res = $this->ssl->request($this->user1, $this->accountId, $host);
        $this->assertTrue($res['success']);
        $cert = $this->db->fetch("SELECT * FROM ssl_certificates WHERE hostname=?", [$host]);
        $this->assertEquals('active', $cert['status']);
        // Renew
        $this->ssl->renew($this->user1, $this->accountId, $cert['id']);
        $cert2 = $this->db->fetch("SELECT * FROM ssl_certificates WHERE id=?", [$cert['id']]);
        $this->assertEquals('active', $cert2['status']);
        // Revoke
        $this->ssl->revoke($this->user1, $this->accountId, $cert['id']);
        $cert3 = $this->db->fetch("SELECT * FROM ssl_certificates WHERE id=?", [$cert['id']]);
        $this->assertEquals('revoked', $cert3['status']);
    }

    public function testProviderInterfacesExist(): void
    {
        $this->assertTrue(interface_exists(\App\Services\Providers\DnsProviderInterface::class));
        $this->assertTrue(interface_exists(\App\Services\Providers\CertificateProviderInterface::class));
        $this->assertTrue(interface_exists(\App\Services\Providers\DomainProviderInterface::class));
        $this->assertTrue(class_exists(\App\Services\Providers\LocalMockDnsProvider::class));
        $this->assertTrue(class_exists(\App\Services\Providers\LocalMockCertificateProvider::class));
    }
}
