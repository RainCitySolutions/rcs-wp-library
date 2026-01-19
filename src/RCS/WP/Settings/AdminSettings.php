<?php
declare(strict_types = 1);
namespace RCS\WP\Settings;

use Psr\Log\LoggerInterface;
use RCS\WP\PluginInfoInterface;

abstract class AdminSettings
{
    /** @var array<string, AdminSettingsTab> */
    private array $tabs = [];

    /**
     *
     * @param PluginInfoInterface $pluginInfo
     * @param iterable<AdminSettingsTab> $tabs
     * @param string $optionsPageTitle
     * @param string $optionsPageSlug
     * @param string $optionsMenuTitle
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected PluginInfoInterface $pluginInfo,
        iterable $tabs,
        protected string $optionsPageTitle,
        protected string $optionsPageSlug,
        protected string $optionsMenuTitle,
        protected LoggerInterface $logger
        )
    {
        foreach($tabs as $tab) {
            $this->registerTab($tab);
        }
    }

    protected function initializeInstance(): void
    {
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'onAdminEnqueueScripts']);

            add_action('admin_init', [$this, 'addSettings']);
            add_action('admin_menu', [$this, 'addSettingsMenu']);

            add_filter('plugin_action_links', [$this, 'addPluginActionLinks'], 10, 4);
        }
    }


    /**
     * Register the settings menu.
     *
     * Hooked into the 'admin_menu' event.
     */
    final public function addSettingsMenu(): void
    {
        add_options_page(
            $this->optionsPageTitle,    // page title
            $this->optionsMenuTitle,    // menu title
            'manage_options',           // capability reqd
            $this->optionsPageSlug,     // menu slug name
            array(                      // function to output page contents
                $this,
                'renderSettingsPage'
            )
        );
    }

    /**
     * Register the settings for the active tab.
     *
     * Hooked into the 'admin_init' event and called as a result of the child
     * class being registered with the plugin as the admin helper.
     */
    final public function addSettings(): void
    {
        foreach ($this->tabs as $tab) {
            $tab->registerActions();
        }

        $activeTab = $this->getActiveTab();
        $activeTab->initSettings($this->optionsPageSlug);
    }

    /**
     * Register the scripts and stylesheets for the active tab.
     *
     * Hooked into the 'admin_enqueue_scripts' event.
     */
    final public function onAdminEnqueueScripts(): void
    {
        $activeTab = $this->getActiveTab();

        $activeTab->onEnqueueScripts($this->pluginInfo->getSlug(), $this->pluginInfo->getUrl(), $this->pluginInfo->getVersion());
    }

    private function resolveActiveTabId(): ?string
    {
        if (isset($_GET['tab']) && is_string($_GET['tab'])) {
            return $_GET['tab'];
        }

        if (isset($_POST['_wp_http_referer']) && is_string($_POST['_wp_http_referer'])) {
            $query = parse_url($_POST['_wp_http_referer'], PHP_URL_QUERY);

            if ($query !== null) {
                $args = [];

                parse_str($query, $args);

                if (isset($args['tab']) && is_string($args['tab'])) {
                    return $args['tab'];
                }
            }
        }

        return null;
    }

    private function getActiveTab(): AdminSettingsTab
    {
        $tabId = $this->resolveActiveTabId();

        if ($tabId !== null && isset($this->tabs[$tabId])) {
            return $this->tabs[$tabId];
        }

        return array_values($this->tabs)[0];
    }

    /**
     *
     * @param array<string, mixed> $input
     */
    public function localSanitize(array $input): void
    {
        $activeTab = $this->getActiveTab();
        $activeTab->sanitize($this->pluginInfo->getSlug(), $input);
    }

    final public function registerTab(AdminSettingsTab $tab): void
    {
        $this->tabs[$tab->getId()] ??= $tab;
    }


    /**
     * Render the settings page
     */
    final public function renderSettingsPage(): void
    {
        // check user capabilities
        if ( current_user_can( 'manage_options' ) && count($this->tabs) != 0)
        {
            $activeTab = $this->getActiveTab();

            ?>
            <div class="wrap">
                <h2><?php echo esc_html($this->optionsPageTitle); ?></h2>

                <!-- wordpress provides the styling for tabs. -->
                <h2 class="nav-tab-wrapper">
                    <?php
                        $activeTabId = $activeTab->getId();

                        // Generate a link for each registered tab
                        foreach ($this->tabs as $tab) {
                            // When tab buttons are clicked we jump back to the same page but with a new parameter
                            // that represents the clicked tab. accordingly we make it active
                            printf(
                                '<a href="%s" class="nav-tab %s">%s</a>',
                                esc_url(
                                    add_query_arg(
                                        [
                                            'page' => $this->optionsPageSlug,
                                            'tab' => $tab->getId()
                                        ],
                                        admin_url('options-general.php')
                                    )
                                ),
                                $activeTabId === $tab->getId() ? 'nav-tab-active' : '',
                                esc_html($tab->getName())
                            );
                        }
                    ?>
                </h2>

                <form method="post" action="options.php">
                    <?php
                        settings_fields($this->optionsPageSlug);
                        do_settings_sections($this->optionsPageSlug);
                        submit_button( 'Save Changes' );
                    ?>
                </form>

                <?php
                    $activeTab->renderPostFormData();
                ?>
            </div>
            <?php
        }
    }

    /**
     * Hook for the 'plugin_action_links' filter.
     *
     * @param array<string> $actions    Array of plugin action links
     * @param string        $pluginFile Path to the plugin file
     * @param array<mixed>  $pluginData Array of plugin data
     * @param string        $context    The plugin context
     *
     * @return array<string> A potentially updated array of plugin action links.
     */
    public function addPluginActionLinks(
        array $actions,
        string $pluginFile,
        ?array $pluginData,
        string $context
        ): array
    {
        if ($pluginFile == $this->pluginInfo->getFile()) {
            array_unshift(
                $actions,
                sprintf(
                    '<a href="%s">%s</a>',
                    esc_url(
                        admin_url('options-general.php?page=' . $this->optionsPageSlug)
                        ),
                    esc_html__('Settings', 'your-text-domain')
                    )
                );
        }

        return $actions;
    }

}
