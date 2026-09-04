<?php
$pdo->exec("
CREATE TABLE IF NOT EXISTS dns_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    type ENUM('A','AAAA','CNAME','TXT','MX','NS') NOT NULL DEFAULT 'A',
    value VARCHAR(500) NOT NULL,
    ttl INT UNSIGNED NOT NULL DEFAULT 3600,
    priority SMALLINT UNSIGNED NULL,
    status ENUM('pending','active','failed','suspended','removed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dns_host_type (hostname, type),
    INDEX idx_dns_hosting (hosting_account_id),
    INDEX idx_dns_status (status),
    CONSTRAINT fk_dns_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS ssl_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    status ENUM('pending','issuing','active','renewing','expired','failed','revoked') NOT NULL DEFAULT 'pending',
    provider VARCHAR(50) NOT NULL DEFAULT 'local_mock',
    expires_at TIMESTAMP NULL DEFAULT NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 1,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ssl_host (hostname),
    INDEX idx_ssl_hosting (hosting_account_id),
    INDEX idx_ssl_status (status),
    CONSTRAINT fk_ssl_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Update subdomains to track DNS/SSL status (add columns if not exists)
try {
    $pdo->exec("ALTER TABLE subdomains ADD COLUMN dns_status ENUM('pending','active','failed','suspended','removed') NOT NULL DEFAULT 'pending' AFTER status");
} catch (Throwable $e) {
    // Column may already exist
}
try {
    $pdo->exec("ALTER TABLE subdomains ADD COLUMN ssl_status ENUM('pending','issuing','active','renewing','expired','failed','revoked') NOT NULL DEFAULT 'pending' AFTER dns_status");
} catch (Throwable $e) {}

try {
    $pdo->exec("ALTER TABLE subdomains ADD COLUMN ssl_expires_at TIMESTAMP NULL DEFAULT NULL AFTER ssl_status");
} catch (Throwable $e) {}
