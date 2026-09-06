<?php

declare(strict_types=1);

/**
 * Worker configuration (P1).
 *
 * Values come from environment variables with safe defaults. The worker loads
 * the web .env first and then MAY load a worker-only .env.worker file (mutable
 * overlay) so the restricted worker can be tuned independently of the control
 * panel — see docs/worker.md.
 */

return [
    'poll_interval' => max(1, (int)($_ENV['WORKER_POLL_INTERVAL'] ?? 2)),
    'lease_seconds' => max(10, (int)($_ENV['WORKER_LEASE_SECONDS'] ?? 120)),
    'backoff_base_seconds' => max(1, (int)($_ENV['WORKER_BACKOFF_BASE_SECONDS'] ?? 5)),
    'backoff_max_seconds' => max(1, (int)($_ENV['WORKER_BACKOFF_MAX_SECONDS'] ?? 300)),
    'log_file' => $_ENV['WORKER_LOG_FILE'] ?? null, // default: storage/logs/worker.log
    'stop_file' => $_ENV['WORKER_STOP_FILE'] ?? null, // default: <STORAGE_PATH>/worker.stop
];