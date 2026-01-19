<?php
declare(strict_types = 1);
namespace RCS\WP\Settings\Renderer;

use RCS\WP\Settings\AdminSettingsTab;

interface SettingsPageRendererInterface
{
    /**
     * @param non-empty-array<string, AdminSettingsTab> $tabs
     */
    public function render(
        string $pageTitle,
        string $pageSlug,
        AdminSettingsTab $activeTab,
        array $tabs
        ): void;
}
