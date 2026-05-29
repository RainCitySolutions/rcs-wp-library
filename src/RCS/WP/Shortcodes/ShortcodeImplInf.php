<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes;

use RCS\WP\Shortcodes\Documentation\ShortcodeDocumentation;

interface ShortcodeImplInf
{
    /**
     * Fetch the tag name of the short code.
     *
     * @return string A string identifying the short code.
     */
    public static function getTagName(): string;

    /**
     * Render the short code.
     * <p>
     * Because this function will ultimately be called by WordPress we cannot
     * enforce parameter types. The defaults might not help but they can't
     * hurt.
     *
     * @param array<string, string>  $attrs  An array of attributes included
     *      with the short code.
     * @param string  $content The content, if any between a start and
     *      end tag for the short code. Likely an empty string when there Is
     *      no end tag.
     *
     * @return string The HTML content for the short code.
     */
    public function renderShortcode(array $attrs = [], ?string $content = null): string;

    /**
     * Fetch the documentation for the short code.
     *
     * @param ShortcodeDocumentation[] $documentation An array of ShortcodeDocumentation instances.
     *
     * @return ShortcodeDocumentation[] The array of ShortcodeDocumentation instances with ours added.
     *
     * @see ShortcodeDocumentation
     */
    public function getDocumentation(array $documentation): array;

    /**
     * Filter the attributes for the short code.
     * <p>
     * The filter can be used to ensure that integer attributes are
     * represented as integers for example.
     *
     * @param array<string, string> $combinedAtts The combined array of default and provided
     *       shortcode attributes.
     * @param array<string, string> $defaultPairs The default shortcode attributes.
     * @param array<string, string> $providedAtts The provided attributes.
     * @param string $shortcode   The shortcode name.
     *
     * @return array<string, mixed> The filtered attributes.
     */
    public function filterAttributes(
        array $combinedAtts,
        array $defaultPairs,
        array $providedAtts,
        string $shortcode
        ): array;

    /**
     * Fetch the scripts to be enqueued.
     *
     * @return ScriptMeta[]
     */
    public function getScripts(): array;

    /**
     * Fetch the styles to be enqueued.
     *
     * @return StyleMeta[]
     */
    public function getStyles(): array;
}
