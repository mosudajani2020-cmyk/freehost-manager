<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Models\User;

final class UserRepository
{
    public function __construct(private readonly Database $db) {}

    public function findById(int $id): ?User
    {
        $row = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
        if ($row === null) {
            return null;
        }
        return $this->hydrate($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetch("SELECT * FROM users WHERE email = ?", [strtolower(trim($email))]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $row = $this->db->fetch("SELECT * FROM users WHERE username = ?", [trim($username)]);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByLogin(string $login): ?User
    {
        $login = trim($login);
        $row = $this->db->fetch(
            "SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1",
            [strtolower($login), $login]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function existsByEmail(string $email): bool
    {
        return (bool) $this->db->fetchColumn("SELECT 1 FROM users WHERE email = ? LIMIT 1", [strtolower(trim($email))]);
    }

    public function existsByUsername(string $username): bool
    {
        return (bool) $this->db->fetchColumn("SELECT 1 FROM users WHERE username = ? LIMIT 1", [trim($username)]);
    }

    public function create(array $data): User
    {
        $this->db->query(
            "INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['full_name'],
                $data['username'],
                strtolower(trim($data['email'])),
                $data['password_hash'],
                $data['status'] ?? 'pending',
                $data['email_verified_at'] ?? null,
            ]
        );
        $id = (int) $this->db->lastInsertId();
        // Assign default customer role
        $roleId = $this->db->fetchColumn("SELECT id FROM roles WHERE name = ? LIMIT 1", ['customer']);
        if ($roleId) {
            $this->db->execute("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$id, $roleId]);
        }
        return $this->findById($id);
    }

    public function updateLastLogin(int $id): void
    {
        $this->db->execute("UPDATE users SET last_login_at = NOW(), failed_login_count = 0, lockout_until = NULL WHERE id = ?", [$id]);
    }

    public function incrementFailedLogin(int $id): void
    {
        $this->db->execute("UPDATE users SET failed_login_count = failed_login_count + 1 WHERE id = ?", [$id]);
    }

    public function setLockout(int $id, string $until): void
    {
        $this->db->execute("UPDATE users SET lockout_until = ? WHERE id = ?", [$until, $id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $this->db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $id]);
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM users ORDER BY id DESC LIMIT ? OFFSET ?", [$limit, $offset]);
        return array_map(fn($r) => $this->hydrate($r), $rows);
    }

    public function count(): int
    {
        return (int) $this->db->fetchColumn("SELECT COUNT(*) FROM users");
    }

    private function hydrate(array $row): User
    {
        $roles = $this->db->fetchAll(
            "SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?",
            [$row['id']]
        );
        $roleNames = array_column($roles, 'name');
        $perms = $this->db->fetchAll(
            "SELECT p.name FROM permissions p INNER JOIN role_permissions rp ON rp.permission_id = p.id INNER JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?",
            [$row['id']]
        );
        $permNames = array_column($perms, 'name');
        $row['roles'] = $roleNames;
        $row['permissions'] = $permNames;
        return User::fromArray($row);
    }
}
