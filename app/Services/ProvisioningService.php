<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\HostingNodeRepository;
use App\Repositories\ProvisioningJobRepository;
use App\Models\ProvisioningJob;
use App\Services\Provisioning\HostingProvisionerInterface;
use App\Services\Providers\ProviderFactory;

final class ProvisioningService
{
    public const MAX_ATTEMPTS = 3;
    private const SIGN_TTL = 300; // 5 min replay window

    public function __construct(
        private readonly Database $db,
        private readonly HostingNodeRepository $nodes,
        private readonly ProvisioningJobRepository $jobs,
        private readonly HostingProvisionerInterface $provisioner,
        private readonly AuditService $audit,
    ) {}

    public static function createDefault(Database $db, AuditService $audit): self
    {
        return new self($db, new HostingNodeRepository($db), new ProvisioningJobRepository($db), ProviderFactory::provisioner(), $audit);
    }

    /**
     * Dispatch a provisioning job with idempotency.
     * Returns existing job if idempotency_key already exists.
     *
     * By default the job is processed synchronously in the web request (LocalMock
     * behavior, preserved for backwards compatibility). Pass $async=true to leave
     * the job queued in the provisioning_jobs table for a restricted worker
     * (P1) to claim and process.
     */
    public function dispatch(int $hostingAccountId, string $operation, array $payload = [], ?int $requestedBy = null, ?string $idempotencyKey = null, ?int $nodeId = null, bool $async = false): ProvisioningJob
    {
        $validOps = ['createHostingAccount','suspendHostingAccount','activateHostingAccount','terminateHostingAccount','createSubdomain','deleteSubdomain','createDatabase','deleteDatabase','createDatabaseUser','deleteDatabaseUser'];
        if (!in_array($operation, $validOps, true)) {
            throw new \InvalidArgumentException('Invalid operation: ' . $operation);
        }

        // Validate hosting account exists and not terminated for certain ops
        $acct = $this->db->fetch("SELECT * FROM hosting_accounts WHERE id=?", [$hostingAccountId]);
        if (!$acct) throw new \RuntimeException('Hosting account not found', 404);
        if ($acct['status'] === 'terminated' && !in_array($operation, ['terminateHostingAccount'], true)) {
            throw new \RuntimeException('Terminated account cannot be provisioned', 400);
        }
        if ($acct['status'] === 'suspended' && in_array($operation, ['createSubdomain','createDatabase','createDatabaseUser'], true)) {
            throw new \RuntimeException('Suspended account cannot provision ' . $operation, 400);
        }

        // Node validation if specified
        if ($nodeId !== null) {
            $nodeCheck = $this->nodes->findById($nodeId);
            if (!$nodeCheck || !$nodeCheck->isActive()) {
                throw new \RuntimeException('Invalid node', 400);
            }
        }

        // Node selection: least loaded active if not specified
        if ($nodeId === null && $operation === 'createHostingAccount') {
            $node = $this->selectNode();
            $nodeId = $node?->id;
        }

        $idempotencyKey = $idempotencyKey ?? $this->generateIdempotencyKey($hostingAccountId, $operation, $payload);

        // Idempotency: return existing
        $existing = $this->jobs->findByIdempotency($idempotencyKey);
        if ($existing) {
            return $existing;
        }

        $job = $this->jobs->create([
            'hosting_account_id' => $hostingAccountId,
            'node_id' => $nodeId,
            'operation' => $operation,
            'payload' => $payload,
            'status' => 'queued',
            'idempotency_key' => $idempotencyKey,
            'requested_by' => $requestedBy,
        ]);

        $this->audit->log($requestedBy, 'provisioning.queued', 'provisioning_job', (string)$job->id, 'success', ['operation'=>$operation, 'hosting'=>$hostingAccountId, 'node'=>$nodeId]);

        if (!$async) {
            // For local mock, process synchronously (future: async worker)
            $this->processJob($job->id);
        }

        return $this->jobs->findById($job->id);
    }

