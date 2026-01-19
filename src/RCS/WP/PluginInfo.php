<?php
declare(strict_types=1);
namespace RCS\WP;

class PluginInfo implements PluginInfoInterface
{
    private string $entryPointFile; // The fully qualified file name of the plugin entry point
    private string $path;           // The file system path to the plugin folder
    private string $url;            // The URL to the root of the plugin
    private string $version;        // The version number of the plugin
    private string $slug;           // The slug for the plugin, e.g. test_plugin
    private string $name;           // The name of the the plugin, e.g. "My Test Plugin"
    private string $writeDir;       // The name of the folder where the plugin can write files

    /**
     *
     * @param string $entryPointFile    The fully qualified file name of the plugin entry point
     */
    public function __construct(string $entryPointFile)
    {
        $this->entryPointFile = $entryPointFile;

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        /** @var array<string, string> */
        $pluginData = get_plugin_data($this->entryPointFile, false, false);

        $this->path = plugin_dir_path($this->entryPointFile);
        $this->url = plugin_dir_url($this->entryPointFile);
        $this->version = $pluginData['Version'];
        $this->slug = $pluginData['TextDomain'];
        $this->name = $pluginData['Name'];

        if (function_exists('wp_upload_dir')) {
            $this->writeDir = \wp_upload_dir()['basedir'] . '/' . $this->slug . '/';
        } else {
            $this->writeDir = sys_get_temp_dir() . '/';
        }
    }

    public static function isPluginActive(string $pluginFile): bool
    {
        if (defined('ABSPATH')) { // Wrap in case we get invoked via unit testing
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return \is_plugin_active($pluginFile);
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getEntryPointFile()
     */
    public function getEntryPointFile(): string
    {
        return $this->entryPointFile;
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getFile()
     */
    public function getFile(): string
    {
        return plugin_basename($this->entryPointFile);
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getPath()
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getUrl()
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getVersion()
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getSlug()
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getName()
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     *
     * {@inheritDoc}
     * @see \RCS\WP\PluginInfoInterface::getWriteDir()
     */
    public function getWriteDir(): string
    {
        return $this->writeDir;
    }
}
