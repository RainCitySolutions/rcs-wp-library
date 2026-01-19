<?php
declare(strict_types=1);
namespace RCS\WP\Fixtures;

use RCS\WP\PluginInfoInterface;
use RCS\WP\Shortcodes\ScriptMeta;
use RCS\WP\Shortcodes\ShortcodeBase;
use RCS\WP\Shortcodes\StyleMeta;

final class TestShortcode extends ShortcodeBase
{
    public function __construct(PluginInfoInterface $pluginInfo)
    {
        parent::__construct($pluginInfo, self::getTagName());
    }

    public static function getTagName(): string
    {
        return 'test_shortcode';
    }

    public function renderShortcode(array $atts = [], string $content = null): string
    {
        return 'rendered-content';
    }

    public function getScripts(): array
    {
        return [
            new ScriptMeta(
                id: 'test-script',
                url: '/script.js',
                deps: ['jquery'],
                strategy: 'async'
                ),
        ];
    }

    public function getStyles(): array
    {
        return [
            new StyleMeta(
                id: 'test-style',
                url: '/style.css',
                deps: []
                ),
        ];
    }
}