    public function processJob(int $jobId): ProvisioningJob
    {
        $job = $this->jobs->findById($jobId);
        if (!$job) throw new \RuntimeException('Job not found', 404);
        if (in_array($job->status, ['active','terminated'], true)) {
            return $job;
        }

        $this->jobs->updateStatus($job->id, 'provisioning');
        $job = $this->jobs->findById($jobId);

        // Load account
        $acctRow = $this->db->fetch("SELECT * FROM hosting_accounts WHERE id=?", [$job->hostingAccountId]);
        if (!$acctRow) {
            $this->jobs->updateStatus($job->id, 'failed', 'Hosting account not found');
            $this->audit->log($job->requestedBy, 'provisioning.failed', 'provisioning_job', (string)$job->id, 'failure', ['reason'=>'account not found']);
            return $this->jobs->findById($jobId);
        }
        $acct = \App\Models\HostingAccount::fromArray($acctRow);
        // Attach plan for provisioner if needed
        $planRow = $this->db->fetch("SELECT * FROM hosting_plans WHERE id=?", [$acct->planId]);
        if ($planRow) $acct->plan = \App\Models\HostingPlan::fromArray($planRow);

        $payload = $job->payload ? json_decode($job->payload, true) : [];

        try {
            $result = $this->executeOperation($acct, $job->operation, $payload);
            if ($result->success) {
                $this->jobs->updateStatus($job->id, 'active', null, date('Y-m-d H:i:s'));
                $this->jobs->incrementAttempts($job->id);
                // Update node load if create
                if ($job->operation === 'createHostingAccount' && $job->nodeId) {
                    $this->nodes->incrementLoad($job->nodeId);
                }
                $this->audit->log($job->requestedBy, 'provisioning.active', 'provisioning_job', (string)$job->id, 'success', ['operation'=>$job->operation]);
            } else {
                throw new \RuntimeException($result->message);
            }
        } catch (\Throwable $e) {
            $attempts = $job->attempts + 1;
            if ($attempts >= $job->maxAttempts) {
                $this->jobs->updateStatus($job->id, 'failed', $e->getMessage());
                $this->audit->log($job->requestedBy, 'provisioning.failed', 'provisioning_job', (string)$job->id, 'failure', ['error'=>$e->getMessage(), 'attempts'=>$attempts]);
            } else {
                $this->jobs->updateAttemptsAndStatus($job->id, $attempts, 'retrying', $e->getMessage());
                $this->audit->log($job->requestedBy, 'provisioning.retrying', 'provisioning_job', (string)$job->id, 'failure', ['error'=>$e->getMessage(), 'attempts'=>$attempts]);
            }
        }

        return $this->jobs->findById($jobId);
    }

    /**
     * Sanitize an error/message before persisting to last_error or logs so that
     * credentials embedded in unexpected error strings are never leaked.
     */
    public static function sanitizeError(string $message): string
    {
        $patterns = [
            '/(password|passwd|pwd)\s*[=:]\s*\S+/i' => '$1=[REDACTED]',
            '/(api[_-]?key|access[_-]?key|secret|token|signature|authorization|credential)\s*[=:]\s*\S+/i' => '$1=[REDACTED]',
            '/Bearer\s+[A-Za-z0-9._\-]+/i' => 'Bearer [REDACTED]',
        ];
        $sanitized = preg_replace(array_keys($patterns), array_values($patterns), $message);
        return $sanitized === null ? $message : $sanitized;
    }

