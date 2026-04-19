<?php

/**
 * Memory Cache Test Suite
 *
 * @covers \Ksfraser\KsfCache\MemoryCache
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\KsfCache\MemoryCache;

class MemoryCacheTest extends TestCase
{
    /**
     * @var MemoryCache
     */
    private $cache;

    protected function setUp(): void
    {
        $this->cache = new MemoryCache();
    }

    public function testSet(): void
    {
        $result = $this->cache->set('key1', 'value1');
        $this->assertTrue($result);
    }

    public function testGetWithoutExpiry(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertSame('value1', $this->cache->get('key1'));
    }

    public function testGetNonExistent(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
    }

    public function testGetNonExistentWithDefault(): void
    {
        $this->assertSame('default', $this->cache->get('nonexistent', 'default'));
    }

    public function testHas(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function testDelete(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->delete('key1'));
        $this->assertFalse($this->cache->has('key1'));
    }

    public function testDeleteNonExistent(): void
    {
        $this->assertFalse($this->cache->delete('nonexistent'));
    }

    public function testSetWithTTL(): void
    {
        $this->cache->set('key1', 'value1', 3600);
        $this->assertSame('value1', $this->cache->get('key1'));
    }

    public function testExpiry(): void
    {
        $this->cache->set('key1', 'value1', -1);
        $this->assertNull($this->cache->get('key1'));
    }

    public function testFlush(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $result = $this->cache->flush();
        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);
        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $result);
    }

    public function testSetMultiple(): void
    {
        $result = $this->cache->setMultiple(['key1' => 'value1', 'key2' => 'value2']);
        $this->assertTrue($result);
        $this->assertSame('value1', $this->cache->get('key1'));
        $this->assertSame('value2', $this->cache->get('key2'));
    }

    public function testGetDriverName(): void
    {
        $this->assertSame('memory', $this->cache->getDriverName());
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->cache->isAvailable());
    }

    public function testComplexValueStorage(): void
    {
        $value = ['key' => 'value', 'nested' => ['item' => 123]];
        $this->cache->set('complex', $value);
        $this->assertSame($value, $this->cache->get('complex'));
    }

    public function testStats(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2', -1);
        $stats = $this->cache->getStats();
        $this->assertSame(1, $stats['entries']);
        $this->assertSame(1, $stats['expired']);
        $this->assertSame(2, $stats['total']);
    }

    public function testMultipleExpirations(): void
    {
        $this->cache->set('key1', 'value1', -1);
        $this->cache->set('key2', 'value2', 3600);
        $this->cache->set('key3', 'value3', -1);

        $this->assertNull($this->cache->get('key1'));
        $this->assertSame('value2', $this->cache->get('key2'));
        $this->assertNull($this->cache->get('key3'));
    }

    public function testSetMultipleWithTTL(): void
    {
        $this->cache->setMultiple(['key1' => 'value1', 'key2' => 'value2'], 3600);
        $this->assertSame('value1', $this->cache->get('key1'));
        $this->assertSame('value2', $this->cache->get('key2'));
    }

    public function testEmptyGetMultiple(): void
    {
        $result = $this->cache->getMultiple([]);
        $this->assertSame([], $result);
    }
}
