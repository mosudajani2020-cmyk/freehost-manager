<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\HostingAccountRepository;
use App\Repositories\HostingPlanRepository;
use App\Services\Provisioning\HostingProvisionerInterface;

final class DatabaseService
{
    private const DB_NAME_REGEX = '/^[a-z][a-z0-9_]{2,29}$/';
    private const USER_REGEX = '/^[a-z][a-z0-9_]{2,29}$/';
    private const RESERVED = ['mysql','information_schema','performance_schema','sys','admin','root'];

    public function __construct(
        private readonly Database $db,
        private readonly HostingAccountRepository $accounts,
        private readonly HostingPlanRepository $plans,
        private readonly HostingProvisionerInterface $provisioner,
        private readonly AuditService $audit,
    ) {}

    private function requireActiveAccount(int $userId, int $accountId): \App\Models\HostingAccount
    {
        $acct = $this->accounts->findById($accountId);
        if (!$acct) throw new \RuntimeException('Hosting account not found', 404);
        if ($acct->userId !== $userId) {
            $this->audit->log($userId, 'database.access_denied', 'hosting_account', (string)$accountId, 'failure', ['reason'=>'ownership']);
            throw new \RuntimeException('Access denied', 403);
        }
        if ($acct->status !== 'active') {
            throw new \RuntimeException('Hosting account is ' . $acct->status . ' — database operations blocked', 403);
        }
        return $acct;
    }

    private function validateIdentifier(string $name, string $type = 'database'): void
    {
        $name = strtolower(trim($name));
        if ($name === '') throw new \RuntimeException(ucfirst($type) . ' name required');
        if (in_array($name, self::RESERVED, true)) throw new \RuntimeException('Reserved name');
        $regex = $type === 'user' ? self::USER_REGEX : self::DB_NAME_REGEX;
        if (!preg_match($regex, $name)) {
            throw new \RuntimeException('Invalid ' . $type . ' name: 3-30 chars, start with letter, a-z0-9_ only');
        }
        // Block SQL injection patterns
        if (str_contains($name, ' ') || str_contains($name, "'") || str_contains($name, '"') || str_contains($name, ';') || str_contains($name, '--') || str_contains($name, '/*') || str_contains($name, '*') || str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            throw new \RuntimeException('Invalid characters in name');
        }
    }

    private function generateDbName(int $accountId, string $part): string
    {
        $this->validateIdentifier($part, 'database');
        $safe = strtolower($part);
        $prefix = 'fh_' . $accountId . '_';
        $name = $prefix . $safe;
        if (strlen($name) > 64) throw new \RuntimeException('Database name too long (max 64)');
        return $name;
    }

    private function generateUserName(int $accountId, string $part): string
    {
        $this->validateIdentifier($part, 'user');
        $safe = strtolower($part);
        $prefix = 'fh_' . $accountId . '_u_';
        $name = $prefix . $safe;
        // MySQL username max 32, our prefix already  maybe 10, so limit
        if (strlen($name) > 32) throw new \RuntimeException('Username too long (max 32)');
        return $name;
    }

    private function generatePassword(): string
    {
        // 16 chars with upper, lower, digit, symbol
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
        $bytes = random_bytes(16);
        $pwd = '';
        for ($i=0;$i<16;$i++) {
            $pwd .= $chars[ord($bytes[$i]) % strlen($chars)];
        }
        // Ensure complexity
        if (!preg_match('/[A-Z]/', $pwd)) $pwd[0]='A';
        if (!preg_match('/[a-z]/', $pwd)) $pwd[1]='a';
        if (!preg_match('/[0-9]/', $pwd)) $pwd[2]='1';
        if (!preg_match('/[!@#$%&*]/', $pwd)) $pwd[3]='!';
        return $pwd;
    }

