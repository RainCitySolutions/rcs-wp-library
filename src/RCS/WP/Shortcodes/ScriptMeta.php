<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes;

class ScriptMeta
{
    /**
     *
     * @param string $id
     * @param string $url
     * @param string[] $deps
     * @param string $strategy
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $deps = [],
        public string $strategy = 'async'
        )
    {
    }
}
