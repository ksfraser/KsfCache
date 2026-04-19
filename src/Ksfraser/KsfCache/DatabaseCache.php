<?php

/**
 * Database Cache Implementation
 *
 * SQL-based cache storage. Persistent, distributed, and reliable.
 * Works on any hosting platform that has database access.
 * Requires a 'ksf_cache' table in the database.
 *
 * @author Kevin Fraser
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache;

use PDO;
use PDOException;

/**
 * DatabaseCache
 *
 * Persistent cache using SQL database. Handles serialization of complex values.
 */
class DatabaseCache implements CacheInterface
{
    /**
     * PDO instance for database access
     *
     * @var PDO
     */
    private $pdo;
    /**
     * Cache table name
     *
     * @var string
     */
    private $tableName = 'ksf_cache';

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     * @param string $tableName Optional table name override
     */
    public function __construct(PDO $pdo, string $tableName = 'ksf_cache')
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
    }

    /**
     * Get a value from database cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT value, expires_at FROM {$this->tableName} WHERE cache_key = ? LIMIT 1"
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $default;
            }

            // Check expiration
            if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
                $this->delete($key);
                return $default;
            }

            return unserialize($row['value']);
        } catch (PDOException $e) {
            return $default;
        }
    }

    /**
     * Set a value in database cache
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null)
    {
        try {
            $serialized = serialize($value);
            $expiresAt = $ttl === null ? null : date('Y-m-d H:i:s', time() + $ttl);

            // Try insert first
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->tableName} (cache_key, value, expires_at, created_at, updated_at) 
                 VALUES (?, ?, ?, NOW(), NOW())"
            );

            if ($stmt->execute([$key, $serialized, $expiresAt])) {
                return true;
            }

            // If insert fails (key exists), try update
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->tableName} SET value = ?, expires_at = ?, updated_at = NOW() 
                 WHERE cache_key = ?"
            );

            return $stmt->execute([$serialized, $expiresAt, $key]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Delete a value from database cache
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE cache_key = ?");
            return $stmt->execute([$key]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check if key exists in database cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM {$this->tableName} WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1"
            );
            $stmt->execute([$key]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Clear all cached values from database
     *
     * @return bool
     */
    public function flush()
    {
        try {
            $this->pdo->exec("DELETE FROM {$this->tableName}");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get multiple values from database cache
     *
     * @param string[] $keys
     * @return array
     */
    public function getMultiple(array $keys)
    {
        try {
            if (empty($keys)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT cache_key, value FROM {$this->tableName} 
                 WHERE cache_key IN ($placeholders) AND (expires_at IS NULL OR expires_at > NOW())"
            );
            $stmt->execute($keys);

            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result[$row['cache_key']] = unserialize($row['value']);
            }

            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Set multiple values in database cache
     *
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null)
    {
        foreach ($values as $key => $value) {
            if (!$this->set((string)$key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get driver name
     *
     * @return string
     */
    public function getDriverName()
    {
        return 'database';
    }

    /**
     * Check if database cache backend is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->tableName} LIMIT 1");
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Create the cache table (run once during setup)
     *
     * @return bool
     * @throws PDOException
     */
    public function createTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                cache_key VARCHAR(255) PRIMARY KEY,
                value LONGBLOB NOT NULL,
                expires_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        return (bool)$this->pdo->exec($sql);
    }

    /**
     * Clean expired entries from database
     *
     * @return int Number of rows deleted
     */
    public function cleanExpired()
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
