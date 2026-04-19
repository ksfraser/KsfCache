<?php

/**
 * Memory Cache Implementation
 *
 * In-process cache stored in memory. Fast but ephemeral - lost when PHP process ends.
 * Suitable for single-request caching or distributed systems with short TTLs.
 *
 * @author Kevin Fraser
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache;

/**
 * MemoryCache
 *
 * In-memory cache implementation. Data is lost when the PHP process terminates.
 */
class MemoryCache implements CacheInterface
{
    /**
     * Cache store: ['key' => ['value' => mixed, 'expires' => int|null]]
     *
     * @var array
     */
    private $store = [];

    /**
     * Get a value from memory cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }

        $entry = $this->store[$key];
        
        // Check expiration
        if ($entry['expires'] !== null && $entry['expires'] < time()) {
            unset($this->store[$key]);
            return $default;
        }

        return $entry['value'];
    }

    /**
     * Set a value in memory cache
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null)
    {
        $this->store[$key] = [
            'value' => $value,
            'expires' => $ttl === null ? null : time() + $ttl,
        ];

        return true;
    }

    /**
     * Delete a value from memory cache
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key)
    {
        if (isset($this->store[$key])) {
            unset($this->store[$key]);
            return true;
        }

        return false;
    }

    /**
     * Check if key exists in memory cache
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        $entry = $this->store[$key];
        
        // Check expiration
        if ($entry['expires'] !== null && $entry['expires'] < time()) {
            unset($this->store[$key]);
            return false;
        }

        return true;
    }

    /**
     * Clear all cached values from memory
     *
     * @return bool
     */
    public function flush()
    {
        $this->store = [];
        return true;
    }

    /**
     * Get multiple values from memory cache
     *
     * @param string[] $keys
     * @return array
     */
    public function getMultiple(array $keys)
    {
        $result = [];
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->store[$key]['value'];
            }
        }
        return $result;
    }

    /**
     * Set multiple values in memory cache
     *
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->set((string)$key, $value, $ttl);
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
        return 'memory';
    }

    /**
     * Memory cache is always available in PHP
     *
     * @return bool
     */
    public function isAvailable()
    {
        return true;
    }

    /**
     * Get cache statistics for debugging
     *
     * @return array
     */
    public function getStats()
    {
        $count = 0;
        $expired = 0;

        foreach ($this->store as $entry) {
            if ($entry['expires'] !== null && $entry['expires'] < time()) {
                $expired++;
            } else {
                $count++;
            }
        }

        return [
            'entries' => $count,
            'expired' => $expired,
            'total' => count($this->store),
        ];
    }
}
