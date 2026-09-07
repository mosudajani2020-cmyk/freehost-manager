<?php
$pdo->exec("
CREATE TABLE IF NOT EXISTS node_api_nonces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    node_id BIGINT UNSIGNED NOT NULL,
    nonce VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    UNIQUE KEY uq_node_nonce (node_id, nonce),
    INDEX idx_nonces_expires (expires_at),
    CONSTRAINT fk_nonces_node FOREIGN KEY (node_id) REFERENCES hosting_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS node_api_idempotency (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    node_id BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(100) NOT NULL,
    operation VARCHAR(50) NOT NULL,
    request_id VARCHAR(64) NULL,
    status_code SMALLINT NOT NULL,
    response_body JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_node_idempotency (node_id, idempotency_key),
    INDEX idx_idempotency_created (created_at),
    CONSTRAINT fk_idempotency_node FOREIGN KEY (node_id) REFERENCES hosting_nodes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS node_api_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_id VARCHAR(64) NOT NULL,
    node_id BIGINT UNSIGNED NULL,
    operation VARCHAR(50) NULL,
    idempotency_key VARCHAR(100) NULL,
    result VARCHAR(20) NOT NULL,
    status_code SMALLINT NOT NULL,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    actor VARCHAR(50) NOT NULL DEFAULT 'worker',
    failure_code VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_node (node_id),
    INDEX idx_audit_request (request_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Extend hosting_nodes for encrypted secret storage (worker signing)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM hosting_nodes LIKE 'api_secret_encrypted'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE hosting_nodes ADD COLUMN api_secret_encrypted VARCHAR(512) NULL DEFAULT NULL AFTER api_key_preview");
    }
} catch (Throwable $e) {}
try {
    $cols = $pdo->query("SHOW COLUMNS FROM hosting_nodes LIKE 'secret_rotated_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE hosting_nodes ADD COLUMN secret_rotated_at TIMESTAMP NULL DEFAULT NULL AFTER api_secret_encrypted");
    }
} catch (Throwable $e) {}
