<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\HostingAccountRepository;
use App\Repositories\SslCertificateRepository;
use App\Services\Providers\CertificateProviderInterface;

final class SslService
{
    private const VALID_STATUSES = ['pending','issuing','active','renewing','expired','failed','revoked'];

    public function __construct(
        private readonly Database $db,
        private readonly HostingAccountRepository $accounts,
        private readonly SslCertificateRepository $certs,
        private readonly CertificateProviderInterface $provider,
        private readonly AuditService $audit,
    ) {}

    private function requireActiveAccount(int $userId, int $accountId): \App\Models\HostingAccount
    {
        $acct = $this->accounts->findById($accountId);
        if (!$acct) throw new \RuntimeException('Hosting account not found', 404);
        if ($acct->userId !== $userId) {
            $this->audit->log($userId, 'ssl.access_denied', 'hosting_account', (string)$accountId, 'failure', ['reason'=>'ownership']);
            throw new \RuntimeException('Access denied', 403);
        }
        if ($acct->status !== 'active') throw new \RuntimeException('Hosting account is ' . $acct->status, 403);
        return $acct;
    }

    public static function validateHostname(string $hostname): array
    {
        // Reuse DnsService validation
        return \App\Services\DnsService::validateHostname($hostname);
    }

    public function list(int $userId, int $accountId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $certs = $this->certs->findByHosting($accountId);
        // Also include subdomains with SSL status
        $subs = $this->db->fetchAll("SELECT * FROM subdomains WHERE hosting_account_id=?", [$accountId]);
        return ['account'=>$acct,'certificates'=>$certs,'subdomains'=>$subs];
    }

    public function request(int $userId, int $accountId, string $hostname): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $v = self::validateHostname($hostname);
        if (!$v['valid']) throw new \RuntimeException($v['error'], 400);
        $hostname = $v['hostname'];

        // Must be own hostname (subdomain of account)
        $isOwn = (bool)$this->db->fetchColumn("SELECT 1 FROM subdomains WHERE hosting_account_id=? AND full_domain=?", [$accountId, $hostname]);
        $main = strtolower(trim($_ENV['APP_DOMAIN'] ?? $this->db->fetchColumn("SELECT value FROM system_settings WHERE `key`='main_domain'") ?? 'freehost.example'));
        $isMain = $hostname === $main;
        if (!$isOwn && !$isMain) {
            throw new \RuntimeException('Hostname not owned by this hosting account', 403);
        }

        // Duplicate
        if ($this->certs->findByHostname($hostname)) {
            throw new \RuntimeException('Certificate already exists for hostname', 409);
        }

        // Provider request (mock)
        $res = $this->provider->requestCertificate($hostname);
        $status = $res['success'] ? 'active' : 'failed';
        $expires = $res['expires_at'] ?? null;
        if (!$res['success']) {
            $status = 'failed';
        }

        $cert = $this->certs->create($accountId, $hostname, $status, 'local_mock', $expires);
        if (!$res['success']) {
            $this->db->execute("UPDATE ssl_certificates SET last_error=? WHERE id=?", [$res['message'] ?? 'Provider failed', $cert->id]);
            $this->audit->log($userId, 'ssl.request_failed', 'ssl_certificate', (string)$cert->id, 'failure', ['hostname'=>$hostname,'error'=>$res['message'] ?? '']);
            throw new \RuntimeException('SSL request failed: ' . ($res['message'] ?? 'provider error'), 500);
        }

        // Update subdomain SSL status as well for convenience
        $this->db->execute("UPDATE subdomains SET ssl_status=?, ssl_expires_at=? WHERE full_domain=?", [$status, $expires, $hostname]);

        $this->audit->log($userId, 'ssl.request', 'ssl_certificate', (string)$cert->id, 'success', ['hostname'=>$hostname,'status'=>$status]);
        return ['success'=>true,'message'=>'Certificate issued (mock)','certificate'=>$cert];
    }

    public function renew(int $userId, int $accountId, int $certId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $cert = $this->certs->findById($certId);
        if (!$cert) throw new \RuntimeException('Certificate not found', 404);
        if ($cert->hostingAccountId !== $accountId) throw new \RuntimeException('Access denied', 403);
        if (!in_array($cert->status, ['active','expired','failed'], true)) {
            throw new \RuntimeException('Certificate cannot be renewed in status ' . $cert->status, 400);
        }

        $res = $this->provider->renewCertificate($cert->hostname);
        if (!$res['success']) {
            $this->certs->updateStatus($certId, 'failed', null, $res['message'] ?? 'renew failed');
            $this->audit->log($userId, 'ssl.renew_failed', 'ssl_certificate', (string)$certId, 'failure', ['hostname'=>$cert->hostname]);
            throw new \RuntimeException('Renew failed', 500);
        }

        $expires = $res['expires_at'] ?? date('Y-m-d H:i:s', time()+90*24*3600);
        $this->certs->updateStatus($certId, 'active', $expires, null);
        $this->db->execute("UPDATE subdomains SET ssl_status='active', ssl_expires_at=? WHERE full_domain=?", [$expires, $cert->hostname]);
        $this->audit->log($userId, 'ssl.renew', 'ssl_certificate', (string)$certId, 'success', ['hostname'=>$cert->hostname]);
        return ['success'=>true,'message'=>'Certificate renewed'];
    }

    public function revoke(int $userId, int $accountId, int $certId): array
    {
        $cert = $this->certs->findById($certId);
        if (!$cert) throw new \RuntimeException('Not found', 404);
        $acct = $this->requireActiveAccount($userId, $accountId);
        if ($cert->hostingAccountId !== $accountId) throw new \RuntimeException('Access denied', 403);

        $res = $this->provider->revokeCertificate($cert->hostname);
        $this->certs->updateStatus($certId, 'revoked', null, null);
        $this->db->execute("UPDATE subdomains SET ssl_status='revoked' WHERE full_domain=?", [$cert->hostname]);
        $this->audit->log($userId, 'ssl.revoke', 'ssl_certificate', (string)$certId, 'success', ['hostname'=>$cert->hostname]);
        return ['success'=>true,'message'=>'Certificate revoked'];
    }

    public function updateStatus(int $userId, int $certId, string $status): array
    {
        if (!in_array($status, self::VALID_STATUSES, true)) throw new \RuntimeException('Invalid status', 400);
        $cert = $this->certs->findById($certId);
        if (!$cert) throw new \RuntimeException('Not found', 404);
        // Admin check via controller, but also ensure ownership for customer
        $acct = $this->accounts->findById($cert->hostingAccountId);
        $isAdmin = in_array('admin', $_SESSION['user_roles'] ?? [], true);
        if (!$isAdmin && $acct->userId !== $userId) throw new \RuntimeException('Access denied', 403);

        $this->certs->updateStatus($certId, $status);
        $this->db->execute("UPDATE subdomains SET ssl_status=? WHERE full_domain=?", [$status, $cert->hostname]);
        $this->audit->log($userId, 'ssl.status_update', 'ssl_certificate', (string)$certId, 'success', ['status'=>$status]);
        return ['success'=>true,'message'=>'Status updated'];
    }
}
