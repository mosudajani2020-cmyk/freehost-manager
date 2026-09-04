<?php
$pdo->exec("
CREATE TABLE IF NOT EXISTS hosting_nodes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    hostname VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NULL,
    region VARCHAR(50) NULL,
    status ENUM('active','maintenance','offline') NOT NULL DEFAULT 'active',
    max_accounts INT UNSIGNED NOT NULL DEFAULT 100,
    current_accounts INT UNSIGNED NOT NULL DEFAULT 0,
    api_url VARCHAR(255) NULL,
    api_key_hash VARCHAR(255) NULL,
    api_key_preview VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_nodes_name (name),
    UNIQUE KEY uq_nodes_hostname (hostname),
    INDEX idx_nodes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS provisioning_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_uuid CHAR(36) NOT NULL,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    node_id BIGINT UNSIGNED NULL,
    operation VARCHAR(50) NOT NULL,
    payload JSON NULL,
    status ENUM('pending','queued','provisioning','active','failed','retrying','suspended','terminated') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    idempotency_key VARCHAR(100) NOT NULL,
    requested_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_job_uuid (job_uuid),
    UNIQUE KEY uq_idempotency (idempotency_key),
    INDEX idx_jobs_account (hosting_account_id),
    INDEX idx_jobs_status (status),
    INDEX idx_jobs_node (node_id),
    CONSTRAINT fk_jobs_account FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_jobs_node FOREIGN KEY (node_id) REFERENCES hosting_nodes(id) ON DELETE SET NULL,
    CONSTRAINT fk_jobs_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Seed default local node (for LocalMock)
$exists = $pdo->query("SELECT COUNT(*) FROM hosting_nodes")->fetchColumn();
if ((int)$exists === 0) {
    $pdo->exec("INSERT INTO hosting_nodes (name, hostname, ip_address, region, status, max_accounts, api_url) VALUES ('local-mock-1', 'local-mock.freehost.example', '127.0.0.1', 'local', 'active', 1000, 'http://localhost/mock-provisioning')");
}

// Add provisioning settings
$pdo->exec("INSERT IGNORE INTO system_settings (`key`, `value`, type) VALUES ('provisioning_mode', 'local_mock', 'string'), ('provisioning_api_key_preview', '', 'string'), ('provisioning_signing_algo', 'hmac-sha256', 'string')");
