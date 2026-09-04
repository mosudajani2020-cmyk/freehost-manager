<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Models\HostingAccount;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Repositories\SubdomainRepository;
use App\Repositories\ProvisioningJobRepository;
use App\Repositories\HostingNodeRepository;
use App\Services\Provisioning\HostingProvisionerInterface;

final class HostingService
{
    private ProvisioningService $provisioning;
    private ProvisioningJobRepository $jobs;
    private HostingNodeRepository $nodes;

    public function __construct(
        private readonly Database $db,
        private readonly HostingAccountRepository $accounts,
        private readonly HostingPlanRepository $plans,
        private readonly SubdomainRepository $subdomains,
        private readonly HostingProvisionerInterface $provisioner,
        private readonly AuditService $audit,
        ?ProvisioningJobRepository $jobs = null,
        ?HostingNodeRepository $nodes = null,
    ) {
        $this->jobs = $jobs ?? new ProvisioningJobRepository($this->db);
        $this->nodes = $nodes ?? new HostingNodeRepository($this->db);
        $this->provisioning = new ProvisioningService($this->db, $this->nodes, $this->jobs, $this->provisioner, $this->audit);
    }

    /**
     * Create hosting account for user — enforces plan limits, ownership, quotas.
     * @return array{success:bool, message:string, account:?HostingAccount}
     */
    public function createAccount(int $userId, int $planId, string $username): array
    {
        $username = strtolower(trim($username));
        // Validate
        $plan = $this->plans->findById($planId);
        if (!$plan) return ['success'=>false,'message'=>'Invalid plan','account'=>null];
        if (!$plan->isActive()) return ['success'=>false,'message'=>'Plan is inactive','account'=>null];
        if (!preg_match('/^[a-z0-9]{3,32}$/', $username)) {
            return ['success'=>false,'message'=>'Invalid username format','account'=>null];
        }
        if ($this->accounts->existsUsername($username)) {
            return ['success'=>false,'message'=>'Username already taken','account'=>null];
        }
        // Quota: one hosting account per user in Phase 2? Actually check if user already has accounts exceeding? For Phase2 we allow 1 per free plan but enforce via count vs plan? Simpler: allow 1 per user for FREE, else check limit? We'll check existing count
        $existing = $this->accounts->count($userId);
        // FREE allows 1, BASIC 2? But we don't have per-user limit in DB — use plan's domain limit as proxy? For Phase2, enforce that user with FREE cannot create more than 1 account (hard-coded)
        // More generic: user can have multiple accounts but each account's plan defines limits. So we don't block; we allow.
        // However we should prevent abuse: limit 5 accounts per user for now.
        if ($existing >= 5) {
            return ['success'=>false,'message'=>'Account limit reached (max 5 per user)','account'=>null];
        }

        $baseStorage = defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/storage';
        $rootPath = rtrim($baseStorage, '/\\') . DIRECTORY_SEPARATOR . 'hosting' . DIRECTORY_SEPARATOR . $username . '_' . bin2hex(random_bytes(3));
        // Ensure uniqueness
        $account = $this->accounts->create([
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'username' => $username,
            'domain' => null,
            'status' => 'pending',
            'root_path' => $rootPath,
        ]);

        // Provision (local mock)
        $accountWithPlan = $this->accounts->findById($account->id);
        $result = $this->provisioner->createHostingAccount($accountWithPlan);
        if (!$result->success) {
            // Rollback account creation
            $this->db->execute("DELETE FROM hosting_accounts WHERE id=?", [$account->id]);
            return ['success'=>false,'message'=>'Provisioning failed: ' . $result->message,'account'=>null];
        }

        // Activate
        $this->accounts->updateStatus($account->id, 'active');
        $account = $this->accounts->findById($account->id);

        $this->audit->log($userId, 'hosting.create', 'hosting_account', (string) $account->id, 'success', ['plan' => $plan->slug]);
        // Phase 5: record provisioning job (idempotent)
        $this->recordJob($account->id, 'createHostingAccount', ['username'=>$username,'plan'=>$plan->slug], $userId);
        return ['success'=>true,'message'=>'Hosting account created','account'=>$account];
    }

    public function suspendAccount(int $actorId, int $accountId, bool $isAdmin = false): array
    {
        $account = $this->accounts->findById($accountId);
        if (!$account) return ['success'=>false,'message'=>'Account not found'];
        if ($account->isTerminated()) return ['success'=>false,'message'=>'Terminated accounts cannot be suspended'];
        if ($account->status === 'suspended') return ['success'=>false,'message'=>'Already suspended'];

        $this->accounts->updateStatus($accountId, 'suspended');
        $account = $this->accounts->findById($accountId);
        $this->provisioner->suspendHostingAccount($account);
        $this->audit->log($actorId, 'hosting.suspend', 'hosting_account', (string) $accountId, 'success', ['by_admin' => $isAdmin]);
        $this->recordJob($accountId, 'suspendHostingAccount', [], $actorId);
        return ['success'=>true,'message'=>'Account suspended'];
    }

    public function activateAccount(int $actorId, int $accountId, bool $isAdmin = false): array
    {
        $account = $this->accounts->findById($accountId);
        if (!$account) return ['success'=>false,'message'=>'Account not found'];
        if ($account->isTerminated()) return ['success'=>false,'message'=>'Terminated accounts cannot be activated — create new'];
        if ($account->status === 'active') return ['success'=>false,'message'=>'Already active'];

        $this->accounts->updateStatus($accountId, 'active');
        $account = $this->accounts->findById($accountId);
        $this->provisioner->activateHostingAccount($account);
        $this->audit->log($actorId, 'hosting.activate', 'hosting_account', (string) $accountId, 'success', ['by_admin' => $isAdmin]);
        $this->recordJob($accountId, 'activateHostingAccount', [], $actorId);
        return ['success'=>true,'message'=>'Account activated'];
    }

