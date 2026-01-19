<?php
declare(strict_types=1);
namespace RCS\WP\Shortcodes;

use RCS\WP\PluginInfoInterface;

/**
 * Provides the base implementation for WordPress shortcodes allowing classes
 * that extend this class to primarily focus on the renderShortcode()
 * function.
 *
 * NOTE: Shortcode classes utilizing the ShortcodeProxy should not extend
 * this class. They should instead implement ShortcodeImplInf and make use of
 * ShortcodeImplTrait directly.
 */
abstract class ShortcodeBase implements ShortcodeImplInf
{
    use ShortcodeImplTrait;

    protected function __construct(
        protected PluginInfoInterface $pluginInfo,
        protected string $shortcodeTag
        )
    {
        $this->initializeInstance();
    }

    protected function initializeInstance(): void
    {
        assert(!empty($this->shortcodeTag), '$shortcodeTag should be set in the constructor');

        // Register a new shortcode.
        add_shortcode(
            $this->shortcodeTag,
            function (array $atts, string $content = ''): string
            {
                // Maybe enqueue assets of the shortcode.
                if (!is_admin()) {
                    foreach($this->getScripts() as $scriptMeta) {
                        wp_enqueue_script(
                            $scriptMeta->id,
                            $scriptMeta->url,
                            $scriptMeta->deps,
                            $this->pluginInfo->getVersion(),
                            [
                                'strategy' => $scriptMeta->strategy
                            ]
                            );
                    }
                }

                foreach ($this->getStyles() as $styleMeta) {
                    wp_enqueue_style(
                        $styleMeta->id,
                        $styleMeta->url,
                        $styleMeta->deps,
                        $this->pluginInfo->getVersion(),
                        );
                }

                return $this->renderShortcode($atts, $content);
        }
        );
    }
}
