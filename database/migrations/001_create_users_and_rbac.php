<?php
// Migration 001 — users, roles, permissions, pivots
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('pending','active','suspended','banned') NOT NULL DEFAULT 'pending',
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    lockout_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Seed roles
$pdo->exec("INSERT IGNORE INTO roles (name, display_name, description) VALUES ('admin','Administrator','Full platform access'), ('customer','Customer','Standard hosting customer')");

// Seed permissions (granular)
$permissions = [
    ['users.view','View users'], ['users.create','Create users'], ['users.edit','Edit users'], ['users.suspend','Suspend users'],
    ['hosting.view','View hosting'], ['hosting.create','Create hosting'], ['hosting.suspend','Suspend hosting'], ['hosting.delete','Delete hosting'],
    ['files.view','View files'], ['files.upload','Upload files'], ['files.delete','Delete files'],
    ['databases.create','Create databases'], ['databases.delete','Delete databases'],
    ['plans.manage','Manage plans'], ['audit.view','View audit'], ['settings.manage','Manage settings'],
];
$stmt = $pdo->prepare("INSERT IGNORE INTO permissions (name, display_name) VALUES (?, ?)");
foreach ($permissions as $p) {
    $stmt->execute($p);
}

// Assign all permissions to admin, limited to customer
$adminId = $pdo->query("SELECT id FROM roles WHERE name='admin' LIMIT 1")->fetchColumn();
$customerId = $pdo->query("SELECT id FROM roles WHERE name='customer' LIMIT 1")->fetchColumn();
if ($adminId) {
    $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT {$adminId}, id FROM permissions");
}
if ($customerId) {
    $customerPerms = ['hosting.view','hosting.create','files.view','files.upload','files.delete','databases.create','databases.delete'];
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
    $ins = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($customerPerms as $perm) {
        $stmt->execute([$perm]);
        $pid = $stmt->fetchColumn();
        if ($pid) $ins->execute([$customerId, $pid]);
    }
}
