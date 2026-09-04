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

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
