<?php
declare(strict_types=1);
namespace Fixtures;

use RCS\WP\Settings\AdminSettingsTab;
use RCS\WP\Settings\Renderer\SettingsPageRendererInterface;

final class TestSettingsPageRenderer implements SettingsPageRendererInterface
{
    public bool $called = false;

    public function render(
        string $pageTitle,
        string $pageSlug,
        AdminSettingsTab $activeTab,
        array $tabs
        ): void {
            $this->called = true;
    }
}
