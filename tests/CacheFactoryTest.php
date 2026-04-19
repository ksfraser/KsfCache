<?php

/**
 * Cache Factory Test Suite
 *
 * @covers \Ksfraser\KsfCache\CacheFactory
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\KsfCache\CacheFactory;
use Ksfraser\KsfCache\MemoryCache;
use Ksfraser\KsfCache\DatabaseCache;
use Ksfraser\KsfCache\RedisCache;
use PDO;

class CacheFactoryTest extends TestCase
{
    public function testCreateDefaultFallsBackToMemory(): void
    {
        $cache = CacheFactory::create([]);
        $this->assertInstanceOf(MemoryCache::class, $cache);
        $this->assertTrue($cache->isAvailable());
    }

    public function testCreateWithPreferredMemory(): void
    {
        $cache = CacheFactory::create(['preferred' => ['memory']]);
        $this->assertInstanceOf(MemoryCache::class, $cache);
        $this->assertSame('memory', $cache->getDriverName());
    }

    public function testCreateMemory(): void
    {
        $cache = CacheFactory::createMemory();
        $this->assertInstanceOf(MemoryCache::class, $cache);
    }

    public function testCreateRedisReturnsRedisCache(): void
    {
        $cache = CacheFactory::createRedis();
        $this->assertInstanceOf(RedisCache::class, $cache);
    }

    public function testCreateRedisWithOptions(): void
    {
        $options = ['host' => 'redis-server', 'port' => 6380];
        $cache = CacheFactory::createRedis($options);
        $this->assertInstanceOf(RedisCache::class, $cache);
    }

    public function testCreateDatabaseRequiresPDO(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CacheFactory::create(['preferred' => ['database']]);
    }

    public function testCreateDatabaseWithPDO(): void
    {
        $pdo = $this->createMockPDO();
        $cache = CacheFactory::createDatabase($pdo);
        $this->assertInstanceOf(DatabaseCache::class, $cache);
    }

    public function testCreateWithMultiplePreferred(): void
    {
        $cache = CacheFactory::create([
            'preferred' => ['redis', 'memory'],
        ]);
        $this->assertInstanceOf(MemoryCache::class, $cache);
    }

    public function testCreatePriority(): void
    {
        $pdo = $this->createMockPDO();
        $cache = CacheFactory::create([
            'preferred' => ['database', 'memory'],
            'pdo' => $pdo,
        ]);
        $this->assertInstanceOf(DatabaseCache::class, $cache);
    }

    public function testCreateWithCustomTableName(): void
    {
        $pdo = $this->createMockPDO();
        $cache = CacheFactory::create([
            'preferred' => ['database'],
            'pdo' => $pdo,
            'table_name' => 'custom_cache',
        ]);
        $this->assertInstanceOf(DatabaseCache::class, $cache);
    }

    private function createMockPDO(): PDO
    {
        // Create a mock PDO for testing
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('execute')->willReturn(true);
        $statement->method('rowCount')->willReturn(1);
        $pdo->method('prepare')->willReturn($statement);
        return $pdo;
    }
}
