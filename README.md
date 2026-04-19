# KsfCache - Production-Grade Multi-Backend Caching

[![Latest Version](https://img.shields.io/packagist/v/ksfraser/ksf-cache.svg)](https://packagist.org/packages/ksfraser/ksf-cache)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/packagist/php-v/ksfraser/ksf-cache.svg)](https://php.net)

A robust, production-ready caching library with automatic driver detection, fallback support, and multi-backend coordination.

## Features

- **Multiple Cache Backends**: Memory, Database (SQL), and Redis
- **Automatic Detection**: Detects available backends and falls back gracefully
- **Multi-Backend Support**: Write to multiple backends simultaneously for redundancy
- **Configuration-Driven**: Enable/disable backends without code changes
- **Zero Dependencies** (except optional Redis support)
- **PSR-4 Compliant**: Standard PHP package structure
- **Comprehensive Tests**: 45+ unit tests covering all scenarios

## Installation

```bash
composer require ksfraser/ksf-cache
```

### Optional: Redis Support

For Redis caching, install the Predis library:

```bash
composer require predis/predis
```

## Quick Start

### Default Auto-Detection

```php
use Ksfraser\KsfCache\CacheFactory;

// Automatically selects the best available backend
$cache = CacheFactory::create();

$cache->set('user:123', $userData, 3600);
$userData = $cache->get('user:123');
```

### Memory Cache (Fast, In-Process)

```php
$cache = CacheFactory::createMemory();
$cache->set('key', 'value');
```

### Database Cache (Persistent)

```php
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');
$cache = CacheFactory::createDatabase($pdo);

// Create the cache table (run once)
// $cache->createTable();

$cache->set('key', 'value', 3600);
```

### Redis Cache (Fast, Distributed)

```php
$cache = CacheFactory::createRedis([
    'host' => 'localhost',
    'port' => 6379,
]);

$cache->set('key', 'value');
```

## Multi-Backend Strategy

Coordinate multiple caches for maximum reliability:

```php
use Ksfraser\KsfCache\CacheManager;
use Ksfraser\KsfCache\MemoryCache;
use Ksfraser\KsfCache\DatabaseCache;

$memory = new MemoryCache();
$database = new DatabaseCache($pdo);

$manager = new CacheManager(
    ['memory' => $memory, 'database' => $database],
    [],
    'memory'  // Primary backend for reads
);

// Writes to both backends
$manager->set('key', 'value', 3600);

// Reads from primary (memory), falls back to database
$value = $manager->get('key');

// Control backends
$manager->disableBackend('database');
$manager->enableBackend('database');
```

## Configuration Options

```php
$cache = CacheFactory::create([
    'preferred' => ['redis', 'database', 'memory'],  // Backend priority
    'pdo' => $pdo,                                    // Database connection
    'redis_options' => [
        'host' => 'localhost',
        'port' => 6379,
        'password' => 'secret',
        'database' => 0,
    ],
    'table_name' => 'cache_entries',                  // Database table
]);
```

## API Reference

### Basic Operations

```php
// Set a value (with optional TTL in seconds)
$cache->set('key', 'value', 3600);

// Get a value
$value = $cache->get('key', 'default');

// Check existence
if ($cache->has('key')) { }

// Delete a key
$cache->delete('key');

// Clear all
$cache->flush();
```

### Batch Operations

```php
// Get multiple values
$values = $cache->getMultiple(['key1', 'key2', 'key3']);

// Set multiple values
$cache->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2',
], 3600);
```

### Introspection

```php
// Get driver name
$driver = $cache->getDriverName();  // 'memory', 'database', 'redis', 'multi(...)'

// Check availability
if ($cache->isAvailable()) { }

// Get status (CacheManager only)
$status = $manager->getStatus();
```

## Database Schema

For SQL-based caching, KsfCache creates this table:

```sql
CREATE TABLE ksf_cache (
    cache_key VARCHAR(255) PRIMARY KEY,
    value LONGBLOB NOT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Performance Comparison

| Backend | Speed | Persistent | Distributed | Dependencies |
|---------|-------|-----------|-------------|--------------|
| Memory  | ⚡⚡⚡| No        | No          | None         |
| Database| ⚡⚡  | Yes       | Yes         | PDO          |
| Redis   | ⚡⚡⚡| Yes       | Yes         | Predis       |

## Testing

```bash
composer install
vendor/bin/phpunit tests/
```

## Use Cases

### Web Applications
- Session caching
- User data (profiles, preferences)
- API response caching
- Database query results

### E-Commerce
- Product catalogs
- Shopping cart data
- Inventory checks
- User favorites

### Real-Time Systems
- Temporary state
- User activity tracking
- Request throttling
- Rate limiting

## Error Handling

All cache operations fail gracefully:

```php
// If backend is unavailable, returns default or false
$value = $cache->get('key', $default);  // Never throws

// Set operations return bool
$success = $cache->set('key', 'value');
if (!$success) {
    // Log error, continue with fallback
}
```

## Contributing

Contributions welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Submit a pull request

## License

MIT License - See LICENSE.md for details

## Author

Kevin Fraser - [@ksfraser](https://github.com/ksfraser)

## Changelog

### 1.0.0
- Initial release
- Memory cache implementation
- Database cache implementation
- Redis cache implementation
- Multi-backend coordination
- Comprehensive test suite
