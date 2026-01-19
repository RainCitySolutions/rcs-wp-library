<?php
declare(strict_types = 1);
namespace RCS\WP;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RCS\WP\Shortcodes\ShortcodeImplInf;
use Psr\Log\LoggerInterface;

/**
 * This class acts as a proxy for classes implementing shortcodes,
 * specifically those implementing the ShortcodeImplInf. The class will only
 * instantiate the class for a shortcode implemention when there is a
 * request to render that shortcode. This is done with the aid of a
 * Dependency Injection framework such as PHP-DI.
 */
class ShortcodeProxy
{
    /** @var array<string, string> */
    private array $shortcodeMap = [];

    public function __construct(
        private ContainerInterface $diContainer,
        private PluginInfoInterface $pluginInfo,
        private LoggerInterface $logger
        )
    {}

    /**
     * Render a short code.
     *
     * @param array<string, string>  $attrs  An array of attributes included
     *      with the short code.
     * @param string|null  $content The content, if any between a start and
     *      end tag for the short code. Likely an empty string when there Is
     *      no end tag.
     * @param string $shortcodeTag The tag for the shortcode to be rendered.
     *
     * @return string The HTML content for the short code.
     */
    public function renderShortcode(array $attrs, ?string $content, string $shortcodeTag): string
    {
        $html = '';

        // Verify the shortcode is in our map
        if (array_key_exists($shortcodeTag, $this->shortcodeMap)) {
            try {
                // Get an instance of the shortcode class from the DI framework
                $obj = $this->diContainer->get($this->shortcodeMap[$shortcodeTag]);

                // Maybe enqueue assets of the shortcode.
                if (!is_admin()) {
                    foreach($obj->getScripts() as $scriptMeta) {
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

                foreach ($obj->getStyles() as $styleMeta) {
                    wp_enqueue_style(
                        $styleMeta->id,
                        $styleMeta->url,
                        $styleMeta->deps,
                        $this->pluginInfo->getVersion(),
                        );
                }

                // Have the implementation render the shortcode
                $html = $obj->renderShortcode($attrs, $content);
            }
            catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
                $this->logger->error(
                    'Unable to retrieve class instance for shortcode {sc}: {err}',
                    [
                        'sc' => $shortcodeTag,
                        'err' => $e->getMessage()
                    ]
                );
            }
        }

        return $html;
    }

    /**
     * Adds a shortcode class to the proxy.
     *
     * @param string $shortcodeClass
     */
    public function addShortcode(string $shortcodeClass): void
    {
        // Ensure the class implements the ShortcodeImplInf interface
        assert(
            is_a($shortcodeClass, ShortcodeImplInf::class, true),
            $shortcodeClass. ' must implement ' . ShortcodeImplInf::class
            );

        $tag = $shortcodeClass::getTagName();

        if (!array_key_exists($tag, $this->shortcodeMap))
        {
            // Add the mapping
            $this->shortcodeMap[$tag] = $shortcodeClass;

            // add the shortcode to WordPress with our handler
            add_shortcode($tag, [$this, 'renderShortcode']);
        }
    }

    /**
     * Add a set of shortcode classes to the proxy.
     *
     * @param list<string> $shortcodes
     */
    public function addShortcodes(array $shortcodes): void
    {
        foreach($shortcodes as $class) {
            $this->addShortcode($class);
        }
    }
}