    /**
     * Execute a job that has already been claimed by a worker (P1).
     *
     * The worker claims the job (repository claimNext) which increments attempts
     * and moves status to 'provisioning'. This method then performs ONLY the
     * allow-listed provisioner operation for the claimed job and records the
     * outcome:
     *   - success  -> status 'active'   (claim released, completed_at set)
     *   - retryable failure -> status 'retrying' with a bounded backoff wait
     *   - final failure      -> status 'failed'  (claim released, completed_at set)
     *
     * Ownership is enforced: a job claimed by a different worker cannot be
     * executed or released by this call.
     */
    public function executeClaimedJob(int $jobId, string $workerId, int $backoffBaseSeconds = 5, int $backoffMaxSeconds = 300): ProvisioningJob
    {
        $job = $this->jobs->findById($jobId);
        if (!$job) {
            throw new \RuntimeException('Job not found', 404);
        }
        if ($job->status !== 'provisioning' || $job->workerId !== $workerId) {
            throw new \RuntimeException('Job not claimed by this worker', 409);
        }

        // Refresh the lease heartbeat so a healthy long operation is not reclaimed.
        $this->jobs->heartbeat($job->id, $workerId);

        $acctRow = $this->db->fetch("SELECT * FROM hosting_accounts WHERE id=?", [$job->hostingAccountId]);
        if (!$acctRow) {
            $this->jobs->failClaimed($job->id, $workerId, 'Hosting account not found');
            return $this->jobs->findById($jobId);
        }
        $acct = \App\Models\HostingAccount::fromArray($acctRow);
        $planRow = $this->db->fetch("SELECT * FROM hosting_plans WHERE id=?", [$acct->planId]);
        if ($planRow) {
            $acct->plan = \App\Models\HostingPlan::fromArray($planRow);
        }

        $decoded = $job->payload !== null ? json_decode($job->payload, true) : [];
        $payload = is_array($decoded) ? $decoded : [];

        try {
            $result = $this->executeOperation($acct, $job->operation, $payload);
            if ($result->success) {
                $this->jobs->completeClaimed($job->id, $workerId);
                if ($job->operation === 'createHostingAccount' && $job->nodeId) {
                    $this->nodes->incrementLoad($job->nodeId);
                }
                $this->audit->log($job->requestedBy, 'provisioning.active', 'provisioning_job', (string)$job->id, 'success', ['operation'=>$job->operation, 'attempts'=>$job->attempts]);
            } else {
                throw new \RuntimeException($result->message);
            }
        } catch (\Throwable $e) {
            $attempts = $job->attempts;
            $error = self::sanitizeError($e->getMessage());
            if ($attempts >= $job->maxAttempts) {
                $this->jobs->failClaimed($job->id, $workerId, $error);
                $this->audit->log($job->requestedBy, 'provisioning.failed', 'provisioning_job', (string)$job->id, 'failure', ['error'=>$error, 'attempts'=>$attempts]);
            } else {
                $backoff = max(1, min((int)$backoffBaseSeconds * (int)(2 ** max(0, $attempts - 1)), (int)$backoffMaxSeconds));
                $this->jobs->requeueForRetry($job->id, $workerId, $error, $backoff);
                $this->audit->log($job->requestedBy, 'provisioning.retrying', 'provisioning_job', (string)$job->id, 'failure', ['error'=>$error, 'attempts'=>$attempts, 'retry_in_seconds'=>$backoff]);
            }
        }

        return $this->jobs->findById($jobId);
    }

    public function retryJob(int $jobId, int $actorId): ProvisioningJob
    {
        $job = $this->jobs->findById($jobId);
        if (!$job) throw new \RuntimeException('Job not found', 404);
        if (!$job->canRetry()) {
            throw new \RuntimeException('Job cannot be retried (status ' . $job->status . ', attempts ' . $job->attempts . '/' . $job->maxAttempts . ')', 400);
        }
        $this->jobs->updateStatus($job->id, 'queued');
        $this->audit->log($actorId, 'provisioning.retry', 'provisioning_job', (string)$job->id, 'success', ['operation'=>$job->operation]);
        return $this->processJob($job->id);
    }

