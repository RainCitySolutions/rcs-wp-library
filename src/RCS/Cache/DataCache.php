<?php
declare(strict_types=1);
namespace RCS\Cache;

use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Traversable;

class DataCache implements CacheInterface
{
    private const DEFAULT_TTL = 600;

    /** @var CacheItemPoolInterface Underlying cache implementation */
    private static CacheItemPoolInterface $backingCache;

    /** @var CacheItemPoolInterface Cache to use for this instance */
    private CacheItemPoolInterface $cache;

//     /** @var int TTL in seconds */
//     private int $defaultTTL = self::DEFAULT_TTL;

    /** @var LoggerInterface A logger for the class */
    private LoggerInterface $log;

    /**
     * Helper function to aid in the transition from the singleton implementation.
     *
     * @param mixed ...$args
     *
     * @return \RCS\Cache\DataCache
     */
    public static function instance(...$args): DataCache
    {
        $cache = null;
        $ttl = self::DEFAULT_TTL;
        $logger = null;

        foreach ($args as $arg) {
            if (is_object($arg)) {
                if ($arg instanceof CacheItemPoolInterface) {
                    $cache = $arg;
                } elseif ($arg instanceof LoggerInterface) {
                    $logger = $arg;
                }
            } elseif (is_int($arg) || 1 < @intval($arg)) {
                $ttl = intval($arg);
            }
        }

        return new DataCache($cache, $logger, $ttl);
    }

    /**
     * Constructs an instance of a data cache.
     *
     * If a cache implementation is provided it is used, otherwise a default
     * implementation will be choosen from Memcached, SQLite or the file
     * system depending on which one is available.
     *
     * @param CacheItemPoolInterface $customCache A cache implementation to
     *      use. If not provided a default will be selected.
     * @param LoggerInterface $logger
     * @param int $defaultTTL The default TTL to use for items added to the
     *      cache. If not provided the TTL is set to 600 seconds/5 minutes.
     */
    public function __construct(
        ?CacheItemPoolInterface $customCache = null,
        ?LoggerInterface $logger = null,
        private int $defaultTTL = self::DEFAULT_TTL
        )
    {
        if (is_null($logger)) {
            $this->log = new Logger('name');
            $this->log->pushHandler(new NullHandler());
        } else {
            $this->log = $logger;
        }

        if (isset($customCache)) {
            $this->cache = $customCache;
        } else {
            $this->cache = $this->initBackingCache();
        }

        $this->defaultTTL = $defaultTTL;
    }

    /**
     * Initializes the backing/default cache if it hasn't already been
     * created.
     *
     * @return CacheItemPoolInterface The backing cache
     */
    private function initBackingCache(): CacheItemPoolInterface
    {
        $cache = null;

        if (isset(self::$backingCache)) {
            $cache = self::$backingCache;
        } else {
            $cache = $this->createMemcachedCache();

            if (null === $cache) {
                $cache = $this->createSqliteCache();

                if (null === $cache) {
                    $cache = new FilesystemAdapter('', 0, self::getFilesCacheDir());
                }
            }

            self::$backingCache = $cache;
        }

        return $cache;
    }

    /**
     * Attempts to create an instance of a Memcached adapter.
     *
     * @return CacheItemPoolInterface|NULL A reference to the cache or null
     *      if it cannot be created.
     */
    private function createMemcachedCache(): ?CacheItemPoolInterface
    {
        $cache = null;

        if (extension_loaded('memcached')) {
            try {
                $client = MemcachedAdapter::createConnection('memcached://127.0.0.1');

                $cache = new MemcachedAdapter($client);
            }
            catch (\Exception $e) {
                $this->log->info('Unable to setup Memcached cache: {msg}', array('msg' => $e->getMessage()));
            }
        }

        return $cache;
    }

    /**
     * Attempts to create an instance of a SQLite adapter.
     *
     * @return CacheItemPoolInterface|NULL A reference to the cache or null
     *      if it cannot be created.
     */
    private function createSqliteCache(): ?CacheItemPoolInterface
    {
        $cache = null;

        if (extension_loaded('pdo_sqlite')) {
            try {
                $dbFile = self::getSqliteFile();
                $dbDir = dirname($dbFile);

                if (!file_exists($dbDir)) {
                    mkdir($dbDir);
                }

                $backend = new \PDO('sqlite:'.self::getSqliteFile());
                $cache = new PdoAdapter($backend);
            }
            catch (\Exception $e) {
                $this->log->info('Unable to setup Sqlite cache: {msg}', array('msg' => $e->getMessage()));
            }
        }

        return $cache;
    }

    /**
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::set()
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        /** @var \Psr\Cache\CacheItemInterface */
        $item = $this->cache->getItem($key);

        $item->set($value);
        if (isset($ttl)) {
            $item->expiresAfter($ttl);
        } else {
            $ttl = $this->defaultTTL;
            $item->expiresAfter($ttl);
        }

        return $this->cache->save($item);
    }

    /**
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::get()
     */
    public function get(string $key, mixed $default = null): mixed
    {
        /** @var \Psr\Cache\CacheItemInterface */
        $item = $this->cache->getItem($key);
        if (!$item->isHit() && isset($default)) {
            $this->log->debug("Request for $key not found, returning default");
            $item->set($default);
            $this->cache->save($item);
        }

        return $item->get();
    }

    /**
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::clear()
     */
    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /**
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::has()
     */
    public function has(string $key): bool
    {
        return $this->cache->hasItem($key);
    }

    /**
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::delete()
     */
    public function delete(string $key): bool
    {
        return $this->cache->deleteItem($key);
    }

    /**
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::deleteMultiple()
     */
    public function deleteMultiple(Traversable|array $keys): bool
    {
        return $this->cache->deleteItems($keys);
    }

    /**
     *
     * @param iterable<string, mixed> $values
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::setMultiple()
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $result = false;

        foreach ($values as $key => $value) {
//             if (!is_string($key)) {
//                 throw new DataCacheException('Invalid key included in values parameter');
//             }

            $result = $this->set($key, $value, $ttl) || $result;
        }

        return $result;
    }

    /**
     *
     * {@inheritDoc}
     * @see \Psr\SimpleCache\CacheInterface::getMultiple()
     */
    public function getMultiple(iterable $keys, mixed $default = null): Traversable|array
    {
        $result = array();

        foreach ($keys as $key) {
//             if (!is_string($key)) {
//                 throw new DataCacheException('key argument must be a string or an integer');
//             }

            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    private static function getSqliteFile(): string
    {
        return sys_get_temp_dir() . '/datacache.sqlite3';
    }

    private static function getFilesCacheDir(): string
    {
        return sys_get_temp_dir() . '/files.cache';
    }

}
