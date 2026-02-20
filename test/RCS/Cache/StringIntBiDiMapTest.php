<?php
declare(strict_types = 1);
namespace RCS\Cache;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\SimpleCache\CacheInterface;
use function PHPUnit\Framework\assertNull;


#[CoversClass(\RCS\Cache\StringIntBiDiMap::class)]
class StringIntBiDiMapTest extends TestCase
{
    private const STR_TO_INT_KEY = '.bidimap.str_to_int';
    private const INT_TO_STR_KEY = '.bidimap.int_to_str';

    public function testSetPersistsToCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $prefix = 'test';
        $strKey = $prefix . self::STR_TO_INT_KEY;
        $intKey = $prefix . self::INT_TO_STR_KEY;

        // constructor loads from cache
        $cache->method('get')
        ->willReturnMap([
            [$strKey, [], []],
            [$intKey, [], []],
        ]);

        $calls = [];

        $cache->expects($this->exactly(2))
        ->method('set')
        ->willReturnCallback(function ($key, $value, $ttl) use (&$calls) {
            $calls[] = [$key, $value, $ttl];
            return true;
        });

        $map = new StringIntBiDiMap($cache, $prefix);

        $map->set('apple', 1);

        $this->assertSame(
            [
                [$strKey, ['apple' => 1], null],
                [$intKey, [1 => 'apple'], null],
            ],
            $calls
            );

        $this->assertSame(1, $map->getInt('apple'));
        $this->assertSame('apple', $map->getString(1));
    }

    public function testClearDeletesCacheKeys(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $prefix = 'test';
        $strKey = $prefix . self::STR_TO_INT_KEY;
        $intKey = $prefix . self::INT_TO_STR_KEY;

        $cache->method('get')->willReturn([]);

        $cache->expects($this->once())
        ->method('deleteMultiple')
        ->with([$strKey, $intKey])
        ->willReturn(true);

        $map = new StringIntBiDiMap($cache, $prefix);

        $map->clear();
    }

    public function testRemoveByStringPersists(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $prefix = 'test';
        $strKey = $prefix . self::STR_TO_INT_KEY;
        $intKey = $prefix . self::INT_TO_STR_KEY;

        $cache->method('get')
        ->willReturnMap([
            [$strKey, [], ['apple' => 1]],
            [$intKey, [], [1 => 'apple']],
        ]);

        $calls = [];

        $cache->expects($this->exactly(2))
        ->method('set')
        ->willReturnCallback(function ($key, $value, $ttl) use (&$calls) {
            $calls[] = [$key, $value, $ttl];
            return true;
        });

        $map = new StringIntBiDiMap($cache, $prefix);

        $map->removeByString('apple');

        $this->assertSame(
            [
                [$strKey, [], null],
                [$intKey, [], null],
            ],
            $calls
            );

        assertNull($map->getInt('apple'));
    }

    public function testGetNullWhenMissing(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([]);

        $map = new StringIntBiDiMap($cache, 'test');

        assertnull($map->getInt('missing'));
    }

    /**
     * Tests two instances of StringIntBiDiMap where the same string key is
     * used in each map but with different integer values.
     */
    public function testMultipleInstances(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $prefixA = 'test-a';
        $strKeyA = $prefixA . self::STR_TO_INT_KEY;
        $intKeyA = $prefixA . self::INT_TO_STR_KEY;

        $prefixB = 'test-b';
        $strKeyB = $prefixB . self::STR_TO_INT_KEY;
        $intKeyB = $prefixB . self::INT_TO_STR_KEY;

        // constructor loads from cache
        $cache->method('get')
        ->willReturnMap([
            [$strKeyA, [], []],
            [$intKeyA, [], []],
            [$strKeyB, [], []],
            [$intKeyB, [], []],
        ]);

        $calls = [];

        $cache->expects($this->exactly(4))
        ->method('set')
        ->willReturnCallback(function ($key, $value, $ttl) use (&$calls) {
            $calls[] = [$key, $value, $ttl];
            return true;
        });

        $mapA = new StringIntBiDiMap($cache, $prefixA);
        $mapB = new StringIntBiDiMap($cache, $prefixB);

        $mapA_Apple = 1;
        $mapB_Apple = 2;

        $mapA->set('apple', $mapA_Apple);
        $mapB->set('apple', $mapB_Apple);

        $this->assertSame(
            [
                [$strKeyA, ['apple' => $mapA_Apple], null],
                [$intKeyA, [$mapA_Apple => 'apple'], null],
                [$strKeyB, ['apple' => $mapB_Apple], null],
                [$intKeyB, [$mapB_Apple => 'apple'], null],
            ],
            $calls
            );

        $this->assertSame($mapA_Apple, $mapA->getInt('apple'));
        $this->assertSame('apple', $mapA->getString($mapA_Apple));

        $this->assertSame($mapB_Apple, $mapB->getInt('apple'));
        $this->assertSame('apple', $mapB->getString($mapB_Apple));
    }

}
