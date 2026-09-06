<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\ProvisioningJob;

final class ProvisioningJobRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?ProvisioningJob
    {
        $r = $this->db->fetch("SELECT * FROM provisioning_jobs WHERE id=?", [$id]);
        return $r ? ProvisioningJob::fromArray($r) : null;
    }

    public function findByUuid(string $uuid): ?ProvisioningJob
    {
        $r = $this->db->fetch("SELECT * FROM provisioning_jobs WHERE job_uuid=?", [$uuid]);
        return $r ? ProvisioningJob::fromArray($r) : null;
    }

    public function findByIdempotency(string $key): ?ProvisioningJob
    {
        $r = $this->db->fetch("SELECT * FROM provisioning_jobs WHERE idempotency_key=?", [$key]);
        return $r ? ProvisioningJob::fromArray($r) : null;
    }

    public function findByAccount(int $accountId, int $limit=20): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM provisioning_jobs WHERE hosting_account_id=? ORDER BY id DESC LIMIT ?", [$accountId,$limit]);
        return array_map([ProvisioningJob::class,'fromArray'], $rows);
    }

    public function all(int $limit=50, int $offset=0, string $status=''): array
    {
        if ($status !== '') {
            $rows = $this->db->fetchAll("SELECT * FROM provisioning_jobs WHERE status=? ORDER BY id DESC LIMIT ? OFFSET ?", [$status,$limit,$offset]);
        } else {
            $rows = $this->db->fetchAll("SELECT * FROM provisioning_jobs ORDER BY id DESC LIMIT ? OFFSET ?", [$limit,$offset]);
        }
        return array_map([ProvisioningJob::class,'fromArray'], $rows);
    }

    public function count(?string $status=null): int
    {
        if ($status) return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs WHERE status=?", [$status]);
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM provisioning_jobs");
    }

    public function create(array $data): ProvisioningJob
    {
        $uuid = $data['job_uuid'] ?? $this->generateUuid();
        $this->db->query(
            "INSERT INTO provisioning_jobs (job_uuid, hosting_account_id, node_id, operation, payload, status, attempts, max_attempts, idempotency_key, requested_by) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $uuid,
                $data['hosting_account_id'],
                $data['node_id'] ?? null,
                $data['operation'],
                isset($data['payload']) ? json_encode($data['payload']) : null,
                $data['status'] ?? 'pending',
                $data['attempts'] ?? 0,
                $data['max_attempts'] ?? 3,
                $data['idempotency_key'],
                $data['requested_by'] ?? null,
            ]
        );
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status, ?string $error=null, ?string $completedAt=null): void
    {
        $sql = "UPDATE provisioning_jobs SET status=?, last_error=?, updated_at=NOW()";
        $params = [$status, $error];
        if ($completedAt !== null) {
            $sql .= ", completed_at=?";
            $params[] = $completedAt;
        } elseif (in_array($status, ['active','failed','terminated'], true)) {
            $sql .= ", completed_at=NOW()";
        }
        $sql .= " WHERE id=?";
        $params[] = $id;
        $this->db->execute($sql, $params);
    }

    public function incrementAttempts(int $id): void
    {
        $this->db->execute("UPDATE provisioning_jobs SET attempts = attempts + 1 WHERE id=?", [$id]);
    }

    public function updateAttemptsAndStatus(int $id, int $attempts, string $status, ?string $error=null): void
    {
        $this->db->execute("UPDATE provisioning_jobs SET attempts=?, status=?, last_error=? WHERE id=?", [$attempts,$status,$error,$id]);
    }

    // --- P1 worker queue support -------------------------------------------

    private static ?bool $skipLockedSupported = null;

    /**
     * Row-lock clause for claiming. Uses FOR UPDATE SKIP LOCKED where supported
     * (MySQL 8.0.1+, MariaDB 10.6+), transparently falling back to FOR UPDATE on
     * older engines. Feature-detected once per process — never silently assumed.
     */
    private function lockClause(): string
    {
        if (self::$skipLockedSupported === null) {
            try {
                $this->db->query('SELECT 1 FOR UPDATE SKIP LOCKED');
                self::$skipLockedSupported = true;
            } catch (\Throwable) {
                self::$skipLockedSupported = false;
            }
        }
        return self::$skipLockedSupported ? 'FOR UPDATE SKIP LOCKED' : 'FOR UPDATE';
    }

    /**
     * Atomically claim the next available job.
     *
     * Holds the row lock ONLY for the short claim transaction (select + mark
     * running). The infrastructure operation is executed after commit by the
     * calling worker and is never performed under a row lock.
     *
     * A job must not be processed simultaneously by two workers: the second
     * worker either skips the locked row (SKIP LOCKED) or waits and then finds
     * the status already moved to 'provisioning'.
     */
    public function claimNext(string $workerId, int $leaseSeconds): ?ProvisioningJob
    {
        if ($leaseSeconds < 1) {
            $leaseSeconds = 60;
        }
        $lock = $this->lockClause();
        $this->db->beginTransaction();
        try {
            $row = $this->db->fetch(
                "SELECT * FROM provisioning_jobs
                 WHERE status IN ('pending','queued','retrying')
                   AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
                 ORDER BY id ASC
                 LIMIT 1
                 {$lock}",
                []
            );
            if ($row === null) {
                $this->db->rollBack();
                return null;
            }
            $this->db->execute(
                "UPDATE provisioning_jobs
                 SET status='provisioning', worker_id=?, claimed_at=NOW(), heartbeat_at=NOW(),
                     attempts = attempts + 1, next_attempt_at=NULL, updated_at=NOW()
                 WHERE id=?",
                [$workerId, $row['id']]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            try {
                $this->db->rollBack();
            } catch (\Throwable) {
                // ignore rollback failure — claim already failed
            }
            throw new \RuntimeException('Job claim failed: ' . $e->getMessage(), 0, $e);
        }
        return $this->findById((int)$row['id']);
    }

    /**
     * Reclaim jobs whose lease has gone stale (claimed_at older than the lease).
     * Each stale job is reclaimed inside its own short locked transaction, so two
     * workers recovering at the same time cannot both claim the same stale job.
     * Attempts are incremented per reclaim since each reclaim is a new execution.
     */
    public function recoverStale(string $workerId, int $leaseSeconds): int
    {
        if ($leaseSeconds < 1) {
            $leaseSeconds = 60;
        }
        $lock = $this->lockClause();
        $recovered = 0;
        while (true) {
            $this->db->beginTransaction();
            try {
                $row = $this->db->fetch(
                    "SELECT * FROM provisioning_jobs
                     WHERE status='provisioning'
                       AND claimed_at IS NOT NULL
                       AND claimed_at < NOW() - INTERVAL " . (int)$leaseSeconds . " SECOND
                     ORDER BY id ASC
                     LIMIT 1
                     {$lock}",
                    []
                );
                if ($row === null) {
                    $this->db->rollBack();
                    break;
                }
                $this->db->execute(
                    "UPDATE provisioning_jobs
                     SET worker_id=?, claimed_at=NOW(), heartbeat_at=NOW(),
                         attempts = attempts + 1, next_attempt_at=NULL, updated_at=NOW()
                     WHERE id=? AND status='provisioning'",
                    [$workerId, $row['id']]
                );
                $this->db->commit();
                $recovered++;
            } catch (\Throwable $e) {
                try {
                    $this->db->rollBack();
                } catch (\Throwable) {
                    // ignore rollback failure — recovery already failed
                }
                throw new \RuntimeException('Stale job recovery failed: ' . $e->getMessage(), 0, $e);
            }
        }
        return $recovered;
    }

    /** Refresh the lease heartbeat — job stays owned by the same worker only. */
    public function heartbeat(int $id, string $workerId): void
    {
        $this->db->execute(
            "UPDATE provisioning_jobs SET heartbeat_at=NOW(), updated_at=NOW() WHERE id=? AND worker_id=?",
            [$id, $workerId]
        );
    }

    /** Mark a claimed job completed. Safe only for the worker that owns the lease. */
    public function completeClaimed(int $id, string $workerId): void
    {
        $this->db->execute(
            "UPDATE provisioning_jobs
             SET status='active', worker_id=NULL, claimed_at=NULL, heartbeat_at=NULL,
                 next_attempt_at=NULL, completed_at=NOW(), updated_at=NOW()
             WHERE id=? AND worker_id=?",
            [$id, $workerId]
        );
    }

    /** Mark a claimed job permanently failed. Safe only for the owning worker. */
    public function failClaimed(int $id, string $workerId, ?string $error): void
    {
        $this->db->execute(
            "UPDATE provisioning_jobs
             SET status='failed', worker_id=NULL, claimed_at=NULL, heartbeat_at=NULL,
                 next_attempt_at=NULL, last_error=?, completed_at=NOW(), updated_at=NOW()
             WHERE id=? AND worker_id=?",
            [$error, $id, $workerId]
        );
    }

    /** Re-queue a claimed job for a bounded backoff retry. */
    public function requeueForRetry(int $id, string $workerId, ?string $error, int $delaySeconds): void
    {
        if ($delaySeconds < 1) {
            $delaySeconds = 1;
        }
        $this->db->execute(
            "UPDATE provisioning_jobs
             SET status='retrying', worker_id=NULL, claimed_at=NULL, heartbeat_at=NULL,
                 next_attempt_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error=?, updated_at=NOW()
             WHERE id=? AND worker_id=?",
            [$delaySeconds, $error, $id, $workerId]
        );
    }

    /** Release a claimed job back to the queue without consuming a retry attempt. */
    public function requeue(int $id, string $workerId, ?string $reason = null): void
    {
        $this->db->execute(
            "UPDATE provisioning_jobs
             SET status='queued', worker_id=NULL, claimed_at=NULL, heartbeat_at=NULL,
                 next_attempt_at=NULL, last_error=?, updated_at=NOW()
             WHERE id=? AND worker_id=?",
            [$reason, $id, $workerId]
        );
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
