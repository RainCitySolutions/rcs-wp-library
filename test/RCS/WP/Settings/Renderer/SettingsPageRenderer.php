<?php
declare(strict_types = 1);
namespace RCS\WP\Settings\Renderer;

use RCS\WP\Settings\AdminSettingsTab;

final class SettingsPageRenderer implements SettingsPageRendererInterface
{
    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\Settings\Renderer\SettingsPageRendererInterface::render()
     */
    public function render(
        string $pageTitle,
        string $pageSlug,
        AdminSettingsTab $activeTab,
        array $tabs
        ): void {
            if (! current_user_can('manage_options') || empty($tabs)) { // @phpstan-ignore empty.variable
                return;
            }

            ?>
        <div class="wrap">
            <h2><?= esc_html($pageTitle) ?></h2>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab): ?>
                    <a
                        href="<?= esc_url(add_query_arg(
                            ['page' => $pageSlug, 'tab' => $tab->getId()],
                            admin_url('options-general.php')
                        )) ?>"
                        class="nav-tab <?= $tab === $activeTab ? 'nav-tab-active' : '' ?>"
                    >
                        <?= esc_html($tab->getName()) ?>
                    </a>
                <?php endforeach ?>
            </h2>

            <form method="post" action="options.php">
                <?php
                    settings_fields($pageSlug);
                    do_settings_sections($pageSlug);
                    submit_button('Save Changes');
                ?>
            </form>

            <?php $activeTab->renderPostFormData(); ?>
        </div>
        <?php
    }
}
