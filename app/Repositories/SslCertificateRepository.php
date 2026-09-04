<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\SslCertificate;

final class SslCertificateRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?SslCertificate
    {
        $r = $this->db->fetch("SELECT * FROM ssl_certificates WHERE id=?", [$id]);
        return $r ? SslCertificate::fromArray($r) : null;
    }

    public function findByHostname(string $hostname): ?SslCertificate
    {
        $r = $this->db->fetch("SELECT * FROM ssl_certificates WHERE hostname=?", [strtolower($hostname)]);
        return $r ? SslCertificate::fromArray($r) : null;
    }

    public function findByHosting(int $hostingId): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM ssl_certificates WHERE hosting_account_id=? ORDER BY id DESC", [$hostingId]);
        return array_map([SslCertificate::class,'fromArray'], $rows);
    }

    public function create(int $hostingId, string $hostname, string $status='pending', string $provider='local_mock', ?string $expiresAt=null): SslCertificate
    {
        $this->db->query("INSERT INTO ssl_certificates (hosting_account_id, hostname, status, provider, expires_at) VALUES (?,?,?,?,?)", [$hostingId, strtolower($hostname), $status, $provider, $expiresAt]);
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status, ?string $expiresAt=null, ?string $error=null): void
    {
        $this->db->execute("UPDATE ssl_certificates SET status=?, expires_at=COALESCE(?, expires_at), last_error=? WHERE id=?", [$status, $expiresAt, $error, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM ssl_certificates WHERE id=?", [$id]);
    }
}
