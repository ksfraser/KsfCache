<?php

/**
 * Cache Manager Test Suite
 *
 * @covers \Ksfraser\KsfCache\CacheManager
 */

declare(strict_types=1);

namespace Ksfraser\KsfCache\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\KsfCache\CacheManager;
use Ksfraser\KsfCache\MemoryCache;
use Ksfraser\KsfCache\CacheInterface;

class CacheManagerTest extends TestCase
{
    /**
     * @var CacheManager
     */
    private $manager;
    /**
     * @var MemoryCache
     */
    private $memory1;
    /**
     * @var MemoryCache
     */
    private $memory2;

    protected function setUp(): void
    {
        $this->memory1 = new MemoryCache();
        $this->memory2 = new MemoryCache();

        $this->manager = new CacheManager(
            ['cache1' => $this->memory1, 'cache2' => $this->memory2],
            [],
            'cache1'
        );
    }

    public function testRegisterBackend(): void
    {
        $cache = new MemoryCache();
        $this->manager->registerBackend('cache3', $cache);
        $this->assertContains('cache3', $this->manager->listBackends());
    }

    public function testEnableBackend(): void
    {
        $this->manager->disableBackend('cache1');
        $status = $this->manager->getStatus();
        $this->assertFalse($status['cache1']['enabled']);

        $this->manager->enableBackend('cache1');
        $status = $this->manager->getStatus();
        $this->assertTrue($status['cache1']['enabled']);
    }

    public function testDisableBackend(): void
    {
        $status = $this->manager->getStatus();
        $this->assertTrue($status['cache1']['enabled']);

        $this->manager->disableBackend('cache1');
        $status = $this->manager->getStatus();
        $this->assertFalse($status['cache1']['enabled']);
    }

    public function testPrimaryBackendForReads(): void
    {
        $this->memory1->set('key1', 'value-from-memory1');
        $this->memory2->set('key1', 'value-from-memory2');

        $this->manager->setPrimaryBackend('cache1');
        $this->assertSame('value-from-memory1', $this->manager->get('key1'));

        $this->manager->setPrimaryBackend('cache2');
        $this->assertSame('value-from-memory2', $this->manager->get('key1'));
    }

    public function testSetWritesToAllEnabledBackends(): void
    {
        $this->manager->set('key1', 'value1');

        $this->assertSame('value1', $this->memory1->get('key1'));
        $this->assertSame('value1', $this->memory2->get('key1'));
    }

    public function testSetWritesOnlyToEnabledBackends(): void
    {
        $this->manager->disableBackend('cache2');
        $this->manager->set('key1', 'value1');

        $this->assertSame('value1', $this->memory1->get('key1'));
        $this->assertNull($this->memory2->get('key1'));
    }

    public function testDeleteFromAllEnabledBackends(): void
    {
        $this->manager->set('key1', 'value1');
        $this->manager->delete('key1');

        $this->assertNull($this->memory1->get('key1'));
        $this->assertNull($this->memory2->get('key1'));
    }

    public function testHasChecksOnlyPrimaryBackend(): void
    {
        $this->memory1->set('key1', 'value1');
        $this->manager->setPrimaryBackend('cache1');
        $this->assertTrue($this->manager->has('key1'));

        $this->manager->setPrimaryBackend('cache2');
        $this->assertFalse($this->manager->has('key1'));
    }

    public function testFlushClearsAllEnabledBackends(): void
    {
        $this->manager->set('key1', 'value1');
        $this->manager->set('key2', 'value2');

        $this->manager->flush();

        $this->assertFalse($this->memory1->has('key1'));
        $this->assertFalse($this->memory2->has('key1'));
    }

    public function testGetMultipleReadsFromPrimaryCa(): void
    {
        $this->memory1->setMultiple(['key1' => 'value1', 'key2' => 'value2']);
        $this->manager->setPrimaryBackend('cache1');

        $result = $this->manager->getMultiple(['key1', 'key2']);
        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $result);
    }

    public function testSetMultipleWritesToAllBackends(): void
    {
        $this->manager->setMultiple(['key1' => 'value1', 'key2' => 'value2']);

        $result1 = $this->memory1->getMultiple(['key1', 'key2']);
        $result2 = $this->memory2->getMultiple(['key1', 'key2']);

        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $result1);
        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $result2);
    }

    public function testGetDriverName(): void
    {
        $name = $this->manager->getDriverName();
        $this->assertStringContainsString('multi', $name);
        $this->assertStringContainsString('memory', $name);
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->manager->isAvailable());
    }

    public function testListBackends(): void
    {
        $backends = $this->manager->listBackends();
        $this->assertContains('cache1', $backends);
        $this->assertContains('cache2', $backends);
    }

    public function testGetStatus(): void
    {
        $status = $this->manager->getStatus();

        $this->assertTrue($status['cache1']['enabled']);
        $this->assertTrue($status['cache1']['available']);
        $this->assertTrue($status['cache1']['primary']);
        $this->assertSame('memory', $status['cache1']['driver']);

        $this->assertTrue($status['cache2']['enabled']);
        $this->assertFalse($status['cache2']['primary']);
    }

    public function testChainedOperations(): void
    {
        $this->manager
            ->disableBackend('cache2')
            ->set('key1', 'value1')
            ->enableBackend('cache2')
            ->set('key2', 'value2');

        $this->assertSame('value1', $this->memory1->get('key1'));
        $this->assertNull($this->memory2->get('key1'));
        $this->assertSame('value2', $this->memory2->get('key2'));
    }

    public function testSetMultipleWithTTL(): void
    {
        $this->manager->setMultiple(['key1' => 'value1', 'key2' => 'value2'], 3600);

        $this->assertSame('value1', $this->memory1->get('key1'));
        $this->assertSame('value2', $this->memory2->get('key2'));
    }

    public function testGetWithDefault(): void
    {
        $result = $this->manager->get('nonexistent', 'default');
        $this->assertSame('default', $result);
    }
}
