<?php

/**
 * Redis Cache Implementation
 *
 * High-performance distributed cache using Redis.
 * Optional backend - system works without it.
 * Requires predis/predis package.
 *
 * @author Kevin Fraser
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache;

/**
 * RedisCache
 *
 * Redis-based cache implementation. Requires Predis\Client.
 * Gracefully degrades if Redis is unavailable or not installed.
 */
class RedisCache implements CacheInterface
{
    /**
     * Redis client instance
     *
     * @var \Predis\Client|null
     */
    private $client = null;
    /**
     * Backend availability flag
     *
     * @var bool
     */
    private $available = false;

    /**
     * Constructor
     *
     * @param array $options Predis connection options
     */
    public function __construct(array $options = [])
    {
        try {
            if (!class_exists('Predis\Client')) {
                return;
            }

            // Add connection timeout to prevent hanging
            if (!isset($options['read_write_timeout'])) {
                $options['read_write_timeout'] = 1;  // 1 second timeout
            }

            $this->client = new \Predis\Client($options);

            // Test connection with timeout handling
            try {
                $this->client->ping();
                $this->available = true;
            } catch (\Exception $pingError) {
                // Connection test failed, mark as unavailable but don't throw
                $this->available = false;
            }
        } catch (\Exception $e) {
            $this->available = false;
        }
    }

    /**
     * Get a value from Redis
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!$this->available) {
            return $default;
        }

        try {
            $value = $this->client->get($key);
            return $value === null ? $default : unserialize($value);
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Set a value in Redis
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null)
    {
        if (!$this->available) {
            return false;
        }

        try {
            $serialized = serialize($value);

            if ($ttl === null) {
                $this->client->set($key, $serialized);
            } else {
                $this->client->setex($key, $ttl, $serialized);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a value from Redis
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key)
    {
        if (!$this->available) {
            return false;
        }

        try {
            $this->client->del($key);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if key exists in Redis
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        if (!$this->available) {
            return false;
        }

        try {
            return $this->client->exists($key) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear all cached values from Redis
     *
     * @return bool
     */
    public function flush()
    {
        if (!$this->available) {
            return false;
        }

        try {
            $this->client->flushdb();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get multiple values from Redis
     *
     * @param string[] $keys
     * @return array
     */
    public function getMultiple(array $keys)
    {
        if (!$this->available || empty($keys)) {
            return [];
        }

        try {
            $values = $this->client->mget($keys);
            $result = [];

            foreach ($keys as $index => $key) {
                if ($values[$index] !== null) {
                    $result[$key] = unserialize($values[$index]);
                }
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Set multiple values in Redis
     *
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null)
    {
        if (!$this->available) {
            return false;
        }

        try {
            foreach ($values as $key => $value) {
                $this->set((string)$key, $value, $ttl);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get driver name
     *
     * @return string
     */
    public function getDriverName()
    {
        return 'redis';
    }

    /**
     * Check if Redis backend is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->available;
    }

    /**
     * Get Redis client for advanced operations
     *
     * @return \Predis\Client|null
     */
    public function getClient(): ?\Predis\Client
    {
        return $this->client;
    }
}
