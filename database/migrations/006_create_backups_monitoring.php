<?php
$pdo->exec("
CREATE TABLE IF NOT EXISTS backups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hosting_account_id BIGINT UNSIGNED NOT NULL,
    type ENUM('full','incremental') NOT NULL DEFAULT 'full',
    status ENUM('pending','running','completed','failed','expired') NOT NULL DEFAULT 'pending',
    size_bytes BIGINT UNSIGNED NULL,
    file_path VARCHAR(500) NULL,
    retention_days INT UNSIGNED NOT NULL DEFAULT 7,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    requested_by BIGINT UNSIGNED NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_backups_hosting (hosting_account_id),
    INDEX idx_backups_status (status),
    INDEX idx_backups_expires (expires_at),
    CONSTRAINT fk_backups_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_backups_user FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Usage records already exists, but ensure it has proper indexes for daily/monthly aggregation
// Add type for bandwidth if not exists? Already has storage, bandwidth, etc.
// Ensure usage_records can store daily snapshots
try {
    $pdo->exec("ALTER TABLE usage_records ADD COLUMN recorded_date DATE NULL AFTER recorded_at");
} catch (Throwable $e) {}
