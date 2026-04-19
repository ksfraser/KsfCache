<?php

/**
 * Cache Manager
 *
 * Coordinates multiple cache backends simultaneously.
 * Can write to multiple backends for redundancy and synchronization.
 * Reads from fastest available backend.
 *
 * @author Kevin Fraser
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache;

/**
 * CacheManager
 *
 * Multi-backend cache coordinator.
 * Manages multiple cache instances with read/write policies.
 */
class CacheManager implements CacheInterface
{
    /**
     * Registered cache backends
     *
     * @var array<string, CacheInterface>
     */
    private $backends = [];

    /**
     * Backend enabled status
     *
     * @var array<string, bool>
     */
    private $enabled = [];

    /**
     * Primary backend for reads
     *
     * @var string
     */
    private $primaryBackend = 'memory';

    /**
     * Constructor
     *
     * @param array<string, CacheInterface> $backends
     * @param array<string, bool> $enabled
     * @param string $primaryBackend
     */
    public function __construct(
        array $backends = [],
        array $enabled = [],
        string $primaryBackend = 'memory'
    ) {
        foreach ($backends as $name => $cache) {
            $this->registerBackend($name, $cache);
        }

        $this->enabled = array_merge(
            array_fill_keys(array_keys($this->backends), true),
            $enabled
        );

        $this->primaryBackend = $primaryBackend;
    }

    /**
     * Register a cache backend
     *
     * @param string $name Backend name
     * @param CacheInterface $cache Cache instance
     * @return self
     */
    public function registerBackend(string $name, CacheInterface $cache)
    {
        $this->backends[$name] = $cache;
        $this->enabled[$name] = true;
        return $this;
    }

    /**
     * Enable a backend
     *
     * @param string $name Backend name
     * @return self
     */
    public function enableBackend(string $name)
    {
        if (isset($this->backends[$name])) {
            $this->enabled[$name] = true;
        }
        return $this;
    }

    /**
     * Disable a backend
     *
     * @param string $name Backend name
     * @return self
     */
    public function disableBackend(string $name)
    {
        if (isset($this->backends[$name])) {
            $this->enabled[$name] = false;
        }
        return $this;
    }

    /**
     * Set primary backend for reads
     *
     * @param string $name Backend name
     * @return self
     */
    public function setPrimaryBackend(string $name)
    {
        if (isset($this->backends[$name])) {
            $this->primaryBackend = $name;
        }
        return $this;
    }

    /**
     * Get a value from primary backend
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!isset($this->backends[$this->primaryBackend])) {
            return $default;
        }

        return $this->backends[$this->primaryBackend]->get($key, $default);
    }

    /**
     * Set value in all enabled backends
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @return self
     */
    public function set(string $key, $value, ?int $ttl = null)
    {
        foreach ($this->backends as $name => $backend) {
            if ($this->enabled[$name]) {
                $backend->set($key, $value, $ttl);
            }
        }

        return $this;
    }

    /**
     * Delete from all enabled backends
     *
     * @param string $key
     * @return self
     */
    public function delete(string $key)
    {
        foreach ($this->backends as $name => $backend) {
            if ($this->enabled[$name]) {
                $backend->delete($key);
            }
        }

        return $this;
    }

    /**
     * Check if key exists in primary backend
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key)
    {
        if (!isset($this->backends[$this->primaryBackend])) {
            return false;
        }

        return $this->backends[$this->primaryBackend]->has($key);
    }

    /**
     * Flush all enabled backends
     *
     * @return self
     */
    public function flush()
    {
        foreach ($this->backends as $name => $backend) {
            if ($this->enabled[$name]) {
                $backend->flush();
            }
        }

        return $this;
    }

    /**
     * Get multiple values from primary backend
     *
     * @param string[] $keys
     * @return array
     */
    public function getMultiple(array $keys)
    {
        if (!isset($this->backends[$this->primaryBackend])) {
            return [];
        }

        return $this->backends[$this->primaryBackend]->getMultiple($keys);
    }

    /**
     * Set multiple values in all enabled backends
     *
     * @param array $values
     * @param int|null $ttl
     * @return bool
     */
    public function setMultiple(array $values, ?int $ttl = null)
    {
        $success = true;

        foreach ($this->backends as $name => $backend) {
            if ($this->enabled[$name] && !$backend->setMultiple($values, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get combined driver name
     *
     * @return string
     */
    public function getDriverName()
    {
        $drivers = [];
        foreach ($this->backends as $name => $backend) {
            if ($this->enabled[$name]) {
                $drivers[] = $backend->getDriverName();
            }
        }
        return 'multi(' . implode(',', $drivers) . ')';
    }

    /**
     * All enabled backends are available
     *
     * @return bool
     */
    public function isAvailable()
    {
        foreach ($this->backends as $name => $backend) {
            if ($this->enabled[$name] && $backend->isAvailable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get list of registered backends
     *
     * @return string[]
     */
    public function listBackends()
    {
        return array_keys($this->backends);
    }

    /**
     * Get backend status
     *
     * @return array<string, array>
     */
    public function getStatus()
    {
        $status = [];

        foreach ($this->backends as $name => $backend) {
            $status[$name] = [
                'enabled' => $this->enabled[$name],
                'available' => $backend->isAvailable(),
                'driver' => $backend->getDriverName(),
                'primary' => $name === $this->primaryBackend,
            ];
        }

        return $status;
    }
}
