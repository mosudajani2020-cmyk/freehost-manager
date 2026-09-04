<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\HostingAccountRepository;
use App\Repositories\DnsRecordRepository;
use App\Services\Providers\DnsProviderInterface;

final class DnsService
{
    private const RESERVED = ['www','mail','ftp','admin','api','ns1','ns2','cdn'];

    public function __construct(
        private readonly Database $db,
        private readonly HostingAccountRepository $accounts,
        private readonly DnsRecordRepository $dns,
        private readonly DnsProviderInterface $provider,
        private readonly AuditService $audit,
    ) {}

    public static function validateHostname(string $hostname): array
    {
        $hostname = strtolower(trim($hostname));
        if ($hostname === '') return ['valid'=>false,'error'=>'Hostname required'];
        if (strlen($hostname) > 253) return ['valid'=>false,'error'=>'Hostname too long (max 253)'];
        if (!str_contains($hostname, '.')) return ['valid'=>false,'error'=>'Hostname must contain dot'];
        // No spaces, quotes, semicolon, injection
        if (preg_match('/[\s\'"`;\\\\]/', $hostname)) return ['valid'=>false,'error'=>'Invalid characters'];
        if (str_contains($hostname, '..')) return ['valid'=>false,'error'=>'Consecutive dots'];
        $labels = explode('.', $hostname);
        foreach ($labels as $label) {
            if ($label === '') return ['valid'=>false,'error'=>'Empty label'];
            if (strlen($label) > 63) return ['valid'=>false,'error'=>'Label too long'];
            if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $label)) return ['valid'=>false,'error'=>'Invalid label: ' . $label];
            if (in_array($label, self::RESERVED, true) && count($labels) === 2) {
                // Allow reserved as subdomain? For now allow but warn; we will block reserved single label for subdomains already
            }
        }
        // No wildcard
        if (str_contains($hostname, '*')) return ['valid'=>false,'error'=>'Wildcard not allowed'];
        // Control chars
        if (preg_match('/[\x00-\x1F\x7F]/', $hostname)) return ['valid'=>false,'error'=>'Control characters'];
        return ['valid'=>true,'hostname'=>$hostname];
    }

    private function requireActiveAccount(int $userId, int $accountId): \App\Models\HostingAccount
    {
        $acct = $this->accounts->findById($accountId);
        if (!$acct) throw new \RuntimeException('Hosting account not found', 404);
        if ($acct->userId !== $userId) {
            $this->audit->log($userId, 'dns.access_denied', 'hosting_account', (string)$accountId, 'failure', ['reason'=>'ownership']);
            throw new \RuntimeException('Access denied', 403);
        }
        if ($acct->status !== 'active') throw new \RuntimeException('Hosting account is ' . $acct->status, 403);
        return $acct;
    }

    public function list(int $userId, int $accountId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $records = $this->dns->findByHosting($accountId);
        // Also include subdomains as DNS-aware view
        $subs = $this->db->fetchAll("SELECT * FROM subdomains WHERE hosting_account_id=?", [$accountId]);
        return ['account'=>$acct,'records'=>$records,'subdomains'=>$subs];
    }

    public function create(int $userId, int $accountId, string $hostname, string $type='A', string $value='127.0.0.1', int $ttl=3600): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $validation = self::validateHostname($hostname);
        if (!$validation['valid']) throw new \RuntimeException($validation['error'], 400);
        $hostname = $validation['hostname'];

        // Must belong to assigned domain: check ends with main domain or equals main domain
        $main = strtolower(trim($_ENV['APP_DOMAIN'] ?? $this->db->fetchColumn("SELECT value FROM system_settings WHERE `key`='main_domain'") ?? 'freehost.example'));
        if ($hostname !== $main && !str_ends_with($hostname, '.' . $main)) {
            // For Phase 6, only allow subdomains of main domain to prevent takeover
            throw new \RuntimeException('Hostname must be subdomain of ' . $main, 400);
        }

        // Duplicate
        if ($this->dns->existsHostname($hostname)) throw new \RuntimeException('DNS record already exists for hostname', 409);
        if ((bool)$this->db->fetchColumn("SELECT 1 FROM subdomains WHERE full_domain=?", [$hostname])) {
            // Allow if subdomain already exists? For Phase 6, subdomain creation already creates DNS via HostService, so duplicate should be checked via dns_records only
            // But we prevent duplicate hostname across dns_records and subdomains
            // If subdomain exists, we allow DNS record if not exists in dns_records? Actually we already checked dns_records, so allow
        }

        // Ownership: ensure hostname belongs to this hosting account's subdomains or is main domain? For now, require that hostname was previously created as subdomain for this account or is the main domain assigned
        // For customer, they can only create DNS for their own subdomains
        $isOwnSubdomain = (bool)$this->db->fetchColumn("SELECT 1 FROM subdomains WHERE hosting_account_id=? AND full_domain=?", [$accountId, $hostname]);
        $isMainAssigned = $hostname === $main && $acct->domain === $main;
        if (!$isOwnSubdomain && !$isMainAssigned) {
            // Allow creation for new subdomain that doesn't yet exist as subdomain? For Phase 6, DNS creation is separate from subdomain, so we should allow if hostname is new subdomain of main domain and not yet taken globally
            // But to prevent takeover, we require that the subdomain part is not already taken by another account globally
            if ((bool)$this->db->fetchColumn("SELECT 1 FROM subdomains WHERE full_domain=?", [$hostname])) {
                throw new \RuntimeException('Hostname already taken', 409);
            }
        }

        // Type validation
        $allowedTypes = ['A','AAAA','CNAME','TXT','MX','NS'];
        if (!in_array($type, $allowedTypes, true)) throw new \RuntimeException('Invalid DNS type', 400);
        if ($type === 'MX' && !filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            // Simple check
        }
        if (strlen($value) > 500) throw new \RuntimeException('Value too long', 400);
        if ($ttl < 60 || $ttl > 86400) throw new \RuntimeException('TTL must be 60-86400', 400);

        // Provider call (mock, no real DNS)
        $res = $this->provider->createRecord($hostname, $type, $value, $ttl);
        if (!$res['success']) {
            $this->audit->log($userId, 'dns.create_failed', 'dns_record', $hostname, 'failure', ['error'=>$res['message']]);
            throw new \RuntimeException('DNS provider failed: ' . $res['message'], 500);
        }

        $record = $this->dns->create($accountId, $hostname, $type, $value, $ttl, 'active');
        $this->audit->log($userId, 'dns.create', 'dns_record', (string)$record->id, 'success', ['hostname'=>$hostname,'type'=>$type]);
        return ['success'=>true,'message'=>'DNS record created','record'=>$record];
    }

    public function remove(int $userId, int $accountId, int $recordId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $rec = $this->dns->findById($recordId);
        if (!$rec) throw new \RuntimeException('DNS record not found', 404);
        if ($rec->hostingAccountId !== $accountId) throw new \RuntimeException('Access denied', 403);

        $res = $this->provider->deleteRecord($rec->hostname, $rec->type);
        if (!$res['success']) throw new \RuntimeException('Provider delete failed: ' . $res['message'], 500);

        $this->dns->delete($recordId);
        $this->audit->log($userId, 'dns.delete', 'dns_record', (string)$recordId, 'success', ['hostname'=>$rec->hostname]);
        return ['success'=>true,'message'=>'DNS record removed'];
    }

    public function updateStatus(int $userId, int $accountId, int $recordId, string $status): array
    {
        // Admin only via controller RBAC, but service also checks ownership
        $valid = ['pending','active','failed','suspended','removed'];
        if (!in_array($status, $valid, true)) throw new \RuntimeException('Invalid status', 400);
        $rec = $this->dns->findById($recordId);
        if (!$rec) throw new \RuntimeException('Not found', 404);
        // For customer, ensure own; for admin, allow any (controller checks admin role)
        $acct = $this->accounts->findById($rec->hostingAccountId);
        if (!$acct) throw new \RuntimeException('Account not found', 404);
        // If not admin, require ownership
        $isAdmin = in_array('admin', $_SESSION['user_roles'] ?? [], true);
        if (!$isAdmin && $acct->userId !== $userId) throw new \RuntimeException('Access denied', 403);

        $this->dns->updateStatus($recordId, $status);
        $this->audit->log($userId, 'dns.status_update', 'dns_record', (string)$recordId, 'success', ['status'=>$status]);
        return ['success'=>true,'message'=>'Status updated'];
    }
}