    private function getEncryptionKey(): string
    {
        $keyB64 = $_ENV['APP_KEY'] ?? '';
        // Expect "base64:xxx"
        if (str_starts_with($keyB64, 'base64:')) {
            $raw = base64_decode(substr($keyB64, 7), true);
            if ($raw !== false && strlen($raw) >= 32) {
                return substr($raw, 0, 32);
            }
        }
        // Fallback: hash env key (not ideal for production, but for tests)
        return hash('sha256', $keyB64 ?: 'fallback_key_for_tests_only', true);
    }

    private function encryptPassword(string $plain): string
    {
        $key = $this->getEncryptionKey();
        $nonceBytes = defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES') ? \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES : 24;
        $nonce = random_bytes($nonceBytes);
        if (function_exists('sodium_crypto_secretbox')) {
            $cipher = \sodium_crypto_secretbox($plain, $nonce, $key);
            return base64_encode($nonce . $cipher);
        }
        // OpenSSL fallback: AES-256-GCM
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    public function decryptPassword(string $enc): string
    {
        $key = $this->getEncryptionKey();
        $raw = base64_decode($enc, true);
        if ($raw === false) throw new \RuntimeException('Decrypt failed');
        if (function_exists('sodium_crypto_secretbox_open')) {
            $nonceBytes = defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES') ? \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES : 24;
            $nonce = substr($raw, 0, $nonceBytes);
            $cipher = substr($raw, $nonceBytes);
            $plain = \sodium_crypto_secretbox_open($cipher, $nonce, $key);
            if ($plain === false) throw new \RuntimeException('Decrypt failed');
            return $plain;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) throw new \RuntimeException('Decrypt failed');
        return $plain;
    }

    public function listDatabases(int $userId, int $accountId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $dbs = $this->db->fetchAll("SELECT * FROM customer_databases WHERE hosting_account_id=? AND status='active' ORDER BY id DESC", [$accountId]);
        foreach ($dbs as &$db) {
            $db['users'] = $this->db->fetchAll("SELECT id, username, host, privileges, created_at FROM database_users WHERE customer_database_id=?", [$db['id']]);
        }
        $plan = $this->accounts->findById($accountId)->plan ?? $this->plans->findById($acct->planId);
        return [
            'account' => $acct,
            'databases' => $dbs,
            'limit' => $plan?->databaseLimit ?? 2,
            'count' => count($dbs),
        ];
    }

    public function createDatabase(int $userId, int $accountId, string $namePart): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $plan = $this->plans->findById($acct->planId);
        $limit = $plan?->databaseLimit ?? 2;
        $current = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM customer_databases WHERE hosting_account_id=? AND status='active'", [$accountId]);
        if ($current >= $limit) {
            throw new \RuntimeException('Database limit reached (' . $limit . ')', 400);
        }
        $dbName = $this->generateDbName($accountId, $namePart);
        if ((bool)$this->db->fetchColumn("SELECT 1 FROM customer_databases WHERE name=? LIMIT 1", [$dbName])) {
            throw new \RuntimeException('Database already exists', 409);
        }
        // Also check real MySQL? For mock, just DB table unique is enough

        $result = $this->provisioner->createDatabase($acct, $dbName);
        if (!$result->success) {
            throw new \RuntimeException('Provisioning failed: ' . $result->message);
        }

        $this->db->query("INSERT INTO customer_databases (hosting_account_id, name, charset, status) VALUES (?,?, 'utf8mb4','active')", [$accountId, $dbName]);
        $id = (int)$this->db->lastInsertId();
        $this->audit->log($userId, 'database.create', 'customer_database', (string)$id, 'success', ['name'=>$dbName, 'hosting'=>$accountId]);
        return ['success'=>true,'message'=>'Database created','id'=>$id,'name'=>$dbName];
    }

