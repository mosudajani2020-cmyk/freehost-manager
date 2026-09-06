# Provisioning Worker (P1)

The provisioning worker is a **restricted CLI process** that consumes the
`provisioning_jobs` MySQL queue and executes hosting operations away from the
web request path. It uses an atomic, short-lock claim so that at most one worker
ever runs a given job, and it never holds a database row lock while an
infrastructure operation runs.

```
Control Panel ──dispatch──▶ provisioning_jobs (MySQL queue)
                                  │
                                  ▼  (SELECT ... FOR UPDATE SKIP LOCKED)
                          Provisioning Worker ──process──▶ allowed provider ops
```

## Scope (P1)

- Queue claim foundation (`FOR UPDATE SKIP LOCKED`, lease, heartbeat, stale
  recovery, bounded retry backoff, max attempts).
- Worker process + secret-safe structured logging.
- Environment/config-driven provider selection (`ProviderFactory`),
  defaulting to `local_mock`.
- Worker / control-panel configuration boundary.

**Explicitly out of scope in P1:** real DNS / SSL / backups / payment /
filesystem provisioning, customer OS isolation, resource quotas, PHP-FPM pools,
VM/container management, SSH/agent execution. The worker only ever calls the
existing allow-listed provisioner operations; job payloads are **data**, never
commands.

## Requirements

- PHP 8.3+ CLI.
- MySQL **8.0.1+** or MariaDB **10.6+** is required for `FOR UPDATE SKIP LOCKED`.
  If unsupported, the worker's repository transparently falls back to plain
  `FOR UPDATE` (correct, but workers may briefly block each other on claim).

## Running the worker

```bash
# One-shot: claim and process a single job, then exit (tests / smoke checks)
php scripts/worker.php --once

# Long-running daemon
php scripts/worker.php

# Tune at the command line (persistent settings live in .env / .env.worker)
php scripts/worker.php --poll 2 --lease 120 --backoff-base 5 --backoff-max 300
```

`composer worker` runs the same entrypoint.

### Graceful shutdown

Send `SIGINT`/`SIGTERM` where `pcntl` is available, or create the stop file
(default `<STORAGE_PATH>/worker.stop`, override with `WORKER_STOP_FILE`). The
worker finishes the current job before exiting; anything still running is left
claimed and will be reclaimed after the lease expires.

## Configuration

| Env var | Default | Purpose |
| --- | --- | --- |
| `WORKER_POLL_INTERVAL` | `2` | Seconds between queue polls. |
| `WORKER_LEASE_SECONDS` | `120` | Lease length; stale claims are reclaimed after this. |
| `WORKER_BACKOFF_BASE_SECONDS` | `5` | Base backoff for retries. |
| `WORKER_BACKOFF_MAX_SECONDS` | `300` | Cap on backoff (backoff = base × 2^(attempt−1), capped). |
| `WORKER_LOG_FILE` | `<storage>/logs/worker.log` | JSON-lines log. |
| `WORKER_STOP_FILE` | `<storage>/worker.stop` | Stop-file path for graceful shutdown. |

Provider selection (web `.env`): `PROVISIONING_PROVIDER`, `DNS_PROVIDER`,
`SSL_PROVIDER`, `BACKUP_PROVIDER`, `PAYMENT_PROVIDER`, `USAGE_PROVIDER`,
`MONITORING_PROVIDER`, `DOMAIN_PROVIDER` — all default to `local_mock`.

### Worker / control-panel configuration boundary

- The web application loads only `.env` (immutable).
- The worker loads `.env` **then** an optional `.env.worker` as a **mutable
  overlay** so it can be tuned independently. `.env.worker` is never loaded by
  the web application.
- `.env.worker` is git-ignored; commit `.env.worker.example` instead.
- In production run the worker under its own non-privileged OS account with a
  database user scoped to the minimum tables/columns the queue needs, and keep
  real provider credentials out of the worker environment until P2 hardware.

## Queue mechanics

- **Claim:** `SELECT ... FOR UPDATE SKIP LOCKED` inside a short transaction;
  the row is marked `provisioning` with the worker id, `claimed_at`,
  `heartbeat_at`, and `attempts + 1`, then the transaction commits **before**
  any operation runs. The operation is never executed under a row lock.
- **Lease:** a claimed job whose `claimed_at` is older than the lease is
  `recoverStale()`-reclaimed by another worker (each stale reclaim is its own
  locked transaction).
- **Heartbeat:** `ProvisioningService::executeClaimedJob()` refreshes
  `heartbeat_at` before running; a long-running operation stays owned.
- **Success:** status → `active`, `completed_at` set, claim fields cleared.
- **Failure:** retryable → `retrying` with `next_attempt_at` backoff gate;
  attempts exhausted → `failed` with `completed_at` set.
- **Idempotency:** existing `idempotency_key` returns the existing job — no
  duplicate dispatch.

A web request that dispatches with `async=false` (default) still processes
synchronously for backwards compatibility; pass `async=true` to leave the job
queued for the worker.

## Logging

JSON lines in `storage/logs/worker.log`:

```json
{"ts":"...","level":"info","event":"job_finished","worker":"worker-…","job":"…","operation":"createSubdomain","status":"active","attempt":1,"duration":0.017}
```

`WorkerLogger` redacts sensitive context keys (`password`, `api_key`, `secret`,
`token`, …) and scrubs every string (including exception messages) for embedded
credentials — worker logs never contain secrets.

## Security model

- The worker executes **only** allow-listed interface operations; there is no
  generic command execution path (`exec`, `shell_exec`, `system`, `proc_open`,
  `popen`, … are absent from the codebase).
- Every DB statement is a prepared statement.
- Job payloads are untrusted data validated by the provisioning services.
- Errors persisted to `last_error` and audit logs are sanitized.
- Ownership checks (`worker_id`) gate `executeClaimedJob`, completion, failure,
  requeue, and heartbeat.

## Operations checklist

1. `php scripts/migrate.php` (adds migration 009 lease columns).
2. Start the worker as a service supervised by the platform's init system.
3. Smoke test: create a job, run `php scripts/worker.php --once`, confirm
   status `active` and a `job_finished` log line.
4. On shutdown, remove the stop file if you used it.