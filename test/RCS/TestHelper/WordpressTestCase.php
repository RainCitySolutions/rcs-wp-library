<?php
namespace RCS\TestHelper;

!defined('WP_PLUGIN_DIR') && define( 'WP_PLUGIN_DIR', '/var/www/wp-content/plugins' );

/**
 * WordpressTestCase base class.
 */
abstract class WordpressTestCase extends RainCityTestCase
{
    const WP_DB_PREFIX = "test_wpdb_";

    /** @var array<string, string> */
    private array $optionsTable;
    /** @var array<string, string> */
    private array $siteOptionsTable;
    /** @var array<int, array<string, string>> */
    private array $userMeta;
    /** @var array<string, array<string, string>> */
    private array $plugins;
    /** @var string[] */
    private array $cronSchedules;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $reflector = new \ReflectionClass(static::class);

        if (empty($reflector->getAttributes('PHPUnit\Framework\Attributes\RunClassInSeparateProcess'))) {
            // This is because of some funky side effects related to Brain\Monkey
            fwrite(STDOUT, PHP_EOL.'WARNING: Test cases extending WordpressTestCase may want to use the RunClassInSeparateProcess attribute'.PHP_EOL);
        }
    }

    /**
     * Runs before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;

        $wpdb = self::createMock('\wpdb');
//         $wpdb = Mockery::mock('\wpdb');
//         $wpdb->makePartial();
        $wpdb->prefix = self::WP_DB_PREFIX;

        // reset mock db tables
        $this->optionsTable = $this->siteOptionsTable = $this->userMeta = array();

        // get_plugins() response
        $this->plugins = array();

        // wp_get_schedules default response
        $this->cronSchedules = ['hourly', 'twicedaily', 'daily', 'weekly'];

        \Brain\Monkey\Functions\when('_doing_it_wrong')->alias(function ($function, $message, $version) {
            trigger_error(
                sprintf('%1$s was called <strong>incorrectly</strong>. %2$s %3$s', $function, $message, $version),
                E_USER_NOTICE
                );
        });
        \Brain\Monkey\Functions\when('is_admin')->alias(fn () => true);
        \Brain\Monkey\Functions\when('wp_normalize_path')->alias(fn ($path) => $path);
        \Brain\Monkey\Functions\when('plugin_dir_path')->alias(
            fn ($file) => dirname($file) // '/var/www/wp-content/plugins/test-plugin/'.basename($file)
            );
        \Brain\Monkey\Functions\when('plugin_basename')->alias(
            fn ($file) => dirname($file) . '/' . basename($file)
            );
        \Brain\Monkey\Functions\when('plugin_dir_url')->alias(
            fn ($pluginFile) => 'http://test.org/wp-content/plugins/test-plugin/'
            );
        \Brain\Monkey\Functions\when('get_site_url')->alias(fn() => 'http://test.org/');

        \Brain\Monkey\Functions\when('add_option')->alias(array($this, 'add_option'));
        \Brain\Monkey\Functions\when('update_option')->alias(array($this, 'update_option'));
        \Brain\Monkey\Functions\when('get_option')->alias(array($this, 'get_option'));
        \Brain\Monkey\Functions\when('delete_option')->alias(array($this, 'delete_option'));

        \Brain\Monkey\Functions\when('get_site_option')->alias(array($this, 'get_site_option'));
        \Brain\Monkey\Functions\when('update_site_option')->alias(array($this, 'update_site_option'));

        \Brain\Monkey\Functions\when('get_user_meta')->alias(array($this, 'get_user_meta'));
        \Brain\Monkey\Functions\when('update_user_meta')->alias(array($this, 'update_user_meta'));

        \Brain\Monkey\Functions\when('register_activation_hook')->alias(function () { /* Do nothing */ });
        \Brain\Monkey\Functions\when('register_deactivation_hook')->alias(function () { /* Do nothing */ });
        \Brain\Monkey\Functions\when('register_uninstall_hook')->alias(function () { /* Do nothing */ });

        \Brain\Monkey\Functions\when('get_plugins')->alias(fn () => $this->plugins);
        \Brain\Monkey\Functions\when('is_plugin_active')->alias(fn (string $plugin) => array_key_exists($plugin, $this->plugins));
        \Brain\Monkey\Functions\when('wp_get_schedules')->alias(fn () => $this->cronSchedules);

        \Brain\Monkey\Functions\when('add_theme_support')->justReturn();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);

        parent::tearDown();
    }

    public function add_option(string $option, mixed $value = '', string $deprecated = '', ?string $autoload = 'yes'): bool
    {
        $result = false;

        if (!isset($this->optionsTable[$option])) {
            $this->optionsTable[$option] = $value;
            $result = true;
        }

        return $result;
    }

    public function update_option(string $option, mixed $value, ?string $autoload = null): bool
    {
        $this->optionsTable[$option] = $value;
        return true;
    }

    public function get_option(string $option, mixed $default = false): string|bool
    {
        $result = $default;

        if (isset($this->optionsTable[$option])) {
            $result = $this->optionsTable[$option];
        }

        return $result;
    }

    public function delete_option(string $option): bool
    {
        $result = false;

        if (isset($this->optionsTable[$option])) {
            unset ($this->optionsTable[$option]);
            $result = true;
        }

        return $result;
    }

    public function update_site_option(string $option, mixed $value): bool
    {
        $this->siteOptionsTable[$option] = $value;
        return true;
    }

    public function get_site_option(string $option, mixed $default = false, bool $deprecated = true): string|bool
    {
        $result = false;

        if (isset($this->siteOptionsTable[$option])) {
            $result = $this->siteOptionsTable[$option];
        }

        return $result;
    }

    public function get_user_meta(int $userId, string $key = '', bool $single = false): string|bool
    {
        $result = false;

        if (isset($this->userMeta[$userId])) {
            $userMetaRef = $this->userMeta[$userId];

            if (array_key_exists($key, $userMetaRef)) {
                $result = $userMetaRef[$key];
            }
        }

        return $result;
    }

    public function update_user_meta(int $userId, string $metaKey, mixed $metaValue, mixed $prevValue = ''): int|bool
    {
        $result = false;

        // Ensure the user has an entry in the array
        if (!isset($this->userMeta[$userId])) {
            $this->userMeta[$userId] = array();
        }

        $userMetaRef = &$this->userMeta[$userId];

        if (array_key_exists($metaKey, $userMetaRef)) {
            if ($metaValue !== $userMetaRef[$metaKey]) {
                $userMetaRef[$metaKey] = serialize($metaValue);
                $result = true;
            }
        } else {
            $userMetaRef[$metaKey] = serialize($metaValue);
            $result = 1;
        }

        return $result;
    }

    /**
     * Add a plugin to the list of plugins that will be returned by the
     * get_plugins() function.
     *
     * @param string $pluginFile The name of the main plugin file (need not be real)
     * @param array<string, string> $pluginInfo The array of plugin information
     */
    protected function addPlugin(string $pluginFile, array $pluginInfo): void
    {
        $this->plugins[$pluginFile] = $pluginInfo;
    }
}

class WordPressRestServerStub   // A test stub of WP_REST_Server
{
    const READABLE = 'GET';
}
