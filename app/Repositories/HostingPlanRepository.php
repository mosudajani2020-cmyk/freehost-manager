<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\HostingPlan;

final class HostingPlanRepository
{
    public function __construct(private readonly Database $db) {}

    public function all(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM hosting_plans ORDER BY storage_limit_mb ASC");
        return array_map([HostingPlan::class, 'fromArray'], $rows);
    }

    public function active(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM hosting_plans WHERE status='active' ORDER BY storage_limit_mb ASC");
        return array_map([HostingPlan::class, 'fromArray'], $rows);
    }

    public function findById(int $id): ?HostingPlan
    {
        $row = $this->db->fetch("SELECT * FROM hosting_plans WHERE id=?", [$id]);
        return $row ? HostingPlan::fromArray($row) : null;
    }

    public function findBySlug(string $slug): ?HostingPlan
    {
        $row = $this->db->fetch("SELECT * FROM hosting_plans WHERE slug=?", [$slug]);
        return $row ? HostingPlan::fromArray($row) : null;
    }

    public function getDefault(): ?HostingPlan
    {
        $row = $this->db->fetch("SELECT * FROM hosting_plans WHERE is_default=1 LIMIT 1");
        if ($row) return HostingPlan::fromArray($row);
        $row = $this->db->fetch("SELECT * FROM hosting_plans WHERE status='active' ORDER BY id ASC LIMIT 1");
        return $row ? HostingPlan::fromArray($row) : null;
    }

    public function create(array $data): HostingPlan
    {
        $this->db->query(
            "INSERT INTO hosting_plans (name, slug, description, storage_limit_mb, bandwidth_limit_mb, database_limit, domain_limit, subdomain_limit, status, is_default) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                $data['storage_limit_mb'],
                $data['bandwidth_limit_mb'],
                $data['database_limit'],
                $data['domain_limit'],
                $data['subdomain_limit'],
                $data['status'] ?? 'active',
                $data['is_default'] ?? 0,
            ]
        );
        return $this->findById((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $data): HostingPlan
    {
        $this->db->query(
            "UPDATE hosting_plans SET name=?, slug=?, description=?, storage_limit_mb=?, bandwidth_limit_mb=?, database_limit=?, domain_limit=?, subdomain_limit=?, status=?, is_default=?, updated_at=NOW() WHERE id=?",
            [
                $data['name'],
                $data['slug'],
                $data['description'] ?? null,
                $data['storage_limit_mb'],
                $data['bandwidth_limit_mb'],
                $data['database_limit'],
                $data['domain_limit'],
                $data['subdomain_limit'],
                $data['status'] ?? 'active',
                $data['is_default'] ?? 0,
                $id,
            ]
        );
        // If setting default, unset others
        if (!empty($data['is_default'])) {
            $this->db->execute("UPDATE hosting_plans SET is_default=0 WHERE id!=?", [$id]);
        }
        return $this->findById($id);
    }

    public function count(): int
    {
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM hosting_plans");
    }
}