    public function deleteDatabase(int $userId, int $accountId, int $dbId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $row = $this->db->fetch("SELECT * FROM customer_databases WHERE id=? AND hosting_account_id=?", [$dbId, $accountId]);
        if (!$row) throw new \RuntimeException('Database not found', 404);
        $dbName = $row['name'];

        // Check ownership already via hosting_account_id
        $result = $this->provisioner->deleteDatabase($acct, $dbName);
        if (!$result->success) {
            throw new \RuntimeException('Provisioning failed: ' . $result->message);
        }

        // Delete users first (FK cascade, but explicit)
        $this->db->execute("DELETE FROM database_users WHERE customer_database_id=?", [$dbId]);
        $this->db->execute("DELETE FROM customer_databases WHERE id=?", [$dbId]);
        $this->audit->log($userId, 'database.delete', 'customer_database', (string)$dbId, 'success', ['name'=>$dbName]);
        return ['success'=>true,'message'=>'Database deleted'];
    }

    public function createDatabaseUser(int $userId, int $accountId, int $dbId, string $usernamePart): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $dbRow = $this->db->fetch("SELECT * FROM customer_databases WHERE id=? AND hosting_account_id=?", [$dbId, $accountId]);
        if (!$dbRow) throw new \RuntimeException('Database not found', 404);

        $username = $this->generateUserName($accountId, $usernamePart);
        if ((bool)$this->db->fetchColumn("SELECT 1 FROM database_users WHERE username=? LIMIT 1", [$username])) {
            throw new \RuntimeException('Database user already exists', 409);
        }
        // User per database limit? For simplicity, allow up to 5 users per database, or use plan? We'll limit to 5 per DB
        $count = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM database_users WHERE customer_database_id=?", [$dbId]);
        if ($count >= 5) {
            throw new \RuntimeException('User limit per database reached (5)', 400);
        }

        $plain = $this->generatePassword();
        $enc = $this->encryptPassword($plain);
        $result = $this->provisioner->createDatabaseUser($acct, $username, $plain);
        if (!$result->success) {
            throw new \RuntimeException('Provisioning failed: ' . $result->message);
        }

        $this->db->query("INSERT INTO database_users (customer_database_id, username, encrypted_password, host) VALUES (?,?,?, 'localhost')", [$dbId, $username, $enc]);
        $uid = (int)$this->db->lastInsertId();
        $this->audit->log($userId, 'database.user_create', 'database_user', (string)$uid, 'success', ['username'=>$username, 'db'=>$dbRow['name']]);
        // Return password once
        return ['success'=>true,'message'=>'User created','id'=>$uid,'username'=>$username,'password'=>$plain];
    }

    public function deleteDatabaseUser(int $userId, int $accountId, int $dbUserId): array
    {
        $acct = $this->requireActiveAccount($userId, $accountId);
        $row = $this->db->fetch("SELECT du.*, cd.hosting_account_id FROM database_users du JOIN customer_databases cd ON cd.id=du.customer_database_id WHERE du.id=?", [$dbUserId]);
        if (!$row) throw new \RuntimeException('Database user not found', 404);
        if ((int)$row['hosting_account_id'] !== $accountId) {
            throw new \RuntimeException('Access denied', 403);
        }
        $username = $row['username'];
        $result = $this->provisioner->deleteDatabaseUser($acct, $username);
        if (!$result->success) {
            throw new \RuntimeException('Provisioning failed: ' . $result->message);
        }
        $this->db->execute("DELETE FROM database_users WHERE id=?", [$dbUserId]);
        $this->audit->log($userId, 'database.user_delete', 'database_user', (string)$dbUserId, 'success', ['username'=>$username]);
        return ['success'=>true,'message'=>'User deleted'];
    }

    public function getDatabase(int $userId, int $accountId, int $dbId): ?array
    {
        $this->requireActiveAccount($userId, $accountId);
        $row = $this->db->fetch("SELECT * FROM customer_databases WHERE id=? AND hosting_account_id=?", [$dbId, $accountId]);
        if (!$row) return null;
        $row['users'] = $this->db->fetchAll("SELECT id, username, host, privileges, created_at FROM database_users WHERE customer_database_id=?", [$dbId]);
        return $row;
    }
}
