<?php
declare(strict_types=1);
namespace RCS\Json\Test;

use JsonMapper\Handler\FactoryRegistry;
use RCS\Json\JsonClientTrait;
use Psr\SimpleCache\CacheInterface;

/**
 * This class is in a seperate namespace that JsonClientTrait to ensure that
 * the testing of getCacheKey() can exercise the container class being in a
 * different namespace.
 */
class JsonClientTraitTestClass
{
    use JsonClientTrait;

    public function __construct(?CacheInterface $cache = null, int $cacheTTL = 600, ?FactoryRegistry $factory = null)
    {
        $this->initJsonClientTrait($cache, $cacheTTL, $factory);
    }
}
