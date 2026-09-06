<?php
/**
 * Migration 009 — provisioning job worker lease fields (P1).
 *
 * Adds the minimal state required for a restricted worker to safely claim and
 * process provisioning_jobs:
 *   - worker_id       : runtime identity of the worker that claimed the job
 *   - claimed_at      : when the job was claimed (lease start)
 *   - heartbeat_at    : last heartbeat from the claiming worker
 *   - next_attempt_at : backoff gate — job is claimable again only after this time
 *
 * Claiming uses SELECT ... FOR UPDATE SKIP LOCKED inside a short transaction
 * (MySQL 8.0.1+ / MariaDB 10.6+). No long-running operation is ever executed
 * while a row lock is held.
 */
$sql = "ALTER TABLE provisioning_jobs
    ADD COLUMN worker_id VARCHAR(64) NULL DEFAULT NULL AFTER last_error,
    ADD COLUMN claimed_at DATETIME NULL DEFAULT NULL AFTER worker_id,
    ADD COLUMN heartbeat_at DATETIME NULL DEFAULT NULL AFTER claimed_at,
    ADD COLUMN next_attempt_at DATETIME NULL DEFAULT NULL AFTER heartbeat_at";

try {
    $pdo->exec($sql);
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        // Columns already present — idempotent re-run.
    } else {
        throw $e;
    }
}