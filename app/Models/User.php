<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $fullName,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $status,
        public readonly ?string $emailVerifiedAt,
        public readonly ?string $lastLoginAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public array $roles = [],
        public array $permissions = [],
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            fullName: $row['full_name'],
            username: $row['username'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            status: $row['status'],
            emailVerifiedAt: $row['email_verified_at'] ?? null,
            lastLoginAt: $row['last_login_at'] ?? null,
            createdAt: $row['created_at'],
            updatedAt: $row['updated_at'],
            roles: $row['roles'] ?? [],
            permissions: $row['permissions'] ?? [],
        );
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }
}
