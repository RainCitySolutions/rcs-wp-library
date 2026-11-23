<?php

namespace RCS\WP;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RCS\Cache\DataCache;
use RCS\Logging\LastErrorLogger;
use RCS\Logging\Timer;
use RCS\Logging\TimerInterface;
use RCS\WP\Database\DatabaseUpdater;
use RCS\WP\Shortcodes\EmailShortCode;
use RCS\WP\Shortcodes\UsernameShortCode;
use RCS\WP\ShortURL\ShortUrlOptionsTab;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\StoreFactory;

/*
 * Try to force loading the interface from our vendor folder to avoid a
 * confict with an older version in the voting plugin.
 */

class_exists(ContainerInterface::class);    // @phpstan-ignore function.impossibleType

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 */
abstract class WordPressPlugin
{
    public const REQUEST_ID = 'RainCityRequestId';
    public const PLUGIN_ENTRYPOINT = 'plugin.entryPoint';


    //    private const DEFAULT_PLUGIN_VERSION = '1.0.0';
    //    private const DB_UPGRADE_ACTIVE_FLAG = 'raincity_wpf_dbUpgradeActive';

    const DATABASE_VERSIONS_OPTIONS_NAME = 'raincity_wpf_database_versions';

    const ON_PLUGIN_ACTIVATION_ACTION = 'raincity_wpf_plugin_activation_action';
    const ON_PLUGIN_DEACTIVATION_ACTION = 'raincity_wpf_plugin_deactivation_action';
    const ON_PLUGIN_UNINSTALL_ACTION = 'raincity_wpf_plugin_uninstall_action';

    const ON_REGISTER_SHORTCODE_ACTION = 'raincity_wpf_register_shortcode_action';

    protected readonly PluginInfoInterface $pluginInfo;
    protected ContainerInterface $container;

