<?php

declare(strict_types=1);

// CLI migration runner — PHP 8.3+
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

if (file_exists($base . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($base);
    $dotenv->load();
} elseif (file_exists($base . '/.env.example')) {
    fwrite(STDERR, "WARNING: .env not found, using .env.example defaults\n");
    $dotenv = Dotenv\Dotenv::createImmutable($base, '.env.example');
    $dotenv->load();
}

$config = require $base . '/config/database.php';

$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset'] ?? 'utf8mb4');

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $dbName = $config['database'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    echo "Connected to MySQL {$config['host']}:{$config['port']} as {$config['username']}, DB {$dbName}\n";
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$pdo->exec("
CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(190) NOT NULL UNIQUE,
    batch INT UNSIGNED NOT NULL,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$applied = $pdo->query("SELECT migration FROM migrations ORDER BY migration")->fetchAll(PDO::FETCH_COLUMN);
$appliedMap = array_flip($applied);

$migrationDir = $base . '/database/migrations';
$files = glob($migrationDir . '/*.php');
sort($files);

if ($files === false || $files === []) {
    echo "No migrations found.\n";
    exit(0);
}

$batch = (int) $pdo->query("SELECT COALESCE(MAX(batch),0) FROM migrations")->fetchColumn() + 1;
$run = 0;
$failed = 0;

foreach ($files as $file) {
    $name = basename($file);
    if (isset($appliedMap[$name])) {
        echo "[SKIP] {$name} already applied\n";
        continue;
    }
    echo "[RUN ] {$name} ... ";
    try {
        /** @var PDO $pdo */
        require $file;
        $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->execute([$name, $batch]);
        echo "OK\n";
        $run++;
    } catch (Throwable $e) {
        echo "FAILED: " . $e->getMessage() . PHP_EOL;
        fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
        $failed++;
        break;
    }
}

echo str_repeat('-', 50) . PHP_EOL;
if ($failed) {
    echo "Migrations completed with errors. Applied {$run}, failed {$failed}\n";
    exit(1);
}
if ($run === 0) {
    echo "Nothing to migrate — all up to date.\n";
} else {
    echo "Migrated {$run} file(s) in batch {$batch}\n";
}
exit(0);
