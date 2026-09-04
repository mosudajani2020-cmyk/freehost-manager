<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\HostingNode;

final class HostingNodeRepository
{
    public function __construct(private readonly Database $db) {}

    public function all(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM hosting_nodes ORDER BY id ASC");
        return array_map([HostingNode::class,'fromArray'], $rows);
    }

    public function active(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM hosting_nodes WHERE status='active' ORDER BY id ASC");
        return array_map([HostingNode::class,'fromArray'], $rows);
    }

    public function findById(int $id): ?HostingNode
    {
        $r = $this->db->fetch("SELECT * FROM hosting_nodes WHERE id=?", [$id]);
        return $r ? HostingNode::fromArray($r) : null;
    }

    public function create(array $data): HostingNode
    {
        $this->db->query(
            "INSERT INTO hosting_nodes (name, hostname, ip_address, region, status, max_accounts, api_url, api_key_hash, api_key_preview) VALUES (?,?,?,?,?,?,?,?,?)",
            [
                $data['name'],
                $data['hostname'],
                $data['ip_address'] ?? null,
                $data['region'] ?? null,
                $data['status'] ?? 'active',
                $data['max_accounts'] ?? 100,
                $data['api_url'] ?? null,
                $data['api_key_hash'] ?? null,
                $data['api_key_preview'] ?? null,
            ]
        );
        return $this->findById((int)$this->db->lastInsertId());
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE hosting_nodes SET name=?, hostname=?, ip_address=?, region=?, status=?, max_accounts=?, api_url=? WHERE id=?",
            [$data['name'],$data['hostname'],$data['ip_address'] ?? null,$data['region'] ?? null,$data['status'] ?? 'active',$data['max_accounts'] ?? 100,$data['api_url'] ?? null,$id]
        );
    }

    public function incrementLoad(int $id): void
    {
        $this->db->execute("UPDATE hosting_nodes SET current_accounts = current_accounts + 1 WHERE id=?", [$id]);
    }

    public function count(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM hosting_nodes");
    }
}
