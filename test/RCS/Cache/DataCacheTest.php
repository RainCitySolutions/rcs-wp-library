<?php
declare(strict_types=1);
namespace RCS\Cache;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RCS\Util\ReflectionHelper;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(\RCS\Cache\DataCache::class)]
#[UsesClass(\RCS\Util\ReflectionHelper::class)]
class DataCacheTest extends TestCase
{
    /** @var ArrayAdapter */
    private ArrayAdapter $cacheAdapter;

    private DataCache $dataCache;

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheAdapter = new ArrayAdapter();
        $this->dataCache = new DataCache($this->cacheAdapter);
    }

    public function testInstance(): void
    {
        $tmpAdapter = new ArrayAdapter();
        $tmpTTL = 5;

        $instCache = DataCache::instance($tmpAdapter, 5);

        self::assertNotSame($this->dataCache, $instCache);
        self::assertEquals(
            $tmpAdapter,
            ReflectionHelper::getObjectProperty(get_class($instCache), 'cache', $instCache)
            );
        self::assertEquals(
            $tmpTTL,
            ReflectionHelper::getObjectProperty(get_class($instCache), 'defaultTTL', $instCache)
            );
    }

    public function testSet(): void
    {
        $testKey = 'testSetKey';
        $testValue = 'testSetValue';

        $result = $this->dataCache->set($testKey, $testValue);

        self::assertTrue($result);

        $cachedValue = $this->getFromAdapter($testKey);

        self::assertNotNull($cachedValue);
        self::assertEquals($testValue, $cachedValue);
    }

    public function testGet_noValue(): void
    {
        $testKey = 'testGetKey';

        $cachedValue = $this->dataCache->get($testKey);

        self::assertNull($cachedValue);
    }

    public function testGet_default(): void
    {
        $testKey = 'testGetKey';
        $testValue = 'testGetValue';

        $cachedValue = $this->dataCache->get($testKey, $testValue);

        self::assertNotNull($cachedValue);
        self::assertEquals($testValue, $cachedValue);

        self::assertEquals($testValue, $this->getFromAdapter($testKey));
    }

    public function testHas_present(): void
    {
        $testKey = 'testHasKey';
        $testValue = 'testGetValue';

        self::assertFalse($this->cacheAdapter->hasItem($testKey));

        $this->addToAdapter($testKey, $testValue);

        $hasValue = $this->dataCache->has($testKey);

        self::assertTrue($hasValue);
    }

    public function testHas_missing(): void
    {
        $testKey = 'testHasMissingKey';

        self::assertFalse($this->cacheAdapter->hasItem($testKey));

        $hasValue = $this->dataCache->has($testKey);

        self::assertFalse($hasValue);
    }

    public function testDelete_present(): void
    {
        $testKey = 'testDeleteKey';
        $testValue = 'testDeleteValue';

        $this->addToAdapter($testKey, $testValue);

        $result = $this->dataCache->delete($testKey);

        self::assertTrue($result);

        self::assertFalse($this->cacheAdapter->hasItem($testKey));
    }

    public function testDelete_missing(): void
    {
        $testKey = 'testDeleteMissingKey';

        $result = $this->dataCache->delete($testKey);

        self::assertTrue($result);

        self::assertFalse($this->cacheAdapter->hasItem($testKey));
    }

    public function testClear(): void
    {
        $testKey1 = 'testClearKey1';
        $testValue1 = 'testClearValue1';
        $testKey2 = 'testClearKey2';
        $testValue2 = 'testClearValue2';

        $this->addToAdapter($testKey1, $testValue1);
        $this->addToAdapter($testKey2, $testValue2);

        self::assertTrue($this->dataCache->has($testKey1));
        self::assertTrue($this->dataCache->has($testKey2));

        $result = $this->dataCache->clear();

        self::assertTrue($result);

        self::assertCount(0, $this->cacheAdapter->getValues());
    }

    public function testSetMultiple(): void
    {
        $testKey1 = 'testSetMultipleKey1';
        $testValue1 = 'testSetMultipleValue1';
        $testKey2 = 'testSetMultipleKey2';
        $testValue2 = 'testSetMultipleValue2';

        $result = $this->dataCache->setMultiple(array(
            $testKey1 => $testValue1,
            $testKey2 => $testValue2
        ));

        self::assertTrue($result);

        self::assertEquals($testValue1, $this->getFromAdapter($testKey1));
        self::assertEquals($testValue2, $this->getFromAdapter($testKey2));
    }

    public function testGetMultiple(): void
    {
        $testKey1 = 'testGetMultipleKey1';
        $testValue1 = 'testGetMultipleValue1';
        $testKey2 = 'testGetMultipleKey2';
        $testValue2 = 'testGetMultipleValue2';

        $this->addToAdapter($testKey1, $testValue1);
        $this->addToAdapter($testKey2, $testValue2);

        $result = $this->dataCache->getMultiple(array($testKey1, $testKey2));

        self::assertIsArray($result);
        self::assertCount(2, $result);

        self::assertEquals($testValue1, $result[$testKey1]);
        self::assertEquals($testValue2, $result[$testKey2]);
    }

    public function testGetMultiple_default(): void
    {
        $testKey1 = 'testGetMultipleKey1';
        $testKey2 = 'testGetMultipleKey2';

        $defaultValue = 'testGetMultipleDefault';

        $result = $this->dataCache->getMultiple(array($testKey1, $testKey2), $defaultValue);

        self::assertIsArray($result);
        self::assertCount(2, $result);

        self::assertEquals($defaultValue, $result[$testKey1]);
        self::assertEquals($defaultValue, $result[$testKey2]);

        self::assertEquals($defaultValue, $this->getFromAdapter($testKey1));
        self::assertEquals($defaultValue, $this->getFromAdapter($testKey2));
    }

    public function testGetMultiple_someDefault(): void
    {
        $testKey1 = 'testGetMultipleKey1';
        $testKey2 = 'testGetMultipleKey2';
        $testKey3 = 'testGetMultipleKey3';

        $defaultValue = 'testGetMultipleDefault';
        $testValue2 = 'testGetMultipleValue2';

        $this->addToAdapter($testKey2, $testValue2);

        $result = $this->dataCache->getMultiple(
            array($testKey1, $testKey2, $testKey3),
            $defaultValue
            );

        self::assertIsArray($result);
        self::assertCount(3, $result);

        self::assertEquals($defaultValue, $result[$testKey1]);
        self::assertEquals($defaultValue, $result[$testKey3]);

        self::assertEquals($testValue2, $result[$testKey2]);

        self::assertEquals($defaultValue, $this->getFromAdapter($testKey1));
        self::assertEquals($defaultValue, $this->getFromAdapter($testKey3));

        self::assertEquals($testValue2, $this->getFromAdapter($testKey2));
    }

    public function testDeleteMultiple(): void
    {
        $testKey1 = 'testDeleteMultipleKey1';
        $testKey2 = 'testDeleteMultipleKey2';
        $testKey3 = 'testDeleteMultipleKey3';

        $testValue1 = 'testDeleteMultipleValue1';
        $testValue2 = 'testDeleteMultipleValue2';
        $testValue3 = 'testDeleteMultipleValue3';

        $this->addToAdapter($testKey1, $testValue1);
        $this->addToAdapter($testKey2, $testValue2);
        $this->addToAdapter($testKey3, $testValue3);

        $result = $this->dataCache->deleteMultiple(
            array($testKey2, $testKey3)
            );

        self::assertTrue($result);

        self::assertEquals($testValue1, $this->getFromAdapter($testKey1));

        self::assertNull($this->getFromAdapter($testKey2));
        self::assertNull($this->getFromAdapter($testKey3));
    }

    private function addToAdapter(string $key, mixed $value): void
    {
        $cacheItem = $this->cacheAdapter->getItem($key);
        $cacheItem->set($value);
        $this->cacheAdapter->save($cacheItem);
    }

    private function getFromAdapter(string $key): mixed
    {
        return $this->cacheAdapter->getItem($key)->get();
    }
}
