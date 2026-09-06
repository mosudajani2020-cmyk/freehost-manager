<?php

declare(strict_types=1);

namespace App\Services\Worker;

use App\Helpers\Database;
use App\Repositories\ProvisioningJobRepository;
use App\Services\ProvisioningService;

/**
 * ProvisioningWorker — restricted CLI worker for the provisioning job queue (P1).
 *
 * Responsibilities:
 *   - poll the provisioning_jobs DB queue at a configurable interval;
 *   - atomically claim available jobs (SELECT ... FOR UPDATE SKIP LOCKED in a
 *     short transaction — the row lock is never held during the operation);
 *   - recover jobs whose worker lease has gone stale (crashed worker);
 *   - process jobs through the allow-list ProvisioningService::executeClaimedJob
 *     (no generic command execution exists);
 *   - respect max attempts and apply a bounded backoff between retries;
 *   - shut down gracefully on SIGINT/SIGTERM (where pcntl is available) or via a
 *     stop file, releasing any in-flight claim.
 *
 * The worker never logs credentials: all log strings are sanitized by
 * WorkerLogger. The worker is a separate process from the web application and —
 * in production — runs under its own restricted OS account and environment.
 */
final class ProvisioningWorker
{
    public const DEFAULTS = [
        'poll_interval' => 2,
        'lease_seconds' => 120,
        'backoff_base_seconds' => 5,
        'backoff_max_seconds' => 300,
        'log_file' => null,
        'stop_file' => null,
        'once' => false,
    ];

    private readonly string $workerId;
    private readonly array $options;
    private bool $stopping = false;
    private ?WorkerLogger $logger = null;

    public function __construct(
        private readonly Database $db,
        private readonly ProvisioningJobRepository $jobs,
        private readonly ProvisioningService $provisioning,
        array $options = [],
        ?string $workerId = null,
    ) {
        $this->workerId = $workerId ?? 'worker-' . bin2hex(random_bytes(4));
        $merged = array_merge(self::DEFAULTS, $options);
        $this->options = [
            'poll_interval' => max(1, (int)($merged['poll_interval'] ?? env('WORKER_POLL_INTERVAL', 2))),
            'lease_seconds' => max(10, (int)($merged['lease_seconds'] ?? env('WORKER_LEASE_SECONDS', 120))),
            'backoff_base_seconds' => max(1, (int)($merged['backoff_base_seconds'] ?? env('WORKER_BACKOFF_BASE_SECONDS', 5))),
            'backoff_max_seconds' => max(1, (int)($merged['backoff_max_seconds'] ?? env('WORKER_BACKOFF_MAX_SECONDS', 300))),
            'log_file' => $merged['log_file'] ?? env('WORKER_LOG_FILE', null),
            'stop_file' => $merged['stop_file'] ?? env('WORKER_STOP_FILE', null),
            'once' => (bool)($merged['once'] ?? false),
        ];
    }

    /** Unique runtime identity for this worker process (never a secret). */
    public function workerId(): string
    {
        return $this->workerId;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function logger(): WorkerLogger
    {
        return $this->logger ??= new WorkerLogger($this->workerId, $this->options['log_file']);
    }

    /** Request a graceful stop at the next safe point. */
    public function requestStop(): void
    {
        $this->stopping = true;
    }

    /**
     * Run the worker loop until stopped. Returns exit code 0 on clean shutdown.
     *
     * With 'once' enabled the worker performs a single iteration (recover stale,
     * claim one job if any, process it if claimed) and then returns — used by
     * tests and manual single-shot runs.
     */
    public function run(): int
    {
        $this->registerSignalHandlers();
        $this->logger()->info('worker_started', [
            'worker' => $this->workerId,
            'poll_interval' => $this->options['poll_interval'],
            'lease_seconds' => $this->options['lease_seconds'],
            'once' => $this->options['once'],
        ]);

        while (!$this->shouldStop()) {
            try {
                $recovered = $this->jobs->recoverStale($this->workerId, $this->options['lease_seconds']);
                if ($recovered > 0) {
                    $this->logger()->warning('stale_job_reclaimed', ['count' => $recovered]);
                }

                $job = $this->jobs->claimNext($this->workerId, $this->options['lease_seconds']);
                if ($job === null) {
                    if ($this->options['once']) {
                        break;
                    }
                    $this->sleepFor($this->options['poll_interval']);
                    continue;
                }

                $this->logger()->info('job_claimed', [
                    'job' => $job->jobUuid,
                    'operation' => $job->operation,
                    'attempt' => $job->attempts,
                    'account' => $job->hostingAccountId,
                ]);

                $started = microtime(true);
                try {
                    $result = $this->provisioning->executeClaimedJob(
                        $job->id,
                        $this->workerId,
                        $this->options['backoff_base_seconds'],
                        $this->options['backoff_max_seconds']
                    );
                    $duration = round(microtime(true) - $started, 3);
                    $this->logger()->info('job_finished', [
                        'job' => $job->jobUuid,
                        'operation' => $job->operation,
                        'status' => $result->status,
                        'attempt' => $result->attempts,
                        'duration' => $duration,
                    ]);
                    if ($result->status === 'failed') {
                        $this->logger()->error('job_permanently_failed', ['job' => $job->jobUuid, 'operation' => $job->operation, 'attempt' => $result->attempts]);
                    } elseif ($result->status === 'retrying') {
                        $this->logger()->warning('job_retry_scheduled', ['job' => $job->jobUuid, 'operation' => $job->operation, 'attempt' => $result->attempts]);
                    }
                } catch (\Throwable $e) {
                    // Unexpected execution failure — release the claim so another
                    // worker can retry after backoff, and log the sanitized error.
                    $this->logger()->error('job_error', ['job' => $job->jobUuid, 'operation' => $job->operation, 'error' => $e->getMessage()]);
                    try {
                        $this->jobs->requeue($job->id, $this->workerId, WorkerLogger::sanitizeMessage($e->getMessage()));
                    } catch (\Throwable) {
                        // Requeue failed (e.g. DB lost) — the stale recovery pass
                        // of another worker will eventually reclaim and requeue it.
                    }
                }

                if ($this->options['once']) {
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger()->error('worker_error', ['error' => $e->getMessage()]);
                if ($this->options['once']) {
                    break;
                }
                $this->sleepFor($this->options['poll_interval']);
            }
        }

        $this->logger()->info('worker_shutdown', ['worker' => $this->workerId]);
        $this->logger()->close();
        return 0;
    }

    private function shouldStop(): bool
    {
        if ($this->stopping) {
            return true;
        }
        $stopFile = $this->options['stop_file'];
        if (is_string($stopFile) && $stopFile !== '' && is_file($stopFile)) {
            return true;
        }
        return false;
    }

    private function sleepFor(int $seconds): void
    {
        for ($i = 0; $i < $seconds; $i++) {
            if ($this->shouldStop()) {
                return;
            }
            usleep(1000000);
        }
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }
        $handler = function (): void {
            $this->requestStop();
        };
        @pcntl_signal(SIGINT, $handler);
        @pcntl_signal(SIGTERM, $handler);
        @pcntl_async_signals(true);
    }
}