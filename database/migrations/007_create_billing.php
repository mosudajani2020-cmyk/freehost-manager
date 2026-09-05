<?php
// Extend hosting_plans with additional configurable limits
try {
    $pdo->exec("ALTER TABLE hosting_plans ADD COLUMN backup_limit SMALLINT UNSIGNED NOT NULL DEFAULT 3 AFTER subdomain_limit");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE hosting_plans ADD COLUMN email_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER backup_limit");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE hosting_plans ADD COLUMN price_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER email_limit");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE hosting_plans ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'USD' AFTER price_cents");
} catch (Throwable $e) {}

// Subscriptions
$pdo->exec("
CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    hosting_account_id BIGINT UNSIGNED NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('trial','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trial',
    trial_ends_at TIMESTAMP NULL DEFAULT NULL,
    current_period_start TIMESTAMP NULL DEFAULT NULL,
    current_period_end TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sub_user (user_id),
    INDEX idx_sub_hosting (hosting_account_id),
    INDEX idx_sub_status (status),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_hosting FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES hosting_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Invoices (payment lifecycle)
$pdo->exec("
CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    amount_cents INT UNSIGNED NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    status ENUM('pending','successful','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    due_date TIMESTAMP NULL DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    hosted_url VARCHAR(500) NULL,
    provider_ref VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inv_user (user_id),
    INDEX idx_inv_sub (subscription_id),
    INDEX idx_inv_status (status),
    CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Update FREE/BASIC/PREMIUM with backup/email/price
$pdo->exec("UPDATE hosting_plans SET backup_limit=3, email_limit=1, price_cents=0 WHERE slug='free'");
$pdo->exec("UPDATE hosting_plans SET backup_limit=10, email_limit=5, price_cents=999 WHERE slug='basic'");
$pdo->exec("UPDATE hosting_plans SET backup_limit=50, email_limit=20, price_cents=2999 WHERE slug='premium'");
