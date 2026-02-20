<?php
declare(strict_types = 1);
namespace RCS\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Bidirectional mapping between strings and integers backed by a cache.
 *
 */
class StringIntBiDiMap
{
    private string $stringToIntCacheKey;
    private string $intToStringCacheKey;

    /** @var array<string, int> */
    private array $stringToInt;

    /** @var array<int, string> */
    private array $intToString;

    public function __construct(
        private CacheInterface $cache,
        string $cacheKeyPrefix,
        private ?int $ttl = null
        )
    {
        $this->intToStringCacheKey = $cacheKeyPrefix . '.bidimap.int_to_str';
        $this->stringToIntCacheKey = $cacheKeyPrefix . '.bidimap.str_to_int';

        $this->stringToInt = $cache->get($this->stringToIntCacheKey, []);
        $this->intToString = $cache->get($this->intToStringCacheKey, []);
    }

    public function set(string $key, int $value): void
    {
        if (isset($this->stringToInt[$key])) {
            unset($this->intToString[$this->stringToInt[$key]]);
        }
        if (isset($this->intToString[$value])) {
            unset($this->stringToInt[$this->intToString[$value]]);
        }

        $this->stringToInt[$key] = $value;
        $this->intToString[$value] = $key;

        $this->persist();
    }

    public function getInt(string $key): ?int
    {
//         if (!isset($this->stringToInt[$key])) {
//             throw new OutOfBoundsException("No mapping for {$key}");
//         }
        return $this->stringToInt[$key] ?? null;
    }

    /**
     * Fetch the string value associated with the integer.
     *
     * @param int $value
     *
     * @return string|NULL The string associated with the integer or null If
     *      there is no mapping for the integer.
     */
    public function getString(int $value): ?string
    {
//         if (!isset($this->intToString[$value])) {
//             throw new OutOfBoundsException("No mapping for {$value}");
//         }

        return $this->intToString[$value] ?? null;
    }

    public function removeByString(string $key): void
    {
        if (isset($this->stringToInt[$key])) {
            $value = $this->stringToInt[$key];
            unset($this->stringToInt[$key], $this->intToString[$value]);
            $this->persist();
        }
    }

    public function clear(): void
    {
        $this->stringToInt = [];
        $this->intToString = [];
        $this->cache->deleteMultiple([
            $this->stringToIntCacheKey,
            $this->intToStringCacheKey
        ]);
    }

    private function persist(): void
    {
        $this->cache->set($this->stringToIntCacheKey, $this->stringToInt, $this->ttl);
        $this->cache->set($this->intToStringCacheKey, $this->intToString, $this->ttl);
    }
}
