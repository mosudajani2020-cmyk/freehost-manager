<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\Backup;

final class BackupRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?Backup
    {
        $r = $this->db->fetch("SELECT * FROM backups WHERE id=?", [$id]);
        return $r ? Backup::fromArray($r) : null;
    }

    public function findByHosting(int $hostingId, int $limit=20): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM backups WHERE hosting_account_id=? ORDER BY id DESC LIMIT ?", [$hostingId, $limit]);
        return array_map([Backup::class,'fromArray'], $rows);
    }

    public function all(int $limit=50, int $offset=0, string $status=''): array
    {
        if ($status !== '') {
            $rows = $this->db->fetchAll("SELECT * FROM backups WHERE status=? ORDER BY id DESC LIMIT ? OFFSET ?", [$status,$limit,$offset]);
        } else {
            $rows = $this->db->fetchAll("SELECT * FROM backups ORDER BY id DESC LIMIT ? OFFSET ?", [$limit,$offset]);
        }
        return array_map([Backup::class,'fromArray'], $rows);
    }

    public function count(?string $status=null): int
    {
        if ($status) return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM backups WHERE status=?", [$status]);
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM backups");
    }

    public function create(int $hostingId, string $type='full', int $retentionDays=7, ?int $requestedBy=null): Backup
    {
        $expires = date('Y-m-d H:i:s', time() + $retentionDays*24*3600);
        $this->db->query("INSERT INTO backups (hosting_account_id, type, status, retention_days, expires_at, requested_by) VALUES (?,?,?,?,?,?)", [$hostingId, $type, 'pending', $retentionDays, $expires, $requestedBy]);
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status, ?int $sizeBytes=null, ?string $filePath=null, ?string $error=null): void
    {
        $sql = "UPDATE backups SET status=?";
        $params = [$status];
        if ($sizeBytes !== null) { $sql .= ", size_bytes=?"; $params[] = $sizeBytes; }
        if ($filePath !== null) { $sql .= ", file_path=?"; $params[] = $filePath; }
        if ($error !== null) { $sql .= ", last_error=?"; $params[] = $error; }
        if (in_array($status, ['completed','failed','expired'], true)) { $sql .= ", completed_at=NOW()"; }
        $sql .= " WHERE id=?";
        $params[] = $id;
        $this->db->execute($sql, $params);
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM backups WHERE id=?", [$id]);
    }

    public function expireOld(): int
    {
        // Mark expired where expires_at < NOW() and not already expired
        return $this->db->execute("UPDATE backups SET status='expired' WHERE status IN ('completed','pending','running') AND expires_at < NOW()");
    }
}
