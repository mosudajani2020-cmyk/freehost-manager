<?php
$pdo->exec("
CREATE TABLE IF NOT EXISTS hosting_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    description TEXT NULL,
    storage_limit_mb INT UNSIGNED NOT NULL DEFAULT 500,
    bandwidth_limit_mb INT UNSIGNED NOT NULL DEFAULT 5120,
    database_limit SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    domain_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    subdomain_limit SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plans_slug (slug),
    UNIQUE KEY uq_plans_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS hosting_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(32) NOT NULL,
    domain VARCHAR(190) NULL,
    status ENUM('pending','active','suspended','terminated') NOT NULL DEFAULT 'pending',
    storage_used_mb DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    bandwidth_used_mb DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    root_path VARCHAR(255) NOT NULL,
    suspended_at TIMESTAMP NULL DEFAULT NULL,
    terminated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hosting_username (username),
    INDEX idx_hosting_user (user_id),
    INDEX idx_hosting_plan (plan_id),
    INDEX idx_hosting_status (status),
    CONSTRAINT fk_hosting_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_hosting_plan FOREIGN KEY (plan_id) REFERENCES hosting_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(190) NOT NULL,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domains_domain (domain),
    INDEX idx_domains_hosting (hosting_account_id),
    CONSTRAINT fk_domains_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS subdomains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    domain_id BIGINT UNSIGNED NULL,
    subdomain VARCHAR(63) NOT NULL,
    full_domain VARCHAR(190) NOT NULL,
    status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sub_full (full_domain),
    INDEX idx_sub_hosting (hosting_account_id),
    CONSTRAINT fk_sub_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_domain FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS customer_databases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(64) NOT NULL,
    charset VARCHAR(20) NOT NULL DEFAULT 'utf8mb4',
    status ENUM('active','deleted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cdb_name (name),
    INDEX idx_cdb_hosting (hosting_account_id),
    CONSTRAINT fk_cdb_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// For customer DB users — password is generated secure random, shown once, stored as encrypted value (or null if only reset allowed)
// Per correction: never hash if plaintext needed; use authenticated encryption key outside DB. For Phase 1, we store encrypted_password nullable.
$pdo->exec("
CREATE TABLE IF NOT EXISTS database_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_database_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(32) NOT NULL,
    encrypted_password TEXT NULL,
    host VARCHAR(60) NOT NULL DEFAULT 'localhost',
    privileges VARCHAR(100) NOT NULL DEFAULT 'ALL',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dbu_username (username),
    INDEX idx_dbu_db (customer_database_id),
    CONSTRAINT fk_dbu_cdb FOREIGN KEY (customer_database_id) REFERENCES customer_databases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS usage_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    type ENUM('storage','bandwidth','database','domain','subdomain') NOT NULL,
    used INT NOT NULL DEFAULT 0,
    limit_val INT NOT NULL DEFAULT 0,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usage_hosting (hosting_account_id),
    INDEX idx_usage_type (type),
    CONSTRAINT fk_usage_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Seed default plans
$pdo->exec("
INSERT IGNORE INTO hosting_plans (name, slug, description, storage_limit_mb, bandwidth_limit_mb, database_limit, domain_limit, subdomain_limit, is_default) VALUES
('FREE','free','Free tier — 500 MB storage', 500, 5120, 2, 1, 2, 1),
('BASIC','basic','Basic tier — 2 GB storage', 2048, 20480, 5, 2, 5, 0),
('PREMIUM','premium','Premium tier — 10 GB storage', 10240, 102400, 20, 10, 50, 0)
");
