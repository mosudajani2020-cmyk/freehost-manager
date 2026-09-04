<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\Subdomain;

final class SubdomainRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?Subdomain
    {
        $row = $this->db->fetch("SELECT * FROM subdomains WHERE id=?", [$id]);
        return $row ? Subdomain::fromArray($row) : null;
    }

    public function findByHosting(int $hostingId): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM subdomains WHERE hosting_account_id=? ORDER BY id DESC", [$hostingId]);
        return array_map([Subdomain::class, 'fromArray'], $rows);
    }

    public function countByHosting(int $hostingId): int
    {
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM subdomains WHERE hosting_account_id=?", [$hostingId]);
    }

    public function existsFullDomain(string $fullDomain): bool
    {
        return (bool) $this->db->fetchColumn("SELECT 1 FROM subdomains WHERE full_domain=? LIMIT 1", [strtolower($fullDomain)]);
    }

    public function create(int $hostingId, ?int $domainId, string $subdomain, string $fullDomain): Subdomain
    {
        $this->db->query(
            "INSERT INTO subdomains (hosting_account_id, domain_id, subdomain, full_domain, status) VALUES (?,?,?,?, 'active')",
            [$hostingId, $domainId, strtolower($subdomain), strtolower($fullDomain)]
        );
        return $this->findById((int) $this->db->lastInsertId());
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM subdomains WHERE id=?", [$id]);
    }
}
