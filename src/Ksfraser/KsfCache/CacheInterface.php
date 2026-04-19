<?php

/**
 * Cache Interface
 *
 * Defines the contract for all cache implementations.
 * Supports get, set, delete, flush operations with TTL support.
 *
 * @author Kevin Fraser
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache;

/**
 * CacheInterface
 *
 * Standard cache operations contract that all backends must implement.
 */
interface CacheInterface
{
    /**
     * Get a value from cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null);

    /**
     * Set a value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = no expiry)
     * @return bool Success status
     */
    public function set(string $key, $value, ?int $ttl = null);

    /**
     * Delete a value from cache
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key);

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key);

    /**
     * Clear all cached values
     *
     * @return bool Success status
     */
    public function flush();

    /**
     * Get multiple values from cache
     *
     * @param string[] $keys Cache keys
     * @return array Key => value pairs
     */
    public function getMultiple(array $keys);

    /**
     * Set multiple values in cache
     *
     * @param array $values Key => value pairs
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function setMultiple(array $values, ?int $ttl = null);

    /**
     * Get cache driver name
     *
     * @return string Driver name (memory, database, redis, etc.)
     */
    public function getDriverName();

    /**
     * Check if cache backend is available
     *
     * @return bool
     */
    public function isAvailable();
}
