<?php
declare(strict_types = 1);
namespace RCS\WP\Shortcodes\Documentation;

use RCS\WP\Settings\AdminSettingsTab;
use RCS\WP\PluginOptions;
use Psr\Log\LoggerInterface;

final class DocumentationTab extends AdminSettingsTab
{
    private const TAB_NAME = "Documentation";

    const OPTIONS_SECTION_SHORTCODES_ID = 'shortcodeSection';
    const OPTIONS_SECTION_SHORTCODES_TITLE = 'Shortcodes';

    const DOCUMENTATION_FILTER = 'rcsDocumentationFilter';


    public function __construct(PluginOptions $options, LoggerInterface $logger)
    {
        parent::__construct(self::TAB_NAME, $options, $logger);
    }


    public function addSettings(string $pageSlug): void
    {
        /**
         * Shortcodes section
         */
        add_settings_section(
            self::OPTIONS_SECTION_SHORTCODES_ID,
            self::OPTIONS_SECTION_SHORTCODES_TITLE,
            function () {}, // NOSONAR
            $pageSlug
            );
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\Settings\AdminSettingsTab::sanitize()
     */
    public function sanitize(string $pageSlug, ?array $input): ?array
    {
        // Presentation only page, with no inputs so nothing to sanitize
        return $input;
    }
}

