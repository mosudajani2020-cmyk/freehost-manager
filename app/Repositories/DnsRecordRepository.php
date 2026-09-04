<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\DnsRecord;

final class DnsRecordRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?DnsRecord
    {
        $r = $this->db->fetch("SELECT * FROM dns_records WHERE id=?", [$id]);
        return $r ? DnsRecord::fromArray($r) : null;
    }

    public function findByHostname(string $hostname): ?DnsRecord
    {
        $r = $this->db->fetch("SELECT * FROM dns_records WHERE hostname=?", [strtolower($hostname)]);
        return $r ? DnsRecord::fromArray($r) : null;
    }

    public function findByHosting(int $hostingId): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM dns_records WHERE hosting_account_id=? ORDER BY id DESC", [$hostingId]);
        return array_map([DnsRecord::class,'fromArray'], $rows);
    }

    public function existsHostname(string $hostname): bool
    {
        return (bool)$this->db->fetchColumn("SELECT 1 FROM dns_records WHERE hostname=? LIMIT 1", [strtolower($hostname)]);
    }

    public function create(int $hostingId, string $hostname, string $type='A', string $value='127.0.0.1', int $ttl=3600, string $status='pending'): DnsRecord
    {
        $this->db->query("INSERT INTO dns_records (hosting_account_id, hostname, type, value, ttl, status) VALUES (?,?,?,?,?,?)", [$hostingId, strtolower($hostname), $type, $value, $ttl, $status]);
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->execute("UPDATE dns_records SET status=? WHERE id=?", [$status, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM dns_records WHERE id=?", [$id]);
    }

    public function countByHosting(int $hostingId): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM dns_records WHERE hosting_account_id=?", [$hostingId]);
    }
}
