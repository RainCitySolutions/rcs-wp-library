<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes;

class StyleMeta
{
    /**
     *
     * @param string $id
     * @param string $url
     * @param string[] $deps
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $deps = []
        )
    {
    }
}
