<?php

namespace Core;

use PDO;
use PDOException;
use Exception;

/**
 * Database class - PDO wrapper with connection pooling, transactions support
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    private bool $transactionActive = false;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Get singleton instance
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     *
     * @throws Exception
     */
    private function connect(): void
    {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT . ";charset=utf8mb4";
        
        try {
            $this->connection = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get PDO connection
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Begin transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if (!$this->transactionActive) {
            $this->transactionActive = $this->connection->beginTransaction();
        }
        return $this->transactionActive;
    }

    /**
     * Commit transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->transactionActive) {
            $result = $this->connection->commit();
            $this->transactionActive = false;
            return $result;
        }
        return false;
    }

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        if ($this->transactionActive) {
            $result = $this->connection->rollback();
            $this->transactionActive = false;
            return $result;
        }
        return false;
    }

    /**
     * Execute a query and return results
     *
     * @param string $query
     * @param array $params
     * @return array
     */
    public function select(string $query, array $params = []): array
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return single result
     *
     * @param string $query
     * @param array $params
     * @return array|null
     */
    public function selectOne(string $query, array $params = []): ?array
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Insert a record and return the ID
     *
     * @param string $table
     * @param array $data
     * @return int
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute($data);
        return $this->connection->lastInsertId();
    }

    /**
     * Update records
     *
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $params
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $set);
        
        $query = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        // Merge data values with where clause parameters
        $allParams = array_merge($data, $params);
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute($allParams);
        return $stmt->rowCount();
    }

    /**
     * Delete records
     *
     * @param string $table
     * @param string $where
     * @param array $params
     * @return int Number of affected rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $query = "DELETE FROM {$table} WHERE {$where}";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Check if a table exists
     *
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        $query = "SELECT COUNT(*) as count FROM information_schema.tables 
                  WHERE table_schema = :database AND table_name = :table";
        $result = $this->selectOne($query, [
            'database' => DB_NAME,
            'table' => $tableName
        ]);
        
        return isset($result['count']) && $result['count'] > 0;
    }

    /**
     * Escape identifier (table or column name)
     *
     * @param string $identifier
     * @return string
     */
    public function escapeIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Get table columns
     *
     * @param string $tableName
     * @return array
     */
    public function getTableColumns(string $tableName): array
    {
        $query = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table
                  ORDER BY ORDINAL_POSITION";
        
        return $this->select($query, [
            'database' => DB_NAME,
            'table' => $tableName
        ]);
    }

    /**
     * Check if connection is alive
     *
     * @return bool
     */
    public function ping(): bool
    {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}