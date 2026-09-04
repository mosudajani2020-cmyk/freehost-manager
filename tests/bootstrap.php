<?php

declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    fwrite(STDERR, "Tests require PHP 8.3+. Current: " . PHP_VERSION . PHP_EOL);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

// Load env for tests if present
$base = dirname(__DIR__);
if (file_exists($base . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($base);
    $dotenv->load();
}

// Ensure storage paths exist
foreach (['logs','cache','sessions','temp'] as $d) {
    $p = $base . '/storage/' . $d;
    if (!is_dir($p)) {
        mkdir($p, 0755, true);
    }
}