    public function failJob(int $jobId, string $reason, int $actorId): void
    {
        $this->jobs->updateStatus($jobId, 'failed', $reason);
        $this->audit->log($actorId, 'provisioning.failed_manual', 'provisioning_job', (string)$jobId, 'failure', ['reason'=>$reason]);
    }

    private function executeOperation(\App\Models\HostingAccount $acct, string $op, array $payload): \App\Services\Provisioning\ProvisionResult
    {
        return match($op) {
            'createHostingAccount' => $this->provisioner->createHostingAccount($acct),
            'suspendHostingAccount' => $this->provisioner->suspendHostingAccount($acct),
            'activateHostingAccount' => $this->provisioner->activateHostingAccount($acct),
            'terminateHostingAccount' => $this->provisioner->terminateHostingAccount($acct),
            'createSubdomain' => $this->provisioner->createSubdomain($acct, $payload['subdomain'] ?? '', $payload['fullDomain'] ?? ''),
            'deleteSubdomain' => $this->provisioner->deleteSubdomain($acct, $payload['fullDomain'] ?? ''),
            'createDatabase' => $this->provisioner->createDatabase($acct, $payload['dbName'] ?? ''),
            'deleteDatabase' => $this->provisioner->deleteDatabase($acct, $payload['dbName'] ?? ''),
            'createDatabaseUser' => $this->provisioner->createDatabaseUser($acct, $payload['username'] ?? '', $payload['password'] ?? ''),
            'deleteDatabaseUser' => $this->provisioner->deleteDatabaseUser($acct, $payload['username'] ?? ''),
            default => throw new \RuntimeException('Unknown operation'),
        };
    }

    private function selectNode(): ?\App\Models\HostingNode
    {
        $nodes = $this->nodes->active();
        if (empty($nodes)) return null;
        usort($nodes, fn($a,$b)=> $a->currentAccounts <=> $b->currentAccounts);
        return $nodes[0];
    }

    private function generateIdempotencyKey(int $accountId, string $op, array $payload): string
    {
        // Deterministic for same account+op+payload hash, but allow caller to override for true idempotency
        $hash = hash('sha256', $accountId . '|' . $op . '|' . json_encode($payload, JSON_UNESCAPED_SLASHES));
        return substr($hash, 0, 32) . '-' . bin2hex(random_bytes(4));
    }

    // --- Secure Provisioning API Auth ---
    public static function generateApiKey(): array
    {
        $plain = 'fhm_' . bin2hex(random_bytes(16));
        $hash = hash('sha256', $plain);
        $preview = substr($plain, 0, 8) . '...';
        return ['plain'=>$plain, 'hash'=>$hash, 'preview'=>$preview];
    }

    /**
     * Sign a provisioning request.
     * Returns headers: X-Timestamp, X-Nonce, X-Signature
     */
    public static function signRequest(string $apiKeyPlain, string $method, string $path, string $body, int $timestamp, string $nonce): string
    {
        $payload = $method . '|' . $path . '|' . hash('sha256', $body) . '|' . $timestamp . '|' . $nonce;
        return hash_hmac('sha256', $payload, $apiKeyPlain);
    }

    public static function verifyRequest(string $apiKeyPlain, string $method, string $path, string $body, int $timestamp, string $nonce, string $signature, Database $db): bool
    {
        // TTL check
        if (abs(time() - $timestamp) > self::SIGN_TTL) {
            return false;
        }
        // Replay protection: nonce must be unique in TTL window
        $exists = $db->fetchColumn("SELECT 1 FROM rate_limits WHERE rate_key=? LIMIT 1", ['nonce:' . $nonce]);
        if ($exists) {
            return false;
        }
        // Store nonce
        $db->execute("INSERT INTO rate_limits (rate_key) VALUES (?)", ['nonce:' . $nonce]);

        $expected = self::signRequest($apiKeyPlain, $method, $path, $body, $timestamp, $nonce);
        return hash_equals($expected, $signature);
    }
}