    /**
     * Kick start a plugin.
     *
     * @param string    $pluginClass    The fully qualified name of the plugin class.
     * @param string    $entryPointFile The entrypoint file for the class.
     *
     * @return object   An instance of the plugin class.
     */
    public static function kickstart(string $pluginClass, string $entryPointFile): object
    {
        if (defined('ABSPATH')) { // Wrap in case we get invoked via unit testing
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        if (is_subclass_of($pluginClass, WordPressPlugin::class)) {
            $plugin = new $pluginClass($entryPointFile);
        } else {
            $plugin = null;
        }

        return $plugin;
    }

    /**
     * Classes should override this function to execute code during object
     * construction.
     * 
     * @return void
     */
    protected function onConstruct(): void {}

    private function __construct(
        string $entryPointFile
    ) {
        $this->pluginInfo = new PluginInfo($entryPointFile);

        // Give children an chance to initialize
        $this->onConstruct();

        add_action(
            'init',
            function () use ($entryPointFile): void {
                $this->container = $this->privInitializeContainer($entryPointFile);

                $this->privInitialize($this->container);
            }
        );

        // Action to generate a unique requestID for each request, used in logging
        add_action(
            'init',
            function () {
                putenv(self::REQUEST_ID . '=' . uniqid('req'));
            },
            5
        );

        /**
         * Hook to load a plugin specific functions.php from the plugin's
         * write folder, if it exists, after the theme has been loaded. The
         * write folder is wp-content/uploads/<pluginSlug>
         *
         * Allows for customization without a change to theme or plugin code.
         *
         * <p><strong>Use With Care!</strong>
         */
        add_action(
            'after_setup_theme',
            function (): void {
                $functionsPhp = $this->pluginInfo->getWriteDir() . 'functions.php';

                if (file_exists($functionsPhp)) {
                    require_once $functionsPhp;
                }
            },
            100
        );
    }

    /**
     * Classes should override this function to performan any initialization
     * needed at the time of instantiation.
     * 
     * @return void
     */
    protected function initialize(ContainerInterface $container): void {}

    /**
     * Perform any initialization needed at the time of instantiation.
     * 
     * @return void
     */
    private function privInitialize(ContainerInterface $container): void
    {
        add_action('admin_enqueue_scripts', [$this, 'onAdminEnqueueScripts']);
        add_action('wp_enqueue_scripts', [$this, 'onWpEnqueueScripts']);
        add_filter('script_loader_tag', [$this, 'onScriptLoaderTag'], 10, 2);

        // Enable application passwords for development environments
        add_filter(
            'wp_is_application_passwords_available',
            function ($isAvailable) {
                return 'development' === wp_get_environment_type() || $isAvailable;
            }
        );

        // Add actions to plugin activation and deactivation hooks
        // register_activation_hook($this->mainPluginFilename, array($this, 'activate_plugin'));
        // register_deactivation_hook($this->mainPluginFilename, array($this, 'deactivate_plugin'));
        // register_uninstall_hook($this->mainPluginFilename, array(get_class($this), 'uninstall_plugin'));

        add_filter('plugin_action_links', [$this, 'addPluginActionLinks'], 10, 4);

        $this->initialize($container);
    }

    /**
     * Initialize the Dependency Injection container.
     * 
     * Called in the context of the WordPress 'init' hook.
     * 
     * @param string $entryPointFile
     * 
     * @return ContainerInterface
     */
    private function privInitializeContainer(string $entryPointFile): ContainerInterface
    {
        if (!function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $containerProps = [
            self::PLUGIN_ENTRYPOINT => $entryPointFile,
            PluginInfoInterface::class => $this->pluginInfo,
        ];

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions($containerProps);
        $containerBuilder->addDefinitions($this->privGetDefinitions());
        $containerBuilder->addDefinitions($this->getDefinitions());

        // 20260317 - stop using compiled container as it misses providing some parameters to objects
        //         if (!file_exists(get_home_path() . 'wp-config-local.php')) {
        //             $containerBuilder->enableCompilation(self::getCompiledContainerPath($entryPointFile));
        //         }

        $container = $containerBuilder->build();

        $this->loadContainerClasses(
            $container,
            [
                LastErrorLogger::class,
            ]
        );

        if (is_admin()) {
            $this->loadContainerClasses(
                $container,
                [
                    AjaxCacheHelper::class,
                ]
            );
        }

        /** @var ShortcodeProxy $scProxy */
        $scProxy = $container->get(ShortcodeProxy::class);
        $scProxy->addShortcodes([
            EmailShortCode::class,
            UsernameShortCode::class,
        ]);

        /** @var AjaxHandlerProxy $ahProxy */
        $ahProxy = $container->get(AjaxHandlerProxy::class);
        $ahProxy->addHandlers([
            AjaxCacheHelper::class,
        ]);

        return $container;
    }

    /**
     * Loads the provided set of classes from the container so they can hook
     * into WordPress as needed.
     *
     * @param ContainerInterface $container
     * @param string[] $classes
     */
    protected function loadContainerClasses(ContainerInterface $container, array $classes): void
    {
        foreach ($classes as $class) {
            $container->get($class);
        }
    }

    /**
     * Handler for 'admin_enqueue_scripts' action.
     *
     * Derived classes should override this function to register admin
     * scripts and styles. This function should be used for any scripts
     * or styles that need to be available on any admin page.
     *
     * <p>Option Tabs should use the
     * {@link AdminSettingsTab::onEnqueueScripts()} function for registering
     * tab specific scripts and styles.
     */
    public function onAdminEnqueueScripts(): void {}


    /**
     * Handler for 'wp_enqueue_scripts' action.
     *
     * Derived classes should override this function to register public
     * scripts and styles. This function should be used for any scripts
     * or styles that need to be available on any public page.
     */
    public function onWpEnqueueScripts(): void {}


    /**
     * Handler 'script_loader_tag' filter.
     *
     * Checks if the script tag contains 'async' or 'defer'. If so, modifies
     * the &lt;script&gt; element to have the corresponding tag.
     *
     * @param string $tag The &lt;script&gt; tag.
     * @param string $handle The handle for the script.
     *
     * @return string  A possibly modified script tag.
     */
    public function onScriptLoaderTag(string $tag, string $handle): string
    {
        $replacements = array('<script');

        // if the unique handle/name of the registered script has 'async' in it
        if (strpos($handle, 'async') !== false) {
            // include the async attribute in the tag
            array_push($replacements, 'async');
        }

        // if the unique handle/name of the registered script has 'defer' in it
        if (strpos($handle, 'defer') !== false) {
            // include the defer attribute in the tag
            array_push($replacements, 'defer');
        }

        return str_replace('<script', join(' ', $replacements), $tag);
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
    ): array {
        if ($pluginFile == $this->container->get(PluginInfoInterface::class)->getEntryPointFile()) {
            $settingsSlug = $this->getSettingsPageSlug();

            if (isset($settingsSlug)) {
                array_unshift(
                    $actions,
                    sprintf(
                        '<a href="%s/wp-admin/options-general.php?page=%s">Settings</a>',
                        get_bloginfo('wpurl'),
                        $settingsSlug
                    )
                );
            }
        }

        return $actions;
    }

    /**
     * Retrieves the Settings page slug of the plugin. This method should be
     * overwritten by a plugin implementation if it has a Settings page.
     *
     * @return NULL
     */
    protected function getSettingsPageSlug(): ?string
    {
        return null;
    }


    /**
     * The code that runs during plugin activation.
     */
    public function activate_plugin(): void
    {
        // Logger::getLogger(get_called_class(), $this->pluginSlug)->debug('Activating ' . $this->pluginName);

        // $options = static::getOptions();

        // if (isset($options['name'])) {
        //     add_option($options['name'], $options['initialValue']);
        // } else {
        //     foreach ($options as $optionEntry) {
        //         if (is_array($optionEntry)) {
        //             add_option($optionEntry['name'], $optionEntry['initialValue']);
        //         }
        //     }
        // }

        do_action(self::ON_PLUGIN_ACTIVATION_ACTION);
    }


    /**
     * The code that runs during plugin deactivation.
     */
    public function deactivate_plugin(): void
    {
        // Logger::getLogger(get_called_class())
        //     ->debug('Deactivating ' . $this->pluginName);

        // do_action(self::ON_PLUGIN_DEACTIVATION_ACTION);

        // CronJob::deactivate();
    }


    /**
     * The code that runs during plugin removal.
     */
    public static function uninstall_plugin(): void
    {
        // $pluginInfo = PluginInformation::getPluginInfo();

        // Logger::getLogger(get_called_class(), $pluginInfo->getSlug())
        //     ->debug('Uninstalling ' . $pluginInfo->getPluginName());

        // do_action(self::ON_PLUGIN_UNINSTALL_ACTION);

        // $options = static::getOptions();

        // if (isset($options['name'])) {
        //     delete_option($options['name']);
        // } else {
        //     foreach ($options as $optionEntry) {
        //         if (is_array($optionEntry)) {
        //             delete_option($optionEntry['name']);
        //         }
        //     }
        // }

        // DataCache::uninstall();
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @param   AdminHelperInf   $adminInst Reference to a class implementing the Admin_Helper_Inf interface.
     */
    // public function register_admin_helper(AdminHelperInf $adminInst): void
    // {
    //     if (is_admin()) {
    //         $this->loader->addAction('admin_enqueue_scripts', $adminInst, 'onAdminEnqueueScripts');

    //         $this->loader->addAction('admin_init', $adminInst, 'addSettings');
    //         $this->loader->addAction('admin_menu', $adminInst, 'addSettingsMenu');
    //     }
    // }



    // private static function shutdownFunction(string $pluginSlug): void
    // {
    //     $error = error_get_last();

    //     if (!is_null($error)) {
    //         $logger = Logger::getLogger(WordPressLogger::BASE_LOGGER, $pluginSlug);

    //         switch ($error['type']) {
    //             case E_ERROR:
    //                 $level = \Psr\Log\LogLevel::CRITICAL;
    //                 break;

    //             case E_WARNING:
    //                 $level = \Psr\Log\LogLevel::WARNING;
    //                 break;

    //             case E_NOTICE:
    //                 if (preg_match('/^Constant .* already defined$/', $error['message'])) {
    //                     $level = \Psr\Log\LogLevel::DEBUG;
    //                 } else {
    //                     $level = \Psr\Log\LogLevel::NOTICE;
    //                 }
    //                 break;

    //             case E_DEPRECATED:
    //                 $level = \Psr\Log\LogLevel::DEBUG;
    //                 break;

    //             default:
    //                 $level = \Psr\Log\LogLevel::INFO;
    //                 break;
    //         }

    //         // Don't log DEBUG messages
    //         if (\Psr\Log\LogLevel::DEBUG != $level) {
    //             $logger->log($level, 'PHP Error: ', $error);
    //         }
    //     }
    // }

    /**
     * Classes should override this function to provide any definitions to be
     * added to the Dependency Injection container.
     * 
     * @return array<string,mixed> An array DI definitions to be added to the
     *      container.
     */
    protected function getDefinitions(): array
    {
        return [];
    }

    /**
     * 
     * 
     * @return array<string,mixed>
     */
    private function privGetDefinitions(): array
    {
        return [
            // DSN for Symfony/LockFactory
            'lock.dsn' => fn() => $_ENV['LOCK_DSN'] ?? 'memcached://127.0.0.1:11211',

            PersistingStoreInterface::class => function (ContainerInterface $c): PersistingStoreInterface {
                return StoreFactory::createStore($c->get('lock.dsn'));
            },

            LoggerInterface::class => \DI\autowire(PluginLogger::class),
            \Psr\SimpleCache\CacheInterface::class => \DI\autowire(DataCache::class),
            TimerInterface::class => \DI\autowire(Timer::class),

            LockFactory::class => \DI\autowire(),
            // LockFactory::class => function (ContainerInterface $c): LockFactory {
            //     $factory = new LockFactory($c->get(PersistingStoreInterface::class));
            //     $factory->setLogger($c->get(LoggerInterface::class));

            //     return $factory;
            //     },

            ShortcodeProxy::class => \DI\autowire(),
            AjaxHandlerProxy::class => \DI\autowire(),
            AjaxCacheHelper::class => \DI\autowire(),

            \Psr\Http\Client\ClientInterface::class => \DI\autowire(\GuzzleHttp\Client::class),

            DatabaseUpdater::class => \DI\autowire(),

            LastLoginManager::class => \DI\autowire(),
            RedirectionManager::class => \DI\autowire(),

            /**
             * Shortcodes
             */

            /**
             * Administration classes
             */
            ShortUrlOptionsTab::class => \DI\autowire(),

            LastErrorLogger::class => \DI\autowire(),
            EmailShortCode::class => \DI\autowire(),
            UsernameShortCode::class => \DI\autowire(),
        ];
    }
}
