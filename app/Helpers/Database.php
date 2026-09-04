<?php

declare(strict_types=1);

namespace App\Helpers;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * PDO wrapper — singleton, prepared statements everywhere.
 */
final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = $config['options'] ?? [];
        // Ensure required options
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $options[PDO::ATTR_EMULATE_PREPARES] = false;

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            // Enforce collation
            $this->pdo->exec("SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'");
        } catch (\PDOException $e) {
            error_log('[DB] Connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Check configuration.', 0, $e);
        }
    }

    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                $config = require CONFIG_PATH . '/database.php';
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /** For tests — reset singleton */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
