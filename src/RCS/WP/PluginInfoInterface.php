<?php
declare(strict_types=1);
namespace RCS\WP;

interface PluginInfoInterface
{
    /**
     * Fetch the fully qualified file name of the plugin entry point.
     *
     * @return string
     */
    public function getEntryPointFile(): string;

    /**
     * Fetch the file name of the plugin entry point relative to the
     * "plugins" folder.
     *
     * @return string
     */
    public function getFile(): string;

    /**
     * Fetch the file system path to the plugin folder.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Fetch the URL to the root of the plugin.
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * Fetch the version number of the plugin.
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Fetch the slug for the plugin, e.g. test_plugin.
     *
     * @return string
     */
    public function getSlug(): string;

    /**
     * Fetch the name of the the plugin, e.g. "My Test Plugin"
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Fetch the name of the folder where the plugin can write files. This is
     * should be a folder named with the slug of the plugin under the
     * wp-uploads folder.
     *
     * @return string
     */
    public function getWriteDir(): string;
}
