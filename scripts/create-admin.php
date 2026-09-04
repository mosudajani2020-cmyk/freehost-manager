<?php

declare(strict_types=1);

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
} else {
    fwrite(STDERR, "ERROR: .env not found. Copy .env.example to .env and configure DB.\n");
    exit(1);
}

$config = require $base . '/config/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset'] ?? 'utf8mb4'),
    $config['username'],
    $config['password'],
    $config['options'] ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "FreeHost Manager — Create Admin\n";
echo str_repeat('=', 40) . PHP_EOL;

// Interactive input — uses STDIN constant to support piped input and interactive
function prompt(string $label, bool $hidden = false): string
{
    echo $label . ': ';
    $line = fgets(STDIN);
    if ($line === false) {
        return '';
    }
    return trim($line);
}

$fullName = '';
while (trim($fullName) === '') {
    $fullName = prompt('Full name');
    if (mb_strlen($fullName) < 2) {
        echo "  -> Must be at least 2 chars\n";
        $fullName = '';
    }
}

$username = '';
while (true) {
    $username = strtolower(trim(prompt('Username (3-50, a-z0-9_.)')));
    if (!preg_match('/^[a-z0-9_\.]{3,50}$/', $username)) {
        echo "  -> Invalid format\n";
        continue;
    }
    $exists = $pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
    $exists->execute([$username]);
    if ($exists->fetchColumn()) {
        echo "  -> Username already exists\n";
        continue;
    }
    break;
}

$email = '';
while (true) {
    $email = strtolower(trim(prompt('Email')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "  -> Invalid email\n";
        continue;
    }
    $exists = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
    $exists->execute([$email]);
    if ($exists->fetchColumn()) {
        echo "  -> Email already exists\n";
        continue;
    }
    break;
}

$password = '';
$confirm = '';
while (true) {
    echo "Password (min 8, upper+lower+digit): ";
    $pwLine = fgets(STDIN);
    $password = $pwLine === false ? '' : trim($pwLine);
    echo "Confirm password: ";
    $cfLine = fgets(STDIN);
    $confirm = $cfLine === false ? '' : trim($cfLine);
    if ($password === '' || $confirm === '') {
        echo "  -> Required\n";
        continue;
    }
    if ($password !== $confirm) {
        echo "  -> Mismatch\n";
        continue;
    }
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        echo "  -> Too weak\n";
        continue;
    }
    break;
}

// Hash — Argon2id preferred
$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$options = $algo === PASSWORD_ARGON2ID ? ['memory_cost'=>65536,'time_cost'=>4,'threads'=>2] : ['cost'=>12];
$hash = password_hash($password, $algo, $options);
// Never log password
unset($password, $confirm);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, status, email_verified_at) VALUES (?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$fullName, $username, $email, $hash]);
    $userId = (int) $pdo->lastInsertId();

    $roleId = $pdo->query("SELECT id FROM roles WHERE name='admin' LIMIT 1")->fetchColumn();
    if (!$roleId) {
        throw new RuntimeException('Admin role not found — run migrations first');
    }
    $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $roleId]);
    // Also ensure audit
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, resource_type, resource_id, ip_address, result) VALUES (?, 'admin.create', 'user', ?, 'cli', 'success')")->execute([$userId, (string)$userId]);

    $pdo->commit();
    echo PHP_EOL . "✓ Admin created: ID {$userId}, username '{$username}', email '{$email}'\n";
    echo "  You can now login at " . ($_ENV['APP_URL'] ?? 'http://localhost') . "/login\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "FAILED: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
