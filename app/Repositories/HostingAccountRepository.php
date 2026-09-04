<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\HostingAccount;

final class HostingAccountRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?HostingAccount
    {
        $row = $this->db->fetch("SELECT * FROM hosting_accounts WHERE id=?", [$id]);
        if (!$row) return null;
        $acct = HostingAccount::fromArray($row);
        $planRow = $this->db->fetch("SELECT * FROM hosting_plans WHERE id=?", [$acct->planId]);
        if ($planRow) {
            $acct->plan = \App\Models\HostingPlan::fromArray($planRow);
        }
        return $acct;
    }

    public function findByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM hosting_accounts WHERE user_id=? ORDER BY id DESC LIMIT ? OFFSET ?", [$userId, $limit, $offset]);
        return $this->hydrateMany($rows);
    }

    public function findAll(int $limit = 50, int $offset = 0, string $search = ''): array
    {
        if ($search !== '') {
            $like = '%' . $search . '%';
            $rows = $this->db->fetchAll(
                "SELECT ha.* FROM hosting_accounts ha JOIN users u ON u.id=ha.user_id WHERE ha.username LIKE ? OR u.email LIKE ? OR u.username LIKE ? ORDER BY ha.id DESC LIMIT ? OFFSET ?",
                [$like, $like, $like, $limit, $offset]
            );
        } else {
            $rows = $this->db->fetchAll("SELECT * FROM hosting_accounts ORDER BY id DESC LIMIT ? OFFSET ?", [$limit, $offset]);
        }
        return $this->hydrateMany($rows);
    }

    public function count(?int $userId = null, string $search = ''): int
    {
        if ($userId !== null) {
            return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts WHERE user_id=?", [$userId]);
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            return (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM hosting_accounts ha JOIN users u ON u.id=ha.user_id WHERE ha.username LIKE ? OR u.email LIKE ? OR u.username LIKE ?",
                [$like, $like, $like]
            );
        }
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM hosting_accounts");
    }

    public function create(array $data): HostingAccount
    {
        $this->db->query(
            "INSERT INTO hosting_accounts (user_id, plan_id, username, domain, status, root_path) VALUES (?,?,?,?,?,?)",
            [$data['user_id'], $data['plan_id'], $data['username'], $data['domain'] ?? null, $data['status'] ?? 'pending', $data['root_path']]
        );
        return $this->findById((int) $this->db->lastInsertId());
    }

    public function updateStatus(int $id, string $status, array $extra = []): void
    {
        $fields = "status=?";
        $params = [$status];
        if ($status === 'suspended') {
            $fields .= ", suspended_at=NOW(), terminated_at=NULL";
        } elseif ($status === 'active') {
            $fields .= ", suspended_at=NULL, terminated_at=NULL";
        } elseif ($status === 'terminated') {
            $fields .= ", terminated_at=NOW(), suspended_at=NULL";
        }
        if (isset($extra['domain'])) {
            $fields .= ", domain=?";
            $params[] = $extra['domain'];
        }
        $params[] = $id;
        $this->db->execute("UPDATE hosting_accounts SET {$fields}, updated_at=NOW() WHERE id=?", $params);
    }

    public function existsUsername(string $username): bool
    {
        return (bool) $this->db->fetchColumn("SELECT 1 FROM hosting_accounts WHERE username=? LIMIT 1", [$username]);
    }

    private function hydrateMany(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $acct = HostingAccount::fromArray($row);
            $planRow = $this->db->fetch("SELECT * FROM hosting_plans WHERE id=?", [$acct->planId]);
            if ($planRow) $acct->plan = \App\Models\HostingPlan::fromArray($planRow);
            $out[] = $acct;
        }
        return $out;
    }
}
