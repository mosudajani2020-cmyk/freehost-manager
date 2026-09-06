<?php

declare(strict_types=1);

use App\Helpers\Database;
use App\Repositories\ProvisioningJobRepository;
use App\Services\AuditService;
use App\Services\ProvisioningService;
use App\Services\Worker\ProvisioningWorker;

/**
 * Provisioning worker CLI entrypoint (P1).
 *
 *   php scripts/worker.php                 # run forever (daemon/manual)
 *   php scripts/worker.php --once          # single poll cycle (ci / smoke test)
 *   php scripts/worker.php --poll 5        # override poll interval (seconds)
 *   php scripts/worker.php --lease 120     # override lease seconds
 *
 * Uses a short-procedure SELECT ... FOR UPDATE SKIP LOCKED claim (see
 * docs/worker.md) and processes jobs away from the web request path. Never
 * executes job payloads as shell commands — it only calls allow-listed
 * provisioner operations via ProvisioningService::executeClaimedJob().
 *
 * PHP 8.3+ required.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    fwrite(STDERR, "ERROR: PHP 8.3+ required. Current: " . PHP_VERSION . PHP_EOL);
    exit(1);
}

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

// Base configuration — same .env as the web application (immutable).
if (file_exists($base . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($base);
    $dotenv->load();
} elseif (file_exists($base . '/.env.example')) {
    fwrite(STDERR, "WARNING: .env not found, using .env.example defaults\n");
    $dotenv = Dotenv\Dotenv::createImmutable($base, '.env.example');
    $dotenv->load();
}

// Worker-only, MUTABLE overlay — never loaded by the web application. Lets the
// worker run with its own overrides (e.g. a different poll interval) while the
// control panel keeps its own values. Optional.
$workerEnvFile = $base . '/.env.worker';
if (file_exists($workerEnvFile)) {
    $dotenvMutable = Dotenv\Dotenv::create($base, '.env.worker');
    $dotenvMutable->load();
    fwrite(STDOUT, "Loaded worker overlay: .env.worker\n");
}

define('BASE_PATH', $base);
define('APP_PATH', $base . '/app');
define('CONFIG_PATH', $base . '/config');
define('STORAGE_PATH', $base . '/storage');
define('PUBLIC_PATH', $base . '/public');

$config = require $base . '/config/worker.php';
$dbConfig = require $base . '/config/database.php';

// CLI argument parsing (kept intentionally small).
$args = $_SERVER['argv'] ?? [];
$opts = [];
for ($i = 1, $n = count($args); $i < $n; $i++) {
    $arg = $args[$i];
    switch ($arg) {
        case '--once':
            $opts['once'] = true;
            break;
        case '--poll':
            $opts['poll_interval'] = max(1, (int)($args[++$i] ?? 2));
            break;
        case '--lease':
            $opts['lease_seconds'] = max(10, (int)($args[++$i] ?? 120));
            break;
        case '--backoff-base':
            $opts['backoff_base_seconds'] = max(1, (int)($args[++$i] ?? 5));
            break;
        case '--backoff-max':
            $opts['backoff_max_seconds'] = max(1, (int)($args[++$i] ?? 300));
            break;
        case '--log':
            $opts['log_file'] = $args[++$i] ?? null;
            break;
        case '--stop-file':
            $opts['stop_file'] = $args[++$i] ?? null;
            break;
        case '--help':
        case '-h':
            fwrite(STDOUT, <<<HELP
Provisioning worker — processes provisioning_jobs from the queue.

Usage: php scripts/worker.php [options]

Options:
  --once            Run a single poll cycle and exit (tests / smoke checks).
  --poll <n>        Poll interval in seconds (default: {$config['poll_interval']}).
  --lease <n>       Lease duration in seconds before stale reclaim (default: {$config['lease_seconds']}).
  --backoff-base <n> Backoff base for retries in seconds (default: {$config['backoff_base_seconds']}).
  --backoff-max <n>  Maximum backoff for retries in seconds (default: {$config['backoff_max_seconds']}).
  --log <path>      Log file (default: storage/logs/worker.log).
  --stop-file <path> Stop-file path for graceful shutdown (default: storage/worker.stop).
  -h, --help        Show this help.

HELP);
            exit(0);
        default:
            fwrite(STDERR, "Unknown option: {$arg}\n");
            exit(2);
    }
}

try {
    $db = Database::getInstance($dbConfig);
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$jobs = new ProvisioningJobRepository($db);
$provisioning = ProvisioningService::createDefault($db, new AuditService($db));

$options = [
    'poll_interval' => $opts['poll_interval'] ?? $config['poll_interval'],
    'lease_seconds' => $opts['lease_seconds'] ?? $config['lease_seconds'],
    'backoff_base_seconds' => $opts['backoff_base_seconds'] ?? $config['backoff_base_seconds'],
    'backoff_max_seconds' => $opts['backoff_max_seconds'] ?? $config['backoff_max_seconds'],
    'log_file' => $opts['log_file'] ?? $config['log_file'],
    'stop_file' => $opts['stop_file'] ?? $config['stop_file'],
    'once' => (bool)($opts['once'] ?? false),
];

$worker = new ProvisioningWorker($db, $jobs, $provisioning, $options);
exit($worker->run());