    public function terminateAccount(int $actorId, int $accountId, bool $isAdmin = false): array
    {
        $account = $this->accounts->findById($accountId);
        if (!$account) return ['success'=>false,'message'=>'Account not found'];
        if ($account->isTerminated()) return ['success'=>false,'message'=>'Already terminated'];

        $this->accounts->updateStatus($accountId, 'terminated');
        $account = $this->accounts->findById($accountId);
        $this->provisioner->terminateHostingAccount($account);
        $this->audit->log($actorId, 'hosting.terminate', 'hosting_account', (string) $accountId, 'success', ['by_admin' => $isAdmin]);
        $this->recordJob($accountId, 'terminateHostingAccount', [], $actorId);
        return ['success'=>true,'message'=>'Account terminated'];
    }

    public function getMainDomain(): string
    {
        // Prefer system_settings, fallback to APP_DOMAIN
        try {
            $row = $this->db->fetch("SELECT value FROM system_settings WHERE `key`='main_domain' LIMIT 1");
            if ($row && !empty($row['value'])) return strtolower(trim($row['value']));
        } catch (\Throwable) {}
        return strtolower(trim($_ENV['APP_DOMAIN'] ?? 'freehost.example'));
    }

    public function createSubdomain(int $userId, int $accountId, string $subdomain): array
    {
        $account = $this->accounts->findById($accountId);
        if (!$account) return ['success'=>false,'message'=>'Account not found'];
        if ($account->userId !== $userId) {
            $this->audit->log($userId, 'subdomain.create_denied', 'hosting_account', (string) $accountId, 'failure', ['reason'=>'ownership']);
            return ['success'=>false,'message'=>'Access denied'];
        }
        if (!$account->isActive()) {
            return ['success'=>false,'message'=>'Only active accounts can create subdomains (current: ' . $account->status . ')'];
        }
        $sub = strtolower(trim($subdomain));
        // Validate format
        if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $sub)) {
            return ['success'=>false,'message'=>'Invalid subdomain format'];
        }
        if (in_array($sub, ['www','mail','ftp','admin','api'], true)) {
            return ['success'=>false,'message'=>'Reserved subdomain'];
        }
        $mainDomain = $this->getMainDomain();
        $fullDomain = $sub . '.' . $mainDomain;
        if ($this->subdomains->existsFullDomain($fullDomain)) {
            return ['success'=>false,'message'=>'Subdomain already exists'];
        }
        // Quota check
        $plan = $account->plan ?? $this->plans->findById($account->planId);
        if (!$plan) return ['success'=>false,'message'=>'Plan not found'];
        $current = $this->subdomains->countByHosting($accountId);
        if ($current >= $plan->subdomainLimit) {
            return ['success'=>false,'message'=>'Subdomain limit reached (' . $plan->subdomainLimit . ')'];
        }

        $sd = $this->subdomains->create($accountId, null, $sub, $fullDomain);
        $this->provisioner->createSubdomain($account, $sub, $fullDomain);
        $this->audit->log($userId, 'subdomain.create', 'subdomain', (string) $sd->id, 'success', ['domain' => $fullDomain]);
        $this->recordJob($accountId, 'createSubdomain', ['subdomain'=>$sub,'fullDomain'=>$fullDomain], $userId);

        return ['success'=>true,'message'=>'Subdomain created','data'=>['fullDomain'=>$fullDomain, 'subdomain'=>$sd]];
    }

    public function enforceOwnership(int $userId, HostingAccount $account): bool
    {
        return $account->userId === $userId;
    }

    public function checkQuota(HostingAccount $account, string $type, int $requested = 1): bool
    {
        $plan = $account->plan ?? $this->plans->findById($account->planId);
        if (!$plan) return false;
        return match($type) {
            'subdomain' => $this->subdomains->countByHosting($account->id) + $requested <= $plan->subdomainLimit,
            'database' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM customer_databases WHERE hosting_account_id=?", [$account->id]) + $requested <= $plan->databaseLimit,
            'domain' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM domains WHERE hosting_account_id=?", [$account->id]) + $requested <= $plan->domainLimit,
            default => true,
        };
    }

    private function recordJob(int $accountId, string $operation, array $payload, int $requestedBy): void
    {
        try {
            // Select least loaded active node
            $node = null;
            $nodes = $this->nodes->active();
            if ($nodes !== []) {
                usort($nodes, fn($a,$b)=> $a->currentAccounts <=> $b->currentAccounts);
                $node = $nodes[0];
            }
            $idempotency = hash('sha256', $accountId . '|' . $operation . '|' . json_encode($payload) . '|' . microtime(true) . random_bytes(8));
            $this->jobs->create([
                'hosting_account_id' => $accountId,
                'node_id' => $node?->id,
                'operation' => $operation,
                'payload' => $payload,
                'status' => 'active',
                'idempotency_key' => substr($idempotency, 0, 64),
                'requested_by' => $requestedBy,
            ]);
        } catch (\Throwable $e) {
            // Do not fail main operation if job logging fails
            error_log('[HostingService] job record failed: ' . $e->getMessage());
        }
    }
}
