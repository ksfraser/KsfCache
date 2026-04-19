<?php

/**
 * Cache Factory
 *
 * Auto-detects and instantiates available cache backends.
 * Supports priority-based selection and fallback chains.
 *
 * @author Kevin Fraser
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache;

use PDO;

/**
 * CacheFactory
 *
 * Factory for creating cache instances with automatic backend detection.
 */
class CacheFactory
{
    /**
     * Create cache instance with auto-detection
     *
     * Priority order:
     * 1. Redis (if available and preferred)
     * 2. Database (if PDO provided and preferred)
     * 3. Memory (always available fallback)
     *
     * @param array $options Configuration options
     *   - 'preferred' => ['redis', 'database', 'memory'] (order of preference)
     *   - 'pdo' => PDO instance for database backend
     *   - 'redis_options' => array of options for Redis connection
     *   - 'table_name' => custom table name for database cache
     * @return CacheInterface
     */
    public static function create(array $options = [])
    {
        $preferred = $options['preferred'] ?? ['redis', 'database', 'memory'];
        $pdo = $options['pdo'] ?? null;
        $redisOptions = $options['redis_options'] ?? [];
        $tableName = $options['table_name'] ?? 'ksf_cache';

        foreach ($preferred as $preferred_index => $driver) {
            $cache = null;
            
            try {
                if ($driver === 'redis') {
                    $cache = self::createRedisCache($redisOptions);
                } elseif ($driver === 'database') {
                    $cache = self::createDatabaseCache($pdo, $tableName);
                } elseif ($driver === 'memory') {
                    $cache = new MemoryCache();
                }

                if ($cache !== null && $cache->isAvailable()) {
                    return $cache;
                }
            } catch (\InvalidArgumentException $e) {
                // If database is the last (only remaining) backend, re-throw
                // Otherwise skip and try next backend
                if ($driver === 'database' && $preferred_index === count($preferred) - 1) {
                    throw $e;
                }
                continue;
            } catch (\Exception $e) {
                // Other exceptions: backend not available, try next
                continue;
            }
        }

        // Fallback to memory cache
        return new MemoryCache();
    }

    /**
     * Create Redis cache instance
     *
     * @param array $options Predis connection options
     * @return RedisCache
     */
    private static function createRedisCache(array $options = [])
    {
        $defaults = [
            'host' => getenv('REDIS_HOST') ?: 'localhost',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => getenv('REDIS_DB') ?: 0,
        ];

        $options = array_filter(array_merge($defaults, $options));

        return new RedisCache($options);
    }

    /**
     * Create database cache instance
     *
     * @param PDO|null $pdo
     * @param string $tableName
     * @return DatabaseCache
     */
    private static function createDatabaseCache($pdo = null, $tableName = 'ksf_cache')
    {
        if ($pdo === null) {
            throw new \InvalidArgumentException('PDO instance required for database cache');
        }

        return new DatabaseCache($pdo, $tableName);
    }

    /**
     * Create memory cache instance
     *
     * @return MemoryCache
     */
    public static function createMemory()
    {
        return new MemoryCache();
    }

    /**
     * Create Redis cache instance
     *
     * @param array $options
     * @return RedisCache
     */
    public static function createRedis(array $options = []): RedisCache
    {
        return self::createRedisCache($options);
    }

    /**
     * Create database cache instance
     *
     * @param PDO $pdo
     * @param string $tableName
     * @return DatabaseCache
     */
    public static function createDatabase(PDO $pdo, string $tableName = 'ksf_cache'): DatabaseCache
    {
        return self::createDatabaseCache($pdo, $tableName);
    }
}
