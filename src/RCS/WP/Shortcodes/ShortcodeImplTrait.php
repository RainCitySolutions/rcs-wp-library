<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes;

use RCS\WP\Shortcodes\Documentation\ShortcodeDocumentation;

/**
 * This trait provides a default implemementation for the ShortcodeImplInf
 * interface. Its primary use would be to avoid having to implement
 * getDocumentation() and filterAttributes() which may not be used by many
 * short codes.
 */
trait ShortcodeImplTrait
{
    /**
     *
     * @param array<string, string>  $attrs  An array of attributes included
     *      with the short code.
     * @param string  $content The content, if any between a start and
     *      end tag for the short code. Likely an empty string when there Is
     *      no end tag.
     *
     * @return string The HTML content for the short code.
     *
     * @see \RCS\WP\Shortcodes\ShortcodeImplInf::renderShortcode()
     */
    public function renderShortcode(array $attrs = [], ?string $content = null): string // NOSONAR
    {
        return '';
    }

    /**
     *
     * @param ShortcodeDocumentation[] $documentation
     *
     * @return ShortcodeDocumentation[]
     *
     * @see \RCS\WP\Shortcodes\ShortcodeImplInf::getDocumentation()
     */
    public function getDocumentation(array $documentation): array // NOSONAR
    {
        return array();
    }

    /**
     *
     * @param array<string, string> $combinedAtts The combined array of default and provided
     *       shortcode attributes.
     * @param array<string, string> $defaultPairs The default shortcode attributes.
     * @param array<string, string> $providedAtts The provided attributes.
     * @param string $shortcode   The shortcode name.
     *
     * @return array<string, mixed> The filtered attributes.
     *
     * @see \RCS\WP\Shortcodes\ShortcodeImplInf::filterAttributes()
     */
    public function filterAttributes(array $combinedAtts, array $defaultPairs, array $providedAtts, string $shortcode): array // NOSONAR
    {
        return $combinedAtts;
    }


    /**
     *
     * @return ScriptMeta[]
     *
     * @see \RCS\WP\Shortcodes\ShortcodeImplInf::getScripts().
     */
    public function getScripts(): array
    {
        return [];
    }

    /**
     * @return StyleMeta[]
     *
     * @see \RCS\WP\Shortcodes\ShortcodeImplInf::getStyles().
     */
    public function getStyles(): array
    {
        return [];
    }

    protected function isEditPreviewMode(): bool
    {
        $result = false;

        if ((isset($_GET['action']) && $_GET['action'] === 'edit') ||
            (isset($_REQUEST['action']) && $_REQUEST['action'] === 'avia_ajax_text_to_preview') )
        {
            $result = true;
        }

        return $result;
    }
}